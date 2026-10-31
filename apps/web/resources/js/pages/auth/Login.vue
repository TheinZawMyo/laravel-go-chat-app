<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description: 'Log in to continue your conversations.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const inputClass =
    'h-11 rounded-xl border-transparent bg-muted/60 px-4 shadow-none transition-colors focus-visible:border-chat-accent/40 focus-visible:bg-background focus-visible:ring-chat-accent/20';
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-5 rounded-xl bg-emerald-500/10 px-4 py-3 text-center text-sm font-medium text-emerald-600 dark:text-emerald-400"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email" class="text-sm font-medium"
                    >Email address</Label
                >
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                    :class="inputClass"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-sm font-medium"
                    >Password</Label
                >
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    :class="inputClass"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <Label
                    for="remember"
                    class="flex items-center gap-2.5 text-sm font-normal text-muted-foreground"
                >
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>

                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-sm"
                    :tabindex="5"
                >
                    Forgot password?
                </TextLink>
            </div>

            <Button
                type="submit"
                size="lg"
                class="h-11 w-full rounded-xl bg-chat-accent text-sm font-semibold text-white shadow-lg shadow-chat-accent/25 hover:bg-chat-accent/90"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Create one</TextLink>
        </p>
    </Form>
</template>
