<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
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

type PresenceStatus = 'P' | 'A' | 'R';
type JustificationStatus = 'J' | 'I' | null;

type RosterEntry = {
    section_user_id: number;
    name: string;
    presence_status: PresenceStatus;
    justification_status: JustificationStatus;
    note: string | null;
    has_active_certificate: boolean;
};

const props = defineProps<{
    timesheet: {
        id: number;
        date: string;
        hours_done: number;
        attendance_submitted_at: string | null;
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

const PRESENCE_LABELS: Record<PresenceStatus, string> = { P: 'Présent', A: 'Absent', R: 'Retard' };

const attendanceForm = useForm({
    attendances: props.roster.map(r => ({
        section_user_id: r.section_user_id,
        presence_status: r.presence_status,
        justification_status: r.justification_status,
        note: r.note ?? '',
    })),
});

// En cyclant P → A → R → P, on repart toujours d'Injustifié par défaut pour
// un nouvel état Absent/Retard — cohérent avec le défaut serveur.
function cyclePresence(entry: (typeof attendanceForm.attendances)[number]) {
    const next: Record<PresenceStatus, PresenceStatus> = { P: 'A', A: 'R', R: 'P' };
    entry.presence_status = next[entry.presence_status];
    entry.justification_status = entry.presence_status === 'P' ? null : 'I';
    if (entry.presence_status === 'P') entry.note = '';
}

function saveAttendance() {
    attendanceForm.post(`/timesheets/${props.timesheet.id}/attendance`, { preserveScroll: true });
}

// Un cours qui n'a pas encore eu lieu ne peut pas avoir ses présences prises
// à l'avance — le backend (AttendancesController::store()) refuse déjà la
// requête, ceci évite en plus d'afficher des contrôles inutilisables.
const isFutureSession = computed(() => new Date(`${props.timesheet.date}T00:00:00`) > new Date(new Date().toDateString()));
// Verrou définitif dès la première soumission — le backend refuse déjà toute
// resoumission, ceci évite en plus d'afficher des contrôles inutilisables.
const isLocked = computed(() => props.timesheet.attendance_submitted_at !== null);
const canEditAttendance = computed(() => canManage.value && !isFutureSession.value && !isLocked.value);

const submittedAtLabel = computed(() => {
    if (!props.timesheet.attendance_submitted_at) return '';
    return new Date(props.timesheet.attendance_submitted_at).toLocaleString('fr-FR', {
        day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
});

const PRESENCE_VARIANT: Record<PresenceStatus, 'outline' | 'destructive' | 'secondary'> = {
    P: 'outline', A: 'destructive', R: 'secondary',
};
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
                    <div v-else-if="isFutureSession" class="py-6 text-center text-sm text-muted-foreground">
                        Ce cours n'a pas encore eu lieu — les présences pourront être prises à partir du {{ timesheet.date }}.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-if="isLocked"
                            class="rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                        >
                            Présences déjà soumises le {{ submittedAtLabel }} — elles ne peuvent plus être modifiées.
                        </div>
                        <div
                            v-for="(entry, i) in attendanceForm.attendances" :key="entry.section_user_id"
                            class="flex items-center justify-between gap-3 border-b border-border pb-3 last:border-0"
                        >
                            <span class="text-sm font-medium">{{ roster[i].name }}</span>
                            <div class="flex items-center gap-2">
                                <template v-if="canEditAttendance">
                                    <Badge v-if="entry.presence_status !== 'P' && roster[i].has_active_certificate" variant="outline">
                                        Justifié (certificat)
                                    </Badge>
                                    <template v-else-if="entry.presence_status !== 'P'">
                                        <select
                                            v-model="entry.justification_status"
                                            class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                        >
                                            <option value="I">Injustifié</option>
                                            <option value="J">Justifié</option>
                                        </select>
                                        <Input
                                            v-if="entry.justification_status === 'J'"
                                            v-model="entry.note"
                                            placeholder="Note (optionnel)"
                                            class="h-8 w-40 text-xs"
                                        />
                                    </template>
                                    <Button
                                        :variant="PRESENCE_VARIANT[entry.presence_status]"
                                        size="sm"
                                        @click="cyclePresence(entry)"
                                    >{{ PRESENCE_LABELS[entry.presence_status] }}</Button>
                                </template>
                                <template v-else>
                                    <Badge :variant="PRESENCE_VARIANT[entry.presence_status]">
                                        {{ PRESENCE_LABELS[entry.presence_status] }}
                                    </Badge>
                                    <Badge v-if="entry.presence_status !== 'P'" variant="outline">
                                        {{ entry.justification_status === 'J' ? 'Justifié' : 'Injustifié' }}
                                    </Badge>
                                </template>
                            </div>
                        </div>
                        <Button v-if="canEditAttendance" class="mt-2" :disabled="attendanceForm.processing" @click="saveAttendance">
                            Enregistrer les présences
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
