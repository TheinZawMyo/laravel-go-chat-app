import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ChatMessage, Conversation, User } from '@/types';
import { useAuthStore } from './auth';
import { useChatStore } from './chat';

const user: User = {
    id: 'user-1',
    name: 'Test User',
    username: 'testuser',
    email: 'test@example.com',
    avatar_url: null,
    bio: null,
    status_text: null,
    status: 'active',
    email_verified_at: null,
    last_seen_at: null,
    created_at: '2026-10-29T00:00:00Z',
    updated_at: '2026-10-29T00:00:00Z',
};

const other = {
    id: 'user-2',
    name: 'Alice',
    username: 'alice',
    avatar_url: null,
    bio: null,
    status_text: null,
    last_seen_at: null,
};

function createConversation(
    overrides: Partial<Conversation> = {},
): Conversation {
    return {
        id: 'conversation-1',
        type: 'direct',
        title: null,
        avatar_url: null,
        unread_count: 0,
        last_read_seq: 0,
        next_message_seq: 0,
        participant: other,
        last_message: null,
        members: [
            { role: 'member', user },
            { role: 'member', user: other },
        ],
        created_at: '2026-10-29T00:00:00Z',
        updated_at: '2026-10-29T00:00:00Z',
        ...overrides,
    };
}

const conversation = createConversation();

function message(overrides: Partial<ChatMessage> = {}): ChatMessage {
    return {
        id: 'message-1',
        conversation_id: conversation.id,
        seq: 1,
        client_message_id: 'client-1',
        sender: other,
        type: 'text',
        body: 'Hello',
        reply_to: null,
        edited_at: null,
        deleted_at: null,
        created_at: '2026-10-29T00:00:00Z',
        updated_at: '2026-10-29T00:00:00Z',
        ...overrides,
    };
}

function success(body: unknown, status = 200): Response {
    return {
        ok: true,
        status,
        json: async () => body,
    } as Response;
}

const fetchMock = vi.fn();

