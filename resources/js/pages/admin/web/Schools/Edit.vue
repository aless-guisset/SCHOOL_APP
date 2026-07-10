<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const props = defineProps<{
    school: { id: number; name: string; email: string | null; phone_number: string | null; address: string | null; description: string | null };
}>();

const form = useForm({
    name: props.school.name,
    email: props.school.email ?? '',
    phone_number: props.school.phone_number ?? '',
    address: props.school.address ?? '',
    description: props.school.description ?? '',
});

const breadcrumbs = [
    { label: t('nav.schools'), href: '/schools' },
    { label: props.school.name, href: `/schools/${props.school.id}` },
    { label: t('action.edit') },
];
</script>

<template>
    <Head :title="`Modifier — ${school.name}`" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="`Modifier : ${school.name}`" :breadcrumbs="breadcrumbs" />

            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.patch(`/schools/${school.id}`)">
                        <div class="space-y-1.5">
                            <Label for="name">{{ t('label.name') }} *</Label>
                            <Input id="name" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="email">{{ t('label.email') }}</Label>
                                <Input id="email" v-model="form.email" type="email" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="phone">{{ t('label.phone') }}</Label>
                                <Input id="phone" v-model="form.phone_number" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="address">{{ t('label.address') }}</Label>
                            <Input id="address" v-model="form.address" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="description">{{ t('label.description') }}</Label>
                            <Textarea id="description" v-model="form.description" rows="3" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child>
                                <Link :href="`/schools/${school.id}`">{{ t('action.cancel') }}</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
