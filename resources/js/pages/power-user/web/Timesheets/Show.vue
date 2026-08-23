<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
const { canManage } = useSchool();

type RosterEntry = {
    section_user_id: number;
    name: string;
    is_present: boolean;
    note: string | null;
};

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
    roster: RosterEntry[];
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

const attendanceForm = useForm({
    attendances: props.roster.map(r => ({
        section_user_id: r.section_user_id,
        is_present: r.is_present,
        note: r.note ?? '',
    })),
});

function saveAttendance() {
    attendanceForm.post(`/timesheets/${props.timesheet.id}/attendance`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="title" :breadcrumbs="breadcrumbs">
                <template v-if="canManage" #actions>
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

            <Card class="mt-4">
                <CardHeader><CardTitle class="text-base">Présences</CardTitle></CardHeader>
                <CardContent>
                    <div v-if="roster.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Aucun élève inscrit dans cette section.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="(entry, i) in attendanceForm.attendances" :key="entry.section_user_id"
                            class="flex items-center justify-between gap-3 border-b border-border pb-3 last:border-0"
                        >
                            <span class="text-sm font-medium">{{ roster[i].name }}</span>
                            <div class="flex items-center gap-2">
                                <template v-if="canManage">
                                    <Input
                                        v-if="!entry.is_present"
                                        v-model="entry.note"
                                        placeholder="Note (optionnel)"
                                        class="h-8 w-48 text-xs"
                                    />
                                    <Button
                                        :variant="entry.is_present ? 'outline' : 'destructive'"
                                        size="sm"
                                        @click="entry.is_present = !entry.is_present"
                                    >{{ entry.is_present ? 'Présent' : 'Absent' }}</Button>
                                </template>
                                <Badge v-else :variant="entry.is_present ? 'default' : 'destructive'">
                                    {{ entry.is_present ? 'Présent' : 'Absent' }}
                                </Badge>
                            </div>
                        </div>
                        <Button v-if="canManage" class="mt-2" :disabled="attendanceForm.processing" @click="saveAttendance">
                            Enregistrer les présences
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
