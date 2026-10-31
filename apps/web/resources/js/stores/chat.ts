import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { ApiError, apiRequest } from '@/lib/http';
import { useAuthStore } from '@/stores/auth';
import type {
    ChatMessage,
    Conversation,
    MessagePage,
    MessageStatus,
    PublicUser,
} from '@/types';

type ChatPagePayload = {
    conversations: Conversation[];
    activeConversation: Conversation | null;
    activeMessages: ChatMessage[];
    activeHasMore: boolean;
    activeNextCursor: number | null;
};

function uuid(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = (Math.random() * 16) | 0;
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}

function sortMessages(messages: ChatMessage[]): ChatMessage[] {
    return [...messages].sort((first, second) => {
        if (first.seq !== second.seq) {
            return first.seq - second.seq;
        }

        return first.created_at.localeCompare(second.created_at);
    });
}

function sortConversations(conversations: Conversation[]): Conversation[] {
    return [...conversations].sort((first, second) =>
        second.updated_at.localeCompare(first.updated_at),
    );
}

function messageKey(message: ChatMessage): string {
    return message.id.startsWith('temp-')
        ? `client:${message.client_message_id}`
        : message.id;
}

function errorMessage(error: unknown): string {
    if (error instanceof ApiError) {
        return error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Something went wrong. Please try again.';
}

async function messageRequest(
    url: string,
    options: Parameters<typeof apiRequest>[1],
): Promise<ChatMessage> {
    const response = await apiRequest<{ data: ChatMessage }>(url, options);

    return response.data;
}

export const useChatStore = defineStore('chat', () => {
    const auth = useAuthStore();

    const conversations = ref<Conversation[]>([]);
    const activeConversationId = ref<string | null>(null);
    const messages = ref<ChatMessage[]>([]);
    const hasMore = ref(false);
    const nextCursor = ref<number | null>(null);
    const replyTo = ref<ChatMessage | null>(null);

    const loadingConversations = ref(false);
    const loadingMessages = ref(false);
    const loadingMore = ref(false);
    const refreshing = ref(false);
    const sending = ref(false);
    const error = ref<string | null>(null);

    let selectionRequest = 0;

    const activeConversation = computed<Conversation | null>(
        () =>
            conversations.value.find(
                (conversation) =>
                    conversation.id === activeConversationId.value,
            ) ?? null,
    );

    function initialize(payload: ChatPagePayload): void {
        conversations.value = sortConversations(payload.conversations);
        activeConversationId.value = payload.activeConversation?.id ?? null;

        if (
            payload.activeConversation !== null &&
            !conversations.value.some(
                (conversation) =>
                    conversation.id === payload.activeConversation?.id,
            )
        ) {
            conversations.value = sortConversations([
                ...conversations.value,
                payload.activeConversation,
            ]);
        }

        messages.value = sortMessages(payload.activeMessages);
        hasMore.value = payload.activeHasMore;
        nextCursor.value = payload.activeNextCursor;
        replyTo.value = null;
        error.value = null;

        const latest = messages.value.at(-1);

        if (latest !== undefined) {
            void markRead(latest.seq);
        }
    }

    function upsertConversation(conversation: Conversation): void {
        const index = conversations.value.findIndex(
            (current) => current.id === conversation.id,
        );

        if (index >= 0) {
            conversations.value.splice(index, 1, conversation);
        } else {
            conversations.value.push(conversation);
        }

        conversations.value = sortConversations(conversations.value);
    }

    function touchConversation(message: ChatMessage): void {
        const conversation = conversations.value.find(
            (current) => current.id === message.conversation_id,
        );

        if (conversation === undefined) {
            return;
        }

        conversation.last_message = message;
        conversation.updated_at = message.updated_at;
        conversation.next_message_seq = Math.max(
            conversation.next_message_seq,
            message.seq,
        );

        conversations.value = sortConversations(conversations.value);
    }

    function upsertMessage(message: ChatMessage): void {
        const normalized: ChatMessage = {
            ...message,
            status: 'sent',
            error: null,
        };

        const index = messages.value.findIndex(
            (current) =>
                current.id === message.id ||
                (current.client_message_id === message.client_message_id &&
                    current.sender.id === message.sender.id),
        );

        if (index >= 0) {
            messages.value.splice(index, 1, normalized);
        } else {
            messages.value.push(normalized);
        }

        messages.value = sortMessages(messages.value);
    }

    function applyRealtimeMessage(message: ChatMessage): void {
        const conversation = conversations.value.find(
            (current) => current.id === message.conversation_id,
        );

        if (conversation === undefined) {
            void fetchConversations();

            return;
        }

        const previousSeq = conversation.last_message?.seq ?? 0;
        const isNewSeq = message.seq > previousSeq;

        if (conversation.id === activeConversationId.value) {
            upsertMessage(message);
        }

        if (
            isNewSeq &&
            message.conversation_id !== activeConversationId.value
        ) {
            conversation.unread_count += 1;
        }

        if (isNewSeq || conversation.last_message?.id === message.id) {
            conversation.last_message = message;
            conversation.next_message_seq = Math.max(
                conversation.next_message_seq,
                message.seq,
            );
            conversation.updated_at = message.updated_at;
            conversations.value = sortConversations(conversations.value);
        }

        if (message.conversation_id === activeConversationId.value) {
            const latest = messages.value.at(-1);

            if (latest !== undefined) {
                void markRead(latest.seq);
            }
        }
    }

    function replaceUrl(path: string): void {
        if (typeof window !== 'undefined') {
            window.history.replaceState({}, '', path);
        }
    }

    async function fetchConversations(): Promise<void> {
        loadingConversations.value = true;

        try {
            const response = await apiRequest<{ data: Conversation[] }>(
                '/api/v1/conversations',
            );

            conversations.value = sortConversations(response.data);
        } catch (requestError) {
            error.value = errorMessage(requestError);
        } finally {
            loadingConversations.value = false;
        }
    }

    async function selectConversation(conversationId: string): Promise<void> {
        const request = ++selectionRequest;

        activeConversationId.value = conversationId;
        messages.value = [];
        hasMore.value = false;
        nextCursor.value = null;
        replyTo.value = null;
        error.value = null;
        loadingMessages.value = true;
        replaceUrl(`/chats/${conversationId}`);

        try {
            const page = await apiRequest<MessagePage>(
                `/api/v1/conversations/${conversationId}/messages`,
            );

            if (request !== selectionRequest) {
                return;
            }

            messages.value = sortMessages(page.data);
            hasMore.value = page.meta.has_more;
            nextCursor.value = page.meta.next_cursor;

            const latest = messages.value.at(-1);

            if (latest !== undefined) {
                void markRead(latest.seq);
            }
        } catch (requestError) {
            if (request === selectionRequest) {
                error.value = errorMessage(requestError);
            }
        } finally {
            if (request === selectionRequest) {
                loadingMessages.value = false;
            }
        }
    }

    function closeConversation(): void {
        selectionRequest += 1;
        activeConversationId.value = null;
        messages.value = [];
        hasMore.value = false;
        nextCursor.value = null;
        replyTo.value = null;
        error.value = null;
        replaceUrl('/chats');
    }

    async function loadOlder(): Promise<void> {
        if (
            activeConversationId.value === null ||
            loadingMore.value ||
            !hasMore.value ||
            nextCursor.value === null
        ) {
            return;
        }

        loadingMore.value = true;

        try {
            const page = await apiRequest<MessagePage>(
                `/api/v1/conversations/${activeConversationId.value}/messages?before_seq=${nextCursor.value}`,
            );

            const known = new Set(
                messages.value.map((message) => messageKey(message)),
            );
            const older = page.data.filter(
                (message) => !known.has(messageKey(message)),
            );

            messages.value = sortMessages([...older, ...messages.value]);
            hasMore.value = page.meta.has_more;
            nextCursor.value = page.meta.next_cursor;
        } catch (requestError) {
            error.value = errorMessage(requestError);
        } finally {
            loadingMore.value = false;
        }
    }

    async function refreshMessages(): Promise<void> {
        if (activeConversationId.value === null || refreshing.value) {
            return;
        }

        refreshing.value = true;

        try {
            const page = await apiRequest<MessagePage>(
                `/api/v1/conversations/${activeConversationId.value}/messages`,
            );

            page.data.forEach(upsertMessage);

            if (!hasMore.value && page.meta.has_more) {
                hasMore.value = true;
                nextCursor.value = page.meta.next_cursor;
            }

            const latest = messages.value.at(-1);

            if (latest !== undefined) {
                void markRead(latest.seq);
            }
        } catch (requestError) {
            error.value = errorMessage(requestError);
        } finally {
            refreshing.value = false;
        }
    }

    async function markRead(seq: number): Promise<void> {
        const conversation = activeConversation.value;

        if (conversation === null || seq <= conversation.last_read_seq) {
            return;
        }

        conversation.last_read_seq = seq;
        conversation.unread_count = 0;

        try {
            await apiRequest<{ data: { last_read_seq: number } }>(
                `/api/v1/conversations/${conversation.id}/read`,
                {
                    method: 'POST',
                    body: { seq },
                },
            );
        } catch {
            // Read cursors are reconciled on refresh; a failed receipt is not fatal.
        }
    }

    async function sendMessage(
        body: string,
        replyToId: string | null = null,
    ): Promise<ChatMessage | null> {
        const conversation = activeConversation.value;
        const sender = auth.user;

        if (conversation === null || sender === null) {
            return null;
        }

        const trimmed = body.trim();

        if (trimmed === '') {
            return null;
        }

        const clientMessageId = uuid();
        const now = new Date().toISOString();
        const highestSeq = messages.value.reduce(
            (highest, message) => Math.max(highest, message.seq),
            conversation.next_message_seq,
        );

        const optimistic: ChatMessage = {
            id: `temp-${clientMessageId}`,
            conversation_id: conversation.id,
            seq: highestSeq + 1,
            client_message_id: clientMessageId,
            sender: sender as PublicUser,
            type: 'text',
            body: trimmed,
            reply_to:
                messages.value.find((message) => message.id === replyToId) ??
                null,
            edited_at: null,
            deleted_at: null,
            created_at: now,
            updated_at: now,
            status: 'sending',
            error: null,
        };

        messages.value = sortMessages([...messages.value, optimistic]);
        sending.value = true;
        error.value = null;

        try {
            const message = await messageRequest(
                `/api/v1/conversations/${conversation.id}/messages`,
                {
                    method: 'POST',
                    body: {
                        client_message_id: clientMessageId,
                        body: trimmed,
                        reply_to_message_id: replyToId,
                    },
                },
            );

            upsertMessage(message);
            touchConversation(message);

            return message;
        } catch (requestError) {
            const failed = messages.value.find(
                (message) => message.client_message_id === clientMessageId,
            );

            if (failed !== undefined) {
                failed.status = 'failed';
                failed.error = errorMessage(requestError);
            }

            error.value = errorMessage(requestError);

            return null;
        } finally {
            sending.value = false;
        }
    }

    async function retryMessage(message: ChatMessage): Promise<void> {
        if (message.status !== 'failed' || message.body === null) {
            return;
        }

        message.status = 'sending';
        message.error = null;

        try {
            const sent = await messageRequest(
                `/api/v1/conversations/${message.conversation_id}/messages`,
                {
                    method: 'POST',
                    body: {
                        client_message_id: message.client_message_id,
                        body: message.body,
                        reply_to_message_id: message.reply_to?.id ?? null,
                    },
                },
            );

            upsertMessage(sent);
            touchConversation(sent);
        } catch (requestError) {
            message.status = 'failed';
            message.error = errorMessage(requestError);
        }
    }

    async function editMessage(
        message: ChatMessage,
        body: string,
    ): Promise<ChatMessage> {
        const updated = await messageRequest(`/api/v1/messages/${message.id}`, {
            method: 'PATCH',
            body: { body },
        });

        upsertMessage(updated);

        if (activeConversation.value?.last_message?.id === updated.id) {
            touchConversation(updated);
        }

        return updated;
    }

    async function deleteMessage(message: ChatMessage): Promise<void> {
        const deleted = await messageRequest(`/api/v1/messages/${message.id}`, {
            method: 'DELETE',
        });

        upsertMessage(deleted);
        await fetchConversations();
    }

    async function openDirect(username: string): Promise<Conversation> {
        const response = await apiRequest<{ data: Conversation }>(
            '/api/v1/conversations/direct',
            {
                method: 'POST',
                body: { username },
            },
        );

        upsertConversation(response.data);
        await selectConversation(response.data.id);

        return response.data;
    }

    function setReplyTo(message: ChatMessage | null): void {
        replyTo.value = message;
    }

    function setMessageStatus(
        message: ChatMessage,
        status: MessageStatus,
    ): void {
        message.status = status;
    }

    return {
        conversations,
        activeConversationId,
        activeConversation,
        messages,
        hasMore,
        nextCursor,
        replyTo,
        loadingConversations,
        loadingMessages,
        loadingMore,
        refreshing,
        sending,
        error,
        initialize,
        fetchConversations,
        selectConversation,
        closeConversation,
        loadOlder,
        refreshMessages,
        markRead,
        sendMessage,
        retryMessage,
        editMessage,
        deleteMessage,
        openDirect,
        setReplyTo,
        setMessageStatus,
        upsertConversation,
        upsertMessage,
        applyRealtimeMessage,
        touchConversation,
    };
});
