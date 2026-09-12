<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import JoinAuthLayout from '@/layouts/JoinAuthLayout.vue';
import { useTranslation } from '@/composables/useTranslation';

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth.user);
const { t } = useTranslation();
</script>

<template>
    <Head :title="t('parent.join_head_title')" />
    <JoinAuthLayout :badge="t('parent.join_badge')" :title="t('parent.join_title')" :description="t('parent.join_desc')">
        <Form
            action="/join/parent" method="post"
            :reset-on-success="['access_code']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <template v-if="!isAuthenticated">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="firstname">{{ t('label.firstname') }}</Label>
                        <Input id="firstname" name="firstname" type="text" required autofocus autocomplete="given-name" />
                        <InputError :message="errors.firstname" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lastname">{{ t('label.lastname') }}</Label>
                        <Input id="lastname" name="lastname" type="text" required autocomplete="family-name" />
                        <InputError :message="errors.lastname" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('parent.field_email') }}</Label>
                    <Input id="email" name="email" type="email" required autocomplete="email" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('label.password') }}</Label>
                    <PasswordInput id="password" name="password" required autocomplete="new-password" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ t('parent.confirm_password') }}</Label>
                    <PasswordInput id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </template>

            <div class="grid gap-2">
                <Label for="access_code">{{ t('parent.student_code_label') }}</Label>
                <Input
                    id="access_code" name="access_code" type="text" required placeholder="ABCD1234" class="uppercase"
                    :autofocus="isAuthenticated"
                />
                <InputError :message="errors.access_code" />
            </div>

            <Button type="submit" :disabled="processing" class="w-full">{{ t('action.join') }}</Button>
        </Form>
    </JoinAuthLayout>
</template>
