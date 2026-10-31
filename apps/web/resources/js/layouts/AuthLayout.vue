<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, MessageCircle, Send } from '@lucide/vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const name = page.props.name;
</script>

<template>
    <div
        class="relative flex min-h-svh items-center justify-center overflow-hidden bg-telegram-bg p-4 sm:p-6"
    >
        <div
            class="pointer-events-none absolute -top-32 -left-24 size-96 rounded-full bg-chat-accent/20 blur-3xl"
        />
        <div
            class="pointer-events-none absolute -right-24 -bottom-32 size-96 rounded-full bg-chat-accent/10 blur-3xl"
        />

        <div
            class="relative grid w-full max-w-4xl overflow-hidden rounded-3xl border border-black/5 bg-telegram-panel shadow-2xl shadow-black/10 lg:grid-cols-[1.05fr_1fr] dark:border-white/10 dark:shadow-black/50"
        >
            <section
                class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-[#2aabee] via-[#229ed9] to-[#1e88bd] p-10 text-white lg:flex"
            >
                <div
                    class="pointer-events-none absolute -top-16 -right-10 size-64 rounded-full bg-white/10"
                />
                <div
                    class="pointer-events-none absolute -bottom-24 -left-10 size-72 rounded-full bg-white/10"
                />

                <Link
                    :href="home()"
                    class="relative flex items-center gap-3 text-lg font-semibold tracking-tight"
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur"
                    >
                        <Send class="size-5" />
                    </span>
                    {{ name }}
                </Link>

                <div class="relative space-y-6">
                    <div class="space-y-3">
                        <h2 class="text-3xl leading-tight font-semibold">
                            Simple. Secure.<br />Fast chatting.
                        </h2>
                        <p class="max-w-sm text-sm leading-6 text-white/80">
                            Stay close to the people who matter with private,
                            real-time conversations in one calm place.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            class="flex max-w-xs items-end gap-2 rounded-2xl rounded-bl-md bg-white/15 px-4 py-3 text-sm text-white/90 backdrop-blur"
                        >
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-white/20"
                            >
                                <MessageCircle class="size-3.5" />
                            </span>
                            Hey! Are you online?
                        </div>
                        <div
                            class="ml-auto flex max-w-xs items-end justify-end gap-2 rounded-2xl rounded-br-md bg-white px-4 py-3 text-sm text-[#1a7fb5] shadow-lg shadow-black/5"
                        >
                            Always. Let's talk.
                            <Check class="mb-0.5 size-4 shrink-0" />
                        </div>
                    </div>
                </div>

                <p class="relative text-xs text-white/60">
                    Private by design. No noise, just conversations.
                </p>
            </section>

            <section class="flex flex-col justify-center px-6 py-10 sm:px-12">
                <Link
                    :href="home()"
                    class="mb-8 flex items-center gap-3 text-base font-semibold tracking-tight lg:hidden"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-full bg-chat-accent text-white shadow-lg shadow-chat-accent/25"
                    >
                        <Send class="size-4.5" />
                    </span>
                    {{ name }}
                </Link>

                <div class="mb-8 space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </section>
        </div>
    </div>
</template>
