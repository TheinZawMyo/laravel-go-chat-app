<script setup lang="ts">
import { ref, watch } from 'vue';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ApiError } from '@/lib/http';
import { useChatStore } from '@/stores/chat';

const chat = useChatStore();

const open = ref(false);
const username = ref('');
const submitting = ref(false);
const error = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        username.value = '';
        error.value = null;
    }
});

async function submit(): Promise<void> {
    const target = username.value.trim().replace(/^@/, '');

    if (target === '' || submitting.value) {
        return;
    }

    submitting.value = true;
    error.value = null;

    try {
        await chat.openDirect(target);
        open.value = false;
    } catch (requestError) {
        error.value =
            requestError instanceof ApiError
                ? (requestError.errors.username?.[0] ?? requestError.message)
                : 'Unable to start this conversation.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-10 rounded-xl bg-chat-accent text-white shadow-sm hover:bg-chat-accent/90 hover:text-white"
                aria-label="New conversation"
            >
                <Plus />
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>New conversation</DialogTitle>
                <DialogDescription>
                    Enter the username of the person you want to message.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="direct-username">Username</Label>
                    <Input
                        id="direct-username"
                        v-model="username"
                        autocomplete="off"
                        autofocus
                        placeholder="alice"
                    />
                    <p v-if="error" class="text-sm text-destructive">
                        {{ error }}
                    </p>
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="submitting || username.trim() === ''"
                >
                    {{ submitting ? 'Opening…' : 'Start conversation' }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
