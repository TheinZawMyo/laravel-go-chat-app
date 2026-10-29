import type { ChatMessage, Conversation } from '@/types';

const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
});

const dayFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
});

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
});

export function formatMessageTime(value: string | null): string {
    if (value === null) {
        return '';
    }

    return timeFormatter.format(new Date(value));
}

export function formatConversationTime(value: string | null): string {
    if (value === null) {
        return '';
    }

    const date = new Date(value);
    const now = new Date();

    if (date.toDateString() === now.toDateString()) {
        return timeFormatter.format(date);
    }

    if (date.getFullYear() === now.getFullYear()) {
        return dayFormatter.format(date);
    }

    return dateFormatter.format(date);
}

export function conversationTitle(conversation: Conversation | null): string {
    if (conversation === null) {
        return 'Chats';
    }

    return (
        conversation.title ??
        conversation.participant?.name ??
        conversation.participant?.username ??
        'Conversation'
    );
}

export function messagePreview(message: ChatMessage | null): string {
    if (message === null) {
        return 'No messages yet';
    }

    if (message.deleted_at !== null) {
        return 'Message deleted';
    }

    const body = message.body?.trim();

    return body && body.length > 0 ? body : 'No messages yet';
}
