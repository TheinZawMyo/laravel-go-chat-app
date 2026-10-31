<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
</script>

<template>
    <Head title="Appearance settings" />

    <div class="space-y-6">
        <section
            class="rounded-2xl border border-black/5 bg-telegram-panel p-5 shadow-sm sm:p-6 dark:border-white/10"
        >
            <Heading
                variant="small"
                title="Appearance"
                description="Choose how the app looks on this device"
            />
            <div class="mt-5">
                <AppearanceTabs />
            </div>
        </section>
    </div>
</template>
