<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div class="inline-flex w-full max-w-sm gap-1 rounded-xl bg-muted/60 p-1">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :class="[
                'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                appearance === value
                    ? 'bg-chat-accent text-white shadow-sm shadow-chat-accent/20'
                    : 'text-muted-foreground hover:bg-telegram-hover hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="h-4 w-4" />
            <span>{{ label }}</span>
        </button>
    </div>
</template>
