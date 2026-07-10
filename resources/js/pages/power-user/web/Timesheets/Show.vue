<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const props = defineProps<{
    timesheet: {
        id: number;
        date: string;
        hours_done: number;
        user_school_role: { user: { lastname: string; firstname: string } } | null;
        schedule: { name: string; start_time: string; end_time: string } | null;
        subject: { name: string } | null;
        classroom: { name: string } | null;
    };
}>();

const title = `Feuille — ${props.timesheet.date}`;

const breadcrumbs = [
    { label: 'Feuilles de temps', href: '/timesheets' },
    { label: title },
];

function destroy() {
    if (confirm('Supprimer cette feuille de temps ?')) {
        router.delete(`/timesheets/${props.timesheet.id}`);
    }
}
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="title" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/timesheets/${timesheet.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="size-4" />{{ t('action.delete') }}
                    </Button>
                </template>
            </PageHeader>
            <Card>
                <CardHeader><CardTitle class="text-base">Détails</CardTitle></CardHeader>
                <CardContent>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Date</dt>
                            <dd>{{ timesheet.date }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Professeur</dt>
                            <dd>{{ timesheet.user_school_role ? `${timesheet.user_school_role.user.lastname} ${timesheet.user_school_role.user.firstname}` : '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Créneau</dt>
                            <dd>{{ timesheet.schedule ? `${timesheet.schedule.name} (${timesheet.schedule.start_time} – ${timesheet.schedule.end_time})` : '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Matière</dt>
                            <dd>{{ timesheet.subject?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Salle</dt>
                            <dd>{{ timesheet.classroom?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Heures effectuées</dt>
                            <dd class="font-medium">{{ timesheet.hours_done }}h</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
