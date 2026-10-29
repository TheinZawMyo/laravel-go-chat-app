import { usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function useAuth() {
    const page = usePage();
    const store = useAuthStore();

    watchEffect(() => {
        store.setUser(page.props.auth.user ?? null);
    });

    return {
        user: computed(() => store.user),
        isAuthenticated: computed(() => store.isAuthenticated),
        displayName: computed(() => store.displayName),
    };
}
