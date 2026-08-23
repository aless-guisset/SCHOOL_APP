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

const DAYS = [
    { value: '1', label: 'Lundi' }, { value: '2', label: 'Mardi' },
    { value: '3', label: 'Mercredi' }, { value: '4', label: 'Jeudi' },
    { value: '5', label: 'Vendredi' }, { value: '6', label: 'Samedi' },
    { value: '7', label: 'Dimanche' },
];

defineProps<{
    sectionCourses: Array<{ id: number; name: string; course: { name: string } | null }>;
    userSchoolRoles: Array<{ id: number; label: string }>;
    subjects: Array<{ id: number; name: string }>;
    classrooms: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    section_course_id: '',
    name: '',
    day_of_week: '',
    start_time: '',
    end_time: '',
    user_school_role_id: '',
    subject_id: '',
    classroom_id: '',
});

const breadcrumbs = [
    { label: t('nav.schedules'), href: '/schedules' },
    { label: t('action.create') },
];
</script>

<template>
    <Head title="Nouveau créneau" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Nouveau créneau" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/schedules')">
                        <div class="space-y-1.5">
                            <Label>Section/Cours *</Label>
                            <Select v-model="form.section_course_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.section_course_id }">
                                    <SelectValue placeholder="Sélectionner" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sc in sectionCourses" :key="sc.id" :value="String(sc.id)">
                                        {{ sc.name }} <span v-if="sc.course" class="text-muted-foreground">— {{ sc.course.name }}</span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.section_course_id" class="text-xs text-destructive">{{ form.errors.section_course_id }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="name">{{ t('label.name') }} *</Label>
                            <Input id="name" v-model="form.name" placeholder="ex : Maths S1" :class="{ 'border-destructive': form.errors.name }" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Jour *</Label>
                            <Select v-model="form.day_of_week">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.day_of_week }">
                                    <SelectValue placeholder="Jour de la semaine" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="d in DAYS" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.day_of_week" class="text-xs text-destructive">{{ form.errors.day_of_week }}</p>
                        </div>

                        <div class="rounded-md border border-border p-3 text-xs text-muted-foreground">
                            Optionnel : si prof/salle/matière sont définis, les séances de ce
                            créneau sont générées et resynchronisées automatiquement jusqu'à la
                            fin de l'année scolaire de l'école.
                        </div>

                        <div class="space-y-1.5">
                            <Label>Professeur</Label>
                            <Select v-model="form.user_school_role_id">
                                <SelectTrigger><SelectValue placeholder="Aucun (à compléter plus tard)" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in userSchoolRoles" :key="r.id" :value="String(r.id)">{{ r.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.user_school_role_id" class="text-xs text-destructive">{{ form.errors.user_school_role_id }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Salle</Label>
                            <Select v-model="form.classroom_id">
                                <SelectTrigger><SelectValue placeholder="Aucune (à compléter plus tard)" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in classrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.classroom_id" class="text-xs text-destructive">{{ form.errors.classroom_id }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Matière</Label>
                            <Select v-model="form.subject_id">
                                <SelectTrigger><SelectValue placeholder="Aucune (à compléter plus tard)" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.subject_id" class="text-xs text-destructive">{{ form.errors.subject_id }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="start">{{ t('label.start_time') }} *</Label>
                                <Input id="start" v-model="form.start_time" type="time" :class="{ 'border-destructive': form.errors.start_time }" />
                                <p v-if="form.errors.start_time" class="text-xs text-destructive">{{ form.errors.start_time }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="end">{{ t('label.end_time') }} *</Label>
                                <Input id="end" v-model="form.end_time" type="time" :class="{ 'border-destructive': form.errors.end_time }" />
                                <p v-if="form.errors.end_time" class="text-xs text-destructive">{{ form.errors.end_time }}</p>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child><Link href="/schedules">{{ t('action.cancel') }}</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
