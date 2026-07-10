<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const form = useForm({
    firstname: '', lastname: '', email: '', password: '', phone_number: '',
});

const breadcrumbs = [
    { label: t('nav.users'), href: '/users' },
    { label: t('action.create') },
];
</script>

<template>
    <Head title="Nouvel utilisateur" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Nouvel utilisateur" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/users')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="lastname">{{ t('label.lastname') }}</Label>
                                <Input id="lastname" v-model="form.lastname" />
                                <p v-if="form.errors.lastname" class="text-xs text-destructive">{{ form.errors.lastname }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="firstname">{{ t('label.firstname') }}</Label>
                                <Input id="firstname" v-model="form.firstname" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="email">{{ t('label.email') }} *</Label>
                            <Input id="email" v-model="form.email" type="email" :class="{ 'border-destructive': form.errors.email }" />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="password">{{ t('label.password') }} *</Label>
                            <Input id="password" v-model="form.password" type="password" :class="{ 'border-destructive': form.errors.password }" />
                            <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="phone">{{ t('label.phone') }}</Label>
                            <Input id="phone" v-model="form.phone_number" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child>
                                <Link href="/users">{{ t('action.cancel') }}</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
