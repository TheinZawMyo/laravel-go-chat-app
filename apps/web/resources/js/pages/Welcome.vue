<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCheck,
    Lock,
    MessageCircle,
    Send,
    Sparkles,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import { login, register } from '@/routes';
import { index as chats } from '@/routes/chats';

const page = usePage();
const name = page.props.name;
const isAuthenticated = computed(() => page.props.auth.user !== null);

const features = [
    {
        icon: Lock,
        title: 'Private by design',
        description:
            'Your conversations stay between you and the people you choose.',
    },
    {
        icon: Zap,
        title: 'Fast and fluid',
        description:
            'Messages arrive instantly with a clean, distraction-free interface.',
    },
    {
        icon: Sparkles,
        title: 'Simple by default',
        description:
            'No clutter, no noise. Just the conversations that matter.',
    },
];

const messages = [
    {
        body: 'Hey! Did you see the new update?',
        time: '09:41',
        own: false,
    },
    {
        body: 'Just opened it. Looks great.',
        time: '09:42',
        own: true,
    },
    {
        body: 'Right? Everything feels so much faster now.',
        time: '09:42',
        own: false,
    },
];
</script>

<template>
    <Head :title="isAuthenticated ? 'Chats' : 'Welcome'" />

    <div
        class="relative min-h-svh overflow-hidden bg-telegram-bg text-foreground"
    >
        <div
            class="pointer-events-none absolute -top-40 -left-32 size-[32rem] rounded-full bg-chat-accent/15 blur-3xl"
        />
        <div
            class="pointer-events-none absolute top-1/3 -right-40 size-[30rem] rounded-full bg-chat-accent/10 blur-3xl"
        />

        <div
            class="relative mx-auto flex min-h-svh w-full max-w-6xl flex-col px-5 sm:px-8"
        >
            <header class="flex h-20 items-center justify-between gap-4">
                <Link href="/" class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-full bg-chat-accent text-white shadow-lg shadow-chat-accent/25"
                    >
                        <Send class="size-5" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight">
                        {{ name }}
                    </span>
                </Link>

                <nav class="flex items-center gap-2 sm:gap-3">
                    <template v-if="isAuthenticated">
                        <Link
                            :href="chats()"
                            class="inline-flex h-10 items-center gap-2 rounded-full bg-chat-accent px-5 text-sm font-semibold text-white shadow-lg shadow-chat-accent/25 transition-colors hover:bg-chat-accent/90"
                        >
                            Open chats
                            <ArrowRight class="size-4" />
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-10 items-center rounded-full px-4 text-sm font-medium text-muted-foreground transition-colors hover:bg-telegram-hover hover:text-foreground"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex h-10 items-center rounded-full bg-chat-accent px-5 text-sm font-semibold text-white shadow-lg shadow-chat-accent/25 transition-colors hover:bg-chat-accent/90"
                        >
                            Get started
                        </Link>
                    </template>
                </nav>
            </header>

            <main
                class="grid flex-1 items-center gap-12 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16 lg:py-16"
            >
                <section class="max-w-xl">
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-chat-accent/10 px-3.5 py-1.5 text-xs font-semibold text-chat-accent"
                    >
                        <MessageCircle class="size-3.5" />
                        Simple, secure messaging
                    </span>

                    <h1
                        class="mt-5 text-4xl leading-[1.08] font-semibold tracking-tight sm:text-5xl lg:text-6xl"
                    >
                        Conversations that feel
                        <span class="text-chat-accent">effortless.</span>
                    </h1>

                    <p
                        class="mt-5 max-w-lg text-base leading-7 text-muted-foreground"
                    >
                        A calm, fast place to talk with the people who matter.
                        Private chats, instant delivery, and a clean interface
                        that stays out of your way.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            v-if="isAuthenticated"
                            :href="chats()"
                            class="inline-flex h-12 items-center gap-2 rounded-xl bg-chat-accent px-7 text-sm font-semibold text-white shadow-xl shadow-chat-accent/25 transition-colors hover:bg-chat-accent/90"
                        >
                            Go to your chats
                            <ArrowRight class="size-4" />
                        </Link>
                        <template v-else>
                            <Link
                                :href="register()"
                                class="inline-flex h-12 items-center gap-2 rounded-xl bg-chat-accent px-7 text-sm font-semibold text-white shadow-xl shadow-chat-accent/25 transition-colors hover:bg-chat-accent/90"
                            >
                                Create free account
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                :href="login()"
                                class="inline-flex h-12 items-center rounded-xl border border-black/10 bg-telegram-panel px-7 text-sm font-semibold text-foreground shadow-sm transition-colors hover:bg-telegram-hover dark:border-white/10"
                            >
                                Log in
                            </Link>
                        </template>
                    </div>

                    <div
                        class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-2">
                            <CheckCheck class="size-4 text-chat-accent" />
                            Instant delivery
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <CheckCheck class="size-4 text-chat-accent" />
                            Direct conversations
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <CheckCheck class="size-4 text-chat-accent" />
                            Light and dark mode
                        </span>
                    </div>
                </section>

                <section class="relative mx-auto w-full max-w-md lg:max-w-none">
                    <div
                        class="pointer-events-none absolute -inset-4 rounded-[2rem] bg-chat-accent/10 blur-2xl"
                    />

                    <div
                        class="relative overflow-hidden rounded-3xl border border-black/5 bg-telegram-panel shadow-2xl shadow-black/10 dark:border-white/10 dark:shadow-black/40"
                    >
                        <header
                            class="flex items-center gap-3 border-b border-black/5 px-5 py-4 dark:border-white/10"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-full bg-gradient-to-br from-[#2aabee] to-[#1e88bd] text-sm font-semibold text-white"
                            >
                                A
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">
                                    Alice
                                </p>
                                <p class="text-xs text-chat-accent">online</p>
                            </div>
                            <span
                                class="rounded-full bg-muted/70 px-2.5 py-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Chat
                            </span>
                        </header>

                        <div
                            class="chat-wallpaper space-y-2.5 px-4 py-6 sm:px-5"
                        >
                            <div
                                v-for="message in messages"
                                :key="message.body"
                                class="flex"
                                :class="
                                    message.own
                                        ? 'justify-end'
                                        : 'justify-start'
                                "
                            >
                                <div
                                    class="max-w-[82%] rounded-2xl px-3.5 py-2.5 text-sm shadow-sm"
                                    :class="
                                        message.own
                                            ? 'rounded-br-md bg-chat-outgoing text-chat-outgoing-foreground'
                                            : 'rounded-bl-md bg-telegram-panel text-foreground'
                                    "
                                >
                                    <p>{{ message.body }}</p>
                                    <p
                                        class="mt-1 text-right text-[10px] opacity-60"
                                    >
                                        {{ message.time }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <footer
                            class="flex items-center gap-2 border-t border-black/5 p-3 dark:border-white/10"
                        >
                            <div
                                class="flex h-10 flex-1 items-center rounded-full bg-muted/60 px-4 text-sm text-muted-foreground"
                            >
                                Write a message...
                            </div>
                            <span
                                class="flex size-10 items-center justify-center rounded-full bg-chat-accent text-white"
                            >
                                <Send class="size-4" />
                            </span>
                        </footer>
                    </div>

                    <div
                        class="absolute -right-3 -bottom-5 hidden items-center gap-2 rounded-2xl border border-black/5 bg-telegram-panel px-4 py-3 text-xs font-medium shadow-xl shadow-black/10 sm:flex dark:border-white/10 dark:shadow-black/40"
                    >
                        <span
                            class="flex size-7 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500"
                        >
                            <CheckCheck class="size-3.5" />
                        </span>
                        Delivered instantly
                    </div>
                </section>
            </main>

            <section class="pb-12">
                <div class="grid gap-4 sm:grid-cols-3">
                    <article
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-2xl border border-black/5 bg-telegram-panel p-5 shadow-sm dark:border-white/10"
                    >
                        <span
                            class="flex size-10 items-center justify-center rounded-xl bg-chat-accent/10 text-chat-accent"
                        >
                            <component :is="feature.icon" class="size-5" />
                        </span>
                        <h3 class="mt-4 text-sm font-semibold">
                            {{ feature.title }}
                        </h3>
                        <p
                            class="mt-1.5 text-sm leading-6 text-muted-foreground"
                        >
                            {{ feature.description }}
                        </p>
                    </article>
                </div>
            </section>

            <footer
                class="flex flex-col items-center justify-between gap-3 border-t border-black/5 py-6 text-xs text-muted-foreground sm:flex-row dark:border-white/10"
            >
                <p>{{ name }} — private conversations, made simple.</p>
                <div class="flex items-center gap-4">
                    <Link
                        v-if="!isAuthenticated"
                        :href="login()"
                        class="transition-colors hover:text-foreground"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="!isAuthenticated"
                        :href="register()"
                        class="transition-colors hover:text-foreground"
                    >
                        Create account
                    </Link>
                    <Link
                        v-else
                        :href="chats()"
                        class="transition-colors hover:text-foreground"
                    >
                        Open chats
                    </Link>
                </div>
            </footer>
        </div>
    </div>
</template>
