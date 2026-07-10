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

const props = defineProps<{
    user: { id: number; firstname: string | null; lastname: string | null; email: string; phone_number: string | null };
}>();

const form = useForm({
    firstname: props.user.firstname ?? '',
    lastname: props.user.lastname ?? '',
    email: props.user.email,
    password: '',
    phone_number: props.user.phone_number ?? '',
});

const fullName = `${props.user.lastname ?? ''} ${props.user.firstname ?? ''}`.trim() || props.user.email;

const breadcrumbs = [
    { label: t('nav.users'), href: '/users' },
    { label: fullName, href: `/users/${props.user.id}` },
    { label: t('action.edit') },
];
</script>

<template>
    <Head :title="`Modifier — ${fullName}`" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="`Modifier : ${fullName}`" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.patch(`/users/${user.id}`)">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="lastname">{{ t('label.lastname') }}</Label>
                                <Input id="lastname" v-model="form.lastname" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="firstname">{{ t('label.firstname') }}</Label>
                                <Input id="firstname" v-model="form.firstname" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="email">{{ t('label.email') }}</Label>
                            <Input id="email" v-model="form.email" type="email" :class="{ 'border-destructive': form.errors.email }" />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="password">{{ t('label.password') }} <span class="text-xs text-muted-foreground">(laisser vide pour ne pas changer)</span></Label>
                            <Input id="password" v-model="form.password" type="password" />
                            <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="phone">{{ t('label.phone') }}</Label>
                            <Input id="phone" v-model="form.phone_number" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child>
                                <Link :href="`/users/${user.id}`">{{ t('action.cancel') }}</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
