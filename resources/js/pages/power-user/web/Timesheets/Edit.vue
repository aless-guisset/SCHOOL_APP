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

const props = defineProps<{
    timesheet: {
        id: number;
        date: string;
        hours_done: number;
        user_school_role_id: number;
        schedule_id: number;
        subject_id: number;
        classroom_id: number;
    };
    userSchoolRoles: Array<{ id: number; label: string }>;
    schedules: Array<{ id: number; name: string; start_time: string; end_time: string }>;
    subjects: Array<{ id: number; name: string }>;
    classrooms: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    user_school_role_id: String(props.timesheet.user_school_role_id),
    schedule_id: String(props.timesheet.schedule_id),
    subject_id: String(props.timesheet.subject_id),
    classroom_id: String(props.timesheet.classroom_id),
    date: props.timesheet.date,
    hours_done: String(props.timesheet.hours_done),
});

const breadcrumbs = [
    { label: 'Feuilles de temps', href: '/timesheets' },
    { label: props.timesheet.date, href: `/timesheets/${props.timesheet.id}` },
    { label: t('action.edit') },
];
</script>

<template>
    <Head :title="`Modifier — ${timesheet.date}`" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="`Modifier : ${timesheet.date}`" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.patch(`/timesheets/${timesheet.id}`)">
                        <div class="space-y-1.5">
                            <Label>Professeur</Label>
                            <Select v-model="form.user_school_role_id">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in userSchoolRoles" :key="r.id" :value="String(r.id)">{{ r.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Créneau</Label>
                            <Select v-model="form.schedule_id">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in schedules" :key="s.id" :value="String(s.id)">
                                        {{ s.name }} ({{ s.start_time }} – {{ s.end_time }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Matière</Label>
                            <Select v-model="form.subject_id">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Salle</Label>
                            <Select v-model="form.classroom_id">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in classrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="date">Date</Label>
                                <Input id="date" v-model="form.date" type="date" :class="{ 'border-destructive': form.errors.date }" />
                                <p v-if="form.errors.date" class="text-xs text-destructive">{{ form.errors.date }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="hours">Heures effectuées</Label>
                                <Input id="hours" v-model="form.hours_done" type="number" min="0" step="0.5" />
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child>
                                <Link :href="`/timesheets/${timesheet.id}`">{{ t('action.cancel') }}</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