describe('chat store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        fetchMock.mockReset();
        fetchMock.mockResolvedValue(success({ data: { last_read_seq: 0 } }));
        vi.stubGlobal('fetch', fetchMock);
        useAuthStore().setUser(user);
    });

    it('initialises conversations and active messages in order', () => {
        const store = useChatStore();

        const activeConversation = createConversation();

        store.initialize({
            conversations: [activeConversation],
            activeConversation,
            activeMessages: [
                message({ id: 'message-2', seq: 2, body: 'Second' }),
                message({ id: 'message-1', seq: 1, body: 'First' }),
            ],
            activeHasMore: false,
            activeNextCursor: 1,
        });

        expect(store.conversations).toHaveLength(1);
        expect(store.activeConversation?.id).toBe(conversation.id);
        expect(store.messages.map((item) => item.seq)).toEqual([1, 2]);
        expect(store.nextCursor).toBe(1);
    });

    it('sends optimistically and reconciles with the server response', async () => {
        const store = useChatStore();
        const activeConversation = createConversation();

        store.initialize({
            conversations: [activeConversation],
            activeConversation,
            activeMessages: [],
            activeHasMore: false,
            activeNextCursor: null,
        });

        fetchMock.mockImplementationOnce(
            async (_url: string, options: RequestInit) => {
                const payload = JSON.parse(options.body as string) as Record<
                    string,
                    string
                >;

                return success(
                    {
                        data: message({
                            id: 'server-message',
                            client_message_id: payload.client_message_id,
                            sender: user,
                            body: 'Hello',
                        }),
                    },
                    201,
                );
            },
        );

        const pending = store.sendMessage('Hello');

        expect(store.messages).toHaveLength(1);
        expect(store.messages[0]?.status).toBe('sending');

        const sent = await pending;

        expect(sent?.id).toBe('server-message');
        expect(store.messages).toHaveLength(1);
        expect(store.messages[0]?.status).toBe('sent');
        expect(store.messages[0]?.id).toBe('server-message');
        expect(store.activeConversation?.last_message?.id).toBe(
            'server-message',
        );

        const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit];
        const body = JSON.parse(options.body as string) as Record<
            string,
            unknown
        >;

        expect(url).toBe(`/api/v1/conversations/${conversation.id}/messages`);
        expect(options.method).toBe('POST');
        expect(body.body).toBe('Hello');
        expect(typeof body.client_message_id).toBe('string');
    });

    it('marks an optimistic message as failed and can retry it', async () => {
        const store = useChatStore();
        const activeConversation = createConversation();

        store.initialize({
            conversations: [activeConversation],
            activeConversation,
            activeMessages: [],
            activeHasMore: false,
            activeNextCursor: null,
        });

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 500,
            json: async () => ({ message: 'Server error' }),
        } as Response);

        await store.sendMessage('Hello');

        expect(store.messages[0]?.status).toBe('failed');
        expect(store.error).toBe('Server error');

        const failed = store.messages[0]!;
        const clientMessageId = failed.client_message_id;

        fetchMock.mockResolvedValueOnce(
            success({
                data: message({
                    id: 'server-message',
                    client_message_id: clientMessageId,
                    sender: user,
                    body: 'Hello',
                }),
            }),
        );

        await store.retryMessage(failed);

        expect(store.messages).toHaveLength(1);
        expect(store.messages[0]?.id).toBe('server-message');
        expect(store.messages[0]?.status).toBe('sent');

        const [, options] = fetchMock.mock.calls[1] as [string, RequestInit];
        const body = JSON.parse(options.body as string) as Record<
            string,
            unknown
        >;

        expect(body.client_message_id).toBe(clientMessageId);
    });

    it('prepends older messages and advances the cursor', async () => {
        const store = useChatStore();
        const activeConversation = createConversation({
            last_read_seq: 32,
            next_message_seq: 32,
        });

        store.initialize({
            conversations: [activeConversation],
            activeConversation,
            activeMessages: [
                message({ id: 'message-31', seq: 31 }),
                message({ id: 'message-32', seq: 32 }),
            ],
            activeHasMore: true,
            activeNextCursor: 31,
        });

        fetchMock.mockResolvedValueOnce(
            success({
                data: [
                    message({ id: 'message-29', seq: 29 }),
                    message({ id: 'message-30', seq: 30 }),
                ],
                meta: { has_more: false, next_cursor: 29 },
            }),
        );

        await store.loadOlder();

        expect(store.messages.map((item) => item.seq)).toEqual([
            29, 30, 31, 32,
        ]);
        expect(store.hasMore).toBe(false);
        expect(store.nextCursor).toBe(29);

        const [url] = fetchMock.mock.calls[0] as [string];
        expect(url).toContain('before_seq=31');
    });

    it('loads a conversation and marks its latest message as read', async () => {
        const store = useChatStore();
        const activeConversation = createConversation();

        store.initialize({
            conversations: [activeConversation],
            activeConversation: null,
            activeMessages: [],
            activeHasMore: false,
            activeNextCursor: null,
        });

        fetchMock.mockImplementation(async (url: string) => {
            if (url.endsWith('/messages')) {
                return success({
                    data: [message({ id: 'message-5', seq: 5 })],
                    meta: { has_more: false, next_cursor: 5 },
                });
            }

            return success({ data: { last_read_seq: 5 } });
        });

        await store.selectConversation(conversation.id);

        expect(store.activeConversationId).toBe(conversation.id);
        expect(store.messages).toHaveLength(1);
        expect(store.activeConversation?.last_read_seq).toBe(5);

        const endpoints = fetchMock.mock.calls.map(([url]) => url as string);
        expect(endpoints).toContain(
            `/api/v1/conversations/${conversation.id}/read`,
        );
    });

    it('applies realtime messages and increments unread counts for inactive chats', () => {
        const store = useChatStore();
        const activeConversation = createConversation();

        store.initialize({
            conversations: [activeConversation],
            activeConversation: null,
            activeMessages: [],
            activeHasMore: false,
            activeNextCursor: null,
        });

        const incoming = message({
            id: 'message-9',
            seq: 9,
            sender: other,
            body: 'Live update',
        });

        store.applyRealtimeMessage(incoming);

        expect(store.messages).toHaveLength(0);
        expect(store.conversations[0]?.last_message?.id).toBe('message-9');
        expect(store.conversations[0]?.unread_count).toBe(1);

        store.applyRealtimeMessage(incoming);

        expect(store.conversations[0]?.unread_count).toBe(1);
    });

    it('reconciles realtime messages with optimistic and HTTP messages', () => {
        const store = useChatStore();
        const activeConversation = createConversation();

        store.initialize({
            conversations: [activeConversation],
            activeConversation,
            activeMessages: [],
            activeHasMore: false,
            activeNextCursor: null,
        });

        const sent = message({
            id: 'server-message',
            client_message_id: 'client-reconcile',
            seq: 1,
            sender: user,
            body: 'Hello',
        });

        store.applyRealtimeMessage(sent);
        store.applyRealtimeMessage(sent);

        expect(store.messages).toHaveLength(1);
        expect(store.messages[0]?.id).toBe('server-message');
        expect(store.messages[0]?.status).toBe('sent');
        expect(store.activeConversation?.last_message?.id).toBe(
            'server-message',
        );
    });

    it('opens a direct conversation through the API', async () => {
        const store = useChatStore();

        fetchMock.mockImplementation(async (url: string) => {
            if (url.endsWith('/conversations/direct')) {
                return success({ data: conversation }, 201);
            }

            return success({
                data: [],
                meta: { has_more: false, next_cursor: null },
            });
        });

        await store.openDirect('alice');

        expect(store.conversations[0]?.id).toBe(conversation.id);
        expect(store.activeConversationId).toBe(conversation.id);

        const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/api/v1/conversations/direct');
        expect(JSON.parse(options.body as string)).toEqual({
            username: 'alice',
        });
    });
});
