<script setup lang="ts">
import { ref } from 'vue';
import { CornerUpLeft, Send, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useChatStore } from '@/stores/chat';

const chat = useChatStore();

const draft = ref('');

async function submit(): Promise<void> {
    const body = draft.value.trim();

    if (body === '' || chat.activeConversation === null) {
        return;
    }

    const replyToId = chat.replyTo?.id ?? null;
    const reply = chat.replyTo;

    draft.value = '';
    chat.setReplyTo(null);

    const message = await chat.sendMessage(body, replyToId);

    if (message === null && reply !== null) {
        chat.setReplyTo(reply);
    }
}
</script>

<template>
    <div
        class="shrink-0 border-t border-sidebar-border/70 bg-chat-canvas px-3 py-3 sm:px-5 sm:py-4"
    >
        <div
            v-if="chat.replyTo !== null"
            class="mx-auto mb-3 flex max-w-4xl items-start gap-2 rounded-xl border-l-2 border-chat-accent bg-background px-3 py-2.5 text-xs shadow-sm"
        >
            <CornerUpLeft
                class="mt-0.5 size-3.5 shrink-0 text-chat-accent"
            />
            <div class="min-w-0 flex-1">
                <div class="font-medium">
                    Replying to {{ chat.replyTo.sender.name }}
                </div>
                <div
                    class="line-clamp-2 break-words whitespace-pre-wrap text-muted-foreground"
                >
                    {{ chat.replyTo.body }}
                </div>
            </div>
            <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                aria-label="Cancel reply"
                @click="chat.setReplyTo(null)"
            >
                <X />
            </Button>
        </div>

        <form
            class="mx-auto flex max-w-4xl items-end gap-2"
            @submit.prevent="submit"
        >
            <div
                class="flex min-h-12 min-w-0 flex-1 items-end rounded-2xl border border-border/80 bg-background px-1.5 py-1 shadow-sm transition-shadow focus-within:border-chat-accent/50 focus-within:ring-2 focus-within:ring-chat-accent/15"
            >
                <Textarea
                    v-model="draft"
                    rows="1"
                    placeholder="Write a message…"
                    aria-label="Message"
                    class="max-h-40 min-h-10 flex-1 resize-none border-0 bg-transparent py-2.5 shadow-none focus-visible:border-0 focus-visible:ring-0"
                    @keydown.enter.exact.prevent="submit"
                />
            </div>

            <Button
                type="submit"
                size="icon"
                class="mb-0.5 size-11 shrink-0 rounded-full bg-chat-accent text-white hover:bg-chat-accent/90"
                :disabled="draft.trim() === ''"
                aria-label="Send message"
            >
                <Spinner v-if="chat.sending" />
                <Send v-else />
            </Button>
        </form>

        <p
            v-if="chat.error !== null"
            class="mx-auto mt-1.5 max-w-4xl text-xs text-destructive"
        >
            {{ chat.error }}
        </p>
        <p
            class="mx-auto mt-1.5 hidden max-w-4xl px-1 text-[11px] text-muted-foreground sm:block"
        >
            Enter to send · Shift + Enter for a new line
        </p>
    </div>
</template>
