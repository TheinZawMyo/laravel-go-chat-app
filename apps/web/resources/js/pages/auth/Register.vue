<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Join in a few seconds and start chatting.',
    },
});

const inputClass =
    'h-11 rounded-xl border-transparent bg-muted/60 px-4 shadow-none transition-colors focus-visible:border-chat-accent/40 focus-visible:bg-background focus-visible:ring-chat-accent/20';
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name" class="text-sm font-medium">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Your full name"
                    :class="inputClass"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username" class="text-sm font-medium"
                    >Username</Label
                >
                <Input
                    id="username"
                    type="text"
                    required
                    :tabindex="2"
                    autocomplete="username"
                    name="username"
                    placeholder="your_username"
                    :class="inputClass"
                />
                <p class="text-xs text-muted-foreground">
                    3-30 characters. Lowercase letters, numbers, dashes, and
                    underscores.
                </p>
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-sm font-medium"
                    >Email address</Label
                >
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="3"
                    autocomplete="email"
                    name="email"
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
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Create a password"
                    :passwordrules="passwordRules"
                    :class="inputClass"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation" class="text-sm font-medium"
                    >Confirm password</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Repeat your password"
                    :passwordrules="passwordRules"
                    :class="inputClass"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="h-11 w-full rounded-xl bg-chat-accent text-sm font-semibold text-white shadow-lg shadow-chat-accent/25 hover:bg-chat-accent/90"
                tabindex="6"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink :href="login()" :tabindex="7">Log in</TextLink>
        </p>
    </Form>
</template>
