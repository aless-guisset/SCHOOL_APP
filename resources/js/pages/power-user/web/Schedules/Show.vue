<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const DAYS: Record<number, string> = { 1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi', 5: 'Vendredi', 6: 'Samedi', 7: 'Dimanche' };

const props = defineProps<{
    schedule: {
        id: number;
        name: string;
        day_of_week: number;
        start_time: string;
        end_time: string;
        is_active: boolean;
        section_course: { name: string; course: { name: string } } | null;
        timesheets: Array<{ id: number; date: string; hours_done: number; user_school_role: { user: { lastname: string; firstname: string } } | null }>;
    };
}>();

const breadcrumbs = [
    { label: t('nav.schedules'), href: '/schedules' },
    { label: props.schedule.name },
];

function destroy() {
    if (confirm('Supprimer ce créneau ?')) router.delete(`/schedules/${props.schedule.id}`);
}
</script>

<template>
    <Head :title="schedule.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="schedule.name" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/schedules/${schedule.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="size-4" />{{ t('action.delete') }}
                    </Button>
                </template>
            </PageHeader>

            <Card>
                <CardHeader><CardTitle class="text-base">Informations</CardTitle></CardHeader>
                <CardContent>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.status') }}</dt>
                            <dd><Badge :variant="schedule.is_active ? 'default' : 'secondary'">{{ schedule.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Jour</dt>
                            <dd>{{ DAYS[schedule.day_of_week] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Horaire</dt>
                            <dd>{{ schedule.start_time.substring(0, 5) }} – {{ schedule.end_time.substring(0, 5) }}</dd>
                        </div>
                        <div v-if="schedule.section_course" class="flex justify-between">
                            <dt class="text-muted-foreground">Section/Cours</dt>
                            <dd>{{ schedule.section_course.name }} — {{ schedule.section_course.course?.name }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="schedule.timesheets?.length" class="mt-4">
                <CardHeader><CardTitle class="text-base">Feuilles de temps ({{ schedule.timesheets.length }})</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-1 text-sm">
                        <li v-for="ts in schedule.timesheets" :key="ts.id" class="flex items-center justify-between">
                            <Link :href="`/timesheets/${ts.id}`" class="hover:underline">{{ ts.date }}</Link>
                            <span class="text-muted-foreground">
                                {{ ts.user_school_role?.user.lastname }} {{ ts.user_school_role?.user.firstname }}
                                · {{ ts.hours_done }}h
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
