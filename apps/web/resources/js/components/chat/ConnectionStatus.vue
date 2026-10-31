<script setup lang="ts">
import { computed } from 'vue';
import { useConnectionStore } from '@/stores/connection';
import { useRealtimeStore } from '@/stores/realtime';

const connection = useConnectionStore();
const realtime = useRealtimeStore();

const label = computed(() => {
    switch (connection.status) {
        case 'connected':
            return 'Live';
        case 'connecting':
            return 'Connecting';
        case 'reconnecting':
            return 'Reconnecting';
        default:
            return 'Offline';
    }
});

const color = computed(() => {
    switch (connection.status) {
        case 'connected':
            return 'bg-emerald-500';
        case 'connecting':
        case 'reconnecting':
            return 'bg-amber-500';
        default:
            return 'bg-destructive';
    }
});

function retry(): void {
    realtime.retry();
}
</script>

<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border/70 bg-background px-2.5 py-1 text-[11px] font-medium text-muted-foreground transition-colors hover:text-foreground"
        :title="connection.error ?? `Realtime connection: ${label}`"
        :aria-label="`Realtime connection: ${label}. Click to retry.`"
        @click="retry"
    >
        <span
            class="size-1.5 rounded-full"
            :class="[
                color,
                connection.status === 'connecting' ||
                connection.status === 'reconnecting'
                    ? 'animate-pulse'
                    : '',
            ]"
        />
        <span>{{ label }}</span>
    </button>
</template>
