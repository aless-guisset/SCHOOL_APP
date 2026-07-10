<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

defineProps<{
    userSchoolRoles: Array<{ id: number; label: string }>;
    schedules: Array<{ id: number; name: string; start_time: string; end_time: string }>;
    subjects: Array<{ id: number; name: string }>;
    classrooms: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    user_school_role_id: '',
    schedule_id: '',
    subject_id: '',
    classroom_id: '',
    date: '',
    hours_done: '',
});

const breadcrumbs = [
    { label: 'Feuilles de temps', href: '/timesheets' },
    { label: t('action.create') },
];
</script>

<template>
    <Head title="Nouvelle feuille de temps" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Nouvelle feuille de temps" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/timesheets')">
                        <div class="space-y-1.5">
                            <Label>Professeur *</Label>
                            <Select v-model="form.user_school_role_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.user_school_role_id }">
                                    <SelectValue placeholder="Sélectionner un professeur" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in userSchoolRoles" :key="r.id" :value="String(r.id)">{{ r.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.user_school_role_id" class="text-xs text-destructive">{{ form.errors.user_school_role_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Créneau *</Label>
                            <Select v-model="form.schedule_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.schedule_id }">
                                    <SelectValue placeholder="Sélectionner un créneau" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in schedules" :key="s.id" :value="String(s.id)">
                                        {{ s.name }} ({{ s.start_time }} – {{ s.end_time }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.schedule_id" class="text-xs text-destructive">{{ form.errors.schedule_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Matière *</Label>
                            <Select v-model="form.subject_id">
                                <SelectTrigger><SelectValue placeholder="Sélectionner une matière" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.subject_id" class="text-xs text-destructive">{{ form.errors.subject_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Salle *</Label>
                            <Select v-model="form.classroom_id">
                                <SelectTrigger><SelectValue placeholder="Sélectionner une salle" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in classrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.classroom_id" class="text-xs text-destructive">{{ form.errors.classroom_id }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="date">Date *</Label>
                                <Input id="date" v-model="form.date" type="date" :class="{ 'border-destructive': form.errors.date }" />
                                <p v-if="form.errors.date" class="text-xs text-destructive">{{ form.errors.date }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="hours">Heures effectuées *</Label>
                                <Input id="hours" v-model="form.hours_done" type="number" min="0" step="0.5" />
                                <p v-if="form.errors.hours_done" class="text-xs text-destructive">{{ form.errors.hours_done }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child><Link href="/timesheets">{{ t('action.cancel') }}</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
