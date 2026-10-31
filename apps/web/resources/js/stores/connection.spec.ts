import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { useConnectionStore } from './connection';

describe('connection store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('starts disconnected', () => {
        expect(useConnectionStore().status).toBe('disconnected');
    });

    it('moves through connection states', () => {
        const store = useConnectionStore();

        store.setStatus('connecting');
        expect(store.isConnected).toBe(false);

        store.setStatus('connected');
        expect(store.isConnected).toBe(true);
        expect(store.lastConnectedAt).not.toBeNull();
    });

    it('tracks errors and retry attempts', () => {
        const store = useConnectionStore();

        store.setError('Gateway unavailable');
        store.incrementAttempts();

        expect(store.error).toBe('Gateway unavailable');
        expect(store.attempts).toBe(1);

        store.setStatus('connected');

        expect(store.error).toBeNull();
    });
});
