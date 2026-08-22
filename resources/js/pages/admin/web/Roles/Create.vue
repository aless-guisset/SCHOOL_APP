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

const form = useForm({ name: '', description: '' });
const breadcrumbs = [
    { label: t('nav.roles'), href: '/roles' },
    { label: t('action.create') },
];
</script>

<template>
    <Head title="Nouveau rôle" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Nouveau rôle" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/roles')">
                        <div class="space-y-1.5">
                            <Label for="name">{{ t('label.name') }} *</Label>
                            <Input id="name" v-model="form.name" placeholder="ex : Professeur" :class="{ 'border-destructive': form.errors.name }" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="description">{{ t('label.description') }}</Label>
                            <Textarea id="description" v-model="form.description" rows="2" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child><Link href="/roles">{{ t('action.cancel') }}</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
