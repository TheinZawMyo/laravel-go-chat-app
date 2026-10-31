import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { RealtimeStatus } from '@/types/realtime';

export type ConnectionStatus = RealtimeStatus;

export const useConnectionStore = defineStore('connection', () => {
    const status = ref<ConnectionStatus>('disconnected');
    const attempts = ref(0);
    const error = ref<string | null>(null);
    const lastConnectedAt = ref<string | null>(null);

    const isConnected = computed(() => status.value === 'connected');

    function setStatus(next: ConnectionStatus): void {
        status.value = next;

        if (next === 'connected') {
            error.value = null;
            lastConnectedAt.value = new Date().toISOString();
        }
    }

    function setError(next: string | null): void {
        error.value = next;
    }

    function incrementAttempts(): void {
        attempts.value += 1;
    }

    function resetAttempts(): void {
        attempts.value = 0;
    }

    return {
        status,
        attempts,
        error,
        lastConnectedAt,
        isConnected,
        setStatus,
        setError,
        incrementAttempts,
        resetAttempts,
    };
});
