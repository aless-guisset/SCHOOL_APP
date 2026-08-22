<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

defineProps<{
    users: Array<{ id: number; firstname: string; lastname: string; email: string }>;
    roles: Array<{ id: number; name: string }>;
    schoolId: number | null;
}>();

const form = useForm({ user_id: '', role_id: '' });
const breadcrumbs = [
    { label: 'Assignations rôles', href: '/user-school-roles' },
    { label: t('action.create') },
];
</script>

<template>
    <Head title="Nouvelle assignation" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Nouvelle assignation" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/user-school-roles')">
                        <div class="space-y-1.5">
                            <Label>Utilisateur *</Label>
                            <Select v-model="form.user_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.user_id }">
                                    <SelectValue placeholder="Sélectionner un utilisateur" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="u in users" :key="u.id" :value="String(u.id)">
                                        {{ u.firstname }} {{ u.lastname }} ({{ u.email }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.user_id" class="text-xs text-destructive">{{ form.errors.user_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>{{ t('nav.roles') }} *</Label>
                            <Select v-model="form.role_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.role_id }">
                                    <SelectValue placeholder="Sélectionner un rôle" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.role_id" class="text-xs text-destructive">{{ form.errors.role_id }}</p>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child><Link href="/user-school-roles">{{ t('action.cancel') }}</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
