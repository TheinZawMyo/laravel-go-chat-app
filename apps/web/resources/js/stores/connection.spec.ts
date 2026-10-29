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
    });
});
