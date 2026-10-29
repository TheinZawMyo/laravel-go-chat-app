import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { User } from '@/types';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);

    const isAuthenticated = computed(() => user.value !== null);
    const displayName = computed(() => user.value?.name ?? 'Guest');

    function setUser(next: User | null): void {
        user.value = next;
    }

    function clear(): void {
        user.value = null;
    }

    return {
        user,
        isAuthenticated,
        displayName,
        setUser,
        clear,
    };
});
