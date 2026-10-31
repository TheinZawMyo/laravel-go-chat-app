<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

const inputClass =
    'h-11 rounded-xl border-transparent bg-muted/60 px-4 shadow-none transition-colors focus-visible:border-chat-accent/40 focus-visible:bg-background focus-visible:ring-chat-accent/20';
</script>

<template>
    <Head title="Security settings" />

    <div class="space-y-6">
        <section
            class="rounded-2xl border border-black/5 bg-telegram-panel p-5 shadow-sm sm:p-6 dark:border-white/10"
        >
            <Heading
                variant="small"
                title="Update password"
                description="Ensure your account is using a long, random password to stay secure"
            />

            <Form
                v-bind="SecurityController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="mt-5 space-y-5"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="current_password" class="text-sm font-medium"
                        >Current password</Label
                    >
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        placeholder="Current password"
                        :class="inputClass"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password" class="text-sm font-medium"
                        >New password</Label
                    >
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        placeholder="New password"
                        :passwordrules="props.passwordRules"
                        :class="inputClass"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="password_confirmation"
                        class="text-sm font-medium"
                        >Confirm password</Label
                    >
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        :passwordrules="props.passwordRules"
                        :class="inputClass"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div
                    class="flex items-center justify-end border-t border-black/5 pt-5 dark:border-white/10"
                >
                    <Button
                        :disabled="processing"
                        class="h-10 rounded-xl bg-chat-accent px-6 font-semibold text-white shadow-md shadow-chat-accent/20 hover:bg-chat-accent/90"
                        data-test="update-password-button"
                    >
                        Update password
                    </Button>
                </div>
            </Form>
        </section>

        <section
            v-if="canManageTwoFactor"
            class="rounded-2xl border border-black/5 bg-telegram-panel p-5 shadow-sm sm:p-6 dark:border-white/10"
        >
            <ManageTwoFactor
                :canManageTwoFactor="canManageTwoFactor"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </section>

        <section
            v-if="canManagePasskeys"
            class="rounded-2xl border border-black/5 bg-telegram-panel p-5 shadow-sm sm:p-6 dark:border-white/10"
        >
            <ManagePasskeys
                :canManagePasskeys="canManagePasskeys"
                :passkeys="passkeys"
            />
        </section>
    </div>
</template>
