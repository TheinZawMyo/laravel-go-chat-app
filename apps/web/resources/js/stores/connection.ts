import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export type ConnectionStatus =
    | 'disconnected'
    | 'connecting'
    | 'connected'
    | 'reconnecting';

export const useConnectionStore = defineStore('connection', () => {
    const status = ref<ConnectionStatus>('disconnected');
    const attempts = ref(0);

    const isConnected = computed(() => status.value === 'connected');

    function setStatus(next: ConnectionStatus): void {
        status.value = next;
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
        isConnected,
        setStatus,
        incrementAttempts,
        resetAttempts,
    };
});
