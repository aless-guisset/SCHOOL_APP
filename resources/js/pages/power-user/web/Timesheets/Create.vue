<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const DAY_LABELS = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

type Schedule = {
    id: number; name: string; day_of_week: number; start_time: string; end_time: string;
    section_id: number | null; section_name: string | null;
};

const props = defineProps<{
    userSchoolRoles: Array<{ id: number; label: string }>;
    schedules: Schedule[];
    sections: Array<{ id: number; name: string }>;
    subjects: Array<{ id: number; name: string }>;
    classrooms: Array<{ id: number; name: string }>;
}>();

// ── Filtre par classe (purement côté client, ne change pas le schedule choisi) ─
const sectionFilter = ref('all');
const filteredSchedules = computed(() =>
    sectionFilter.value === 'all'
        ? props.schedules
        : props.schedules.filter(s => String(s.section_id) === sectionFilter.value)
);

// ── État wizard ───────────────────────────────────────────────────────────────
const step = ref(1);
const STEPS = ['Créneau', 'Date', 'Affectation', 'Confirmation'];

const form = useForm({
    schedule_id:          '',
    date:                 '',
    user_school_role_id:  '',
    classroom_id:         '',
    subject_id:           '',
    hours_done:           '',
    replace_conflict_ids: [] as number[],
});

// ── Étape 1 : schedule sélectionné ──────────────────────────────────────────
const selectedSchedule = computed<Schedule | undefined>(() =>
    props.schedules.find(s => String(s.id) === form.schedule_id)
);

// ── Étape 2 : la date est contrainte au jour du créneau choisi ─────────────────
// Prochaine occurrence (aujourd'hui ou après) d'un jour de semaine ISO donné,
// en manipulant la date entièrement en UTC pour rester cohérent quel que soit
// le fuseau du navigateur (même précaution que dateWarning ci-dessous).
function nextOccurrence(dayOfWeek: number): string {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const cursor = new Date(`${y}-${m}-${d}T00:00:00Z`);
    const currentDow = cursor.getUTCDay() === 0 ? 7 : cursor.getUTCDay();
    cursor.setUTCDate(cursor.getUTCDate() + ((dayOfWeek - currentDow + 7) % 7));
    return cursor.toISOString().slice(0, 10);
}

// Pré-remplit la date sur la prochaine occurrence dès qu'un créneau est choisi
// (ou changé), pour que l'utilisateur n'ait jamais à deviner le bon jour.
watch(selectedSchedule, (schedule) => {
    if (schedule) {
        form.date = nextOccurrence(schedule.day_of_week);
    }
});

// Décale la date de ±7 jours (garde le même jour de semaine) — boutons dans
// le template, plus fiable d'un navigateur à l'autre que `step` sur un input
// date natif (le support de `step` pour restreindre le sélecteur au clic
// varie trop selon les navigateurs pour être utilisable seul).
function shiftDate(days: number) {
    if (!form.date) return;
    const d = new Date(`${form.date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    form.date = d.toISOString().slice(0, 10);
}

// Libellé lisible de la date choisie (ex: "Lundi 24 août 2026").
const formattedDate = computed(() => {
    if (!form.date) return '';
    const d = new Date(`${form.date}T00:00:00Z`);
    return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
});

// ── Étape 2 : validation date vs jour du schedule ─────────────────────────────
// Filet de sécurité : reste utile si une date invalide passe malgré tout
// (saisie manuelle du texte, navigateur qui ne respecte pas `step`).
const dateWarning = computed(() => {
    if (!form.date || !selectedSchedule.value) return null;
    // form.date is a date-only 'YYYY-MM-DD' string, parsed by `new Date()` as UTC midnight —
    // read the weekday with getUTCDay() (not getDay()) so the result doesn't shift with the
    // browser's local timezone.
    const d = new Date(form.date);
    const day = d.getUTCDay();
    const dow = day === 0 ? 7 : day; // ISO
    if (dow !== selectedSchedule.value.day_of_week) {
        return `Ce créneau est prévu le ${DAY_LABELS[selectedSchedule.value.day_of_week]}, mais vous avez sélectionné un ${DAY_LABELS[dow]}.`;
    }
    return null;
});

// ── Étape 3 : check conflits avant confirmation ────────────────────────────────
type RawConflict = { id: number; type: 'teacher' | 'classroom' | 'section'; message: string };
type Conflict = { id: number; messages: string[] };
const conflicts = ref<Conflict[]>([]);

// Détails de la séance déjà existante pour chaque conflit — permet d'afficher
// "ancien cours" vs "nouveau cours" dans la modale de confirmation finale.
type ConflictDetail = { date: string; teacher: string; classroom: string; subject: string; hours_done: number };
const conflictDetails = ref<Record<number, ConflictDetail>>({});

// Un même Timesheet peut déclencher plusieurs types de conflit à la fois
// (ex: le prof ET la section sont déjà occupés par la même séance) — le
// serveur renvoie alors plusieurs entrées avec le même id. On les regroupe
// ici pour n'afficher qu'UNE case à cocher par séance réellement en conflit,
// sinon cocher l'une cochait visuellement les deux (même id, même état).
function dedupeConflicts(raw: RawConflict[]): Conflict[] {
    const byId = new Map<number, string[]>();
    for (const c of raw) {
        (byId.get(c.id) ?? byId.set(c.id, []).get(c.id)!).push(c.message);
    }
    return Array.from(byId, ([id, messages]) => ({ id, messages }));
}
const checkingConflicts = ref(false);
const conflictCheckFailed = ref(false);

async function checkConflicts() {
    if (!form.schedule_id || !form.date || !form.user_school_role_id || !form.classroom_id) return;
    checkingConflicts.value = true;
    conflicts.value = [];
    conflictDetails.value = {};
    conflictCheckFailed.value = false;
    try {
        const params = new URLSearchParams({
            schedule_id:          form.schedule_id,
            date:                 form.date,
            user_school_role_id:  form.user_school_role_id,
            classroom_id:         form.classroom_id,
        });
        const res = await fetch(`/timesheets/check-conflict?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        conflicts.value = dedupeConflicts(json.conflicts ?? []);
        conflictDetails.value = json.conflict_details ?? {};
    } catch {
        // Pre-check couldn't run (network error, unparseable response, etc.) — this is not
        // the same as an actual conflict being found. Don't block the wizard: the server
        // revalidates via NoTimesheetConflict on submit regardless. Just surface it so the
        // user isn't left wondering why nothing happened.
        conflictCheckFailed.value = true;
    } finally {
        checkingConflicts.value = false;
    }
}

// ── Navigation étapes ────────────────────────────────────────────────────────
const canGoNext = computed(() => {
    if (step.value === 1) return !!form.schedule_id;
    if (step.value === 2) return !!form.date;
    if (step.value === 3) return !!form.user_school_role_id && !!form.classroom_id && !!form.subject_id && !!form.hours_done;
    return true;
});

async function next() {
    form.clearErrors();
    if (step.value === 3) await checkConflicts();
    step.value++;
}
function back() { step.value--; }

// ── Soumission finale ────────────────────────────────────────────────────────
// Maps a rejected field back to the wizard step where it's edited, so a user
// whose submit is rejected on step 4 (e.g. a server-side conflict on `date`)
// lands back where they can actually fix it instead of staring at a frozen screen.
const FIELD_STEP: Record<string, number> = {
    schedule_id: 1,
    date: 2,
    user_school_role_id: 3,
    classroom_id: 3,
    subject_id: 3,
    hours_done: 3,
};

function submit() {
    form.post('/timesheets', {
        onError: (errors) => {
            const firstField = Object.keys(errors)[0];
            const targetStep = firstField ? FIELD_STEP[firstField] : undefined;
            if (targetStep) step.value = targetStep;
        },
    });
}

// Clic sur "Enregistrer" : si un conflit existe, on ne soumet pas tout de
// suite — une modale récapitule l'ancien cours et le nouveau côte à côte et
// demande de choisir explicitement lequel garder (plus de case à cocher :
// un choix binaire, sans ambiguïté possible).
const showConfirmDialog = ref(false);
function attemptSubmit() {
    if (conflicts.value.length > 0) {
        showConfirmDialog.value = true;
    } else {
        submit();
    }
}
// "Garder l'ancien" : on referme simplement la modale, rien n'est envoyé —
// le nouveau cours n'est jamais créé, l'ancien reste inchangé.
function keepOld() {
    showConfirmDialog.value = false;
}
// "Garder le nouveau" : tous les cours en conflit détectés sont marqués à
// remplacer, puis on soumet — le serveur les supprime et crée le nouveau.
function keepNew() {
    form.replace_conflict_ids = conflicts.value.map(c => c.id);
    showConfirmDialog.value = false;
    submit();
}

// ── Résumé étape 4 ───────────────────────────────────────────────────────────
const summary = computed(() => ({
    schedule: selectedSchedule.value
        ? `${selectedSchedule.value.name} (${DAY_LABELS[selectedSchedule.value.day_of_week]}, ${selectedSchedule.value.start_time.slice(0,5)}–${selectedSchedule.value.end_time.slice(0,5)})`
        : '—',
    date: form.date,
    teacher: props.userSchoolRoles.find(r => String(r.id) === form.user_school_role_id)?.label ?? '—',
    classroom: props.classrooms.find(c => String(c.id) === form.classroom_id)?.name ?? '—',
    subject: props.subjects.find(s => String(s.id) === form.subject_id)?.name ?? '—',
    hours: form.hours_done,
}));

const breadcrumbs = [
    { label: 'Feuilles de temps', href: '/timesheets' },
    { label: t('action.create') },
];
</script>

<template>
    <Head title="Nouvelle feuille de temps" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader title="Nouvelle feuille de temps" :breadcrumbs="breadcrumbs" />

            <!-- Indicateur étapes -->
            <div class="mb-6 flex items-center gap-2">
                <template v-for="(label, i) in STEPS" :key="i">
                    <div class="flex items-center gap-1.5">
                        <div
                            class="flex size-6 items-center justify-center rounded-full text-xs font-semibold"
                            :class="step > i + 1
                                ? 'bg-primary text-primary-foreground'
                                : step === i + 1
                                    ? 'border-2 border-primary text-primary'
                                    : 'border border-muted-foreground/30 text-muted-foreground'"
                        >
                            <CheckCircle2 v-if="step > i + 1" class="size-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="hidden text-xs sm:block"
                            :class="step === i + 1 ? 'font-semibold text-foreground' : 'text-muted-foreground'"
                        >{{ label }}</span>
                    </div>
                    <div v-if="i < STEPS.length - 1" class="h-px flex-1 bg-border" />
                </template>
            </div>

            <Card>
                <CardContent class="pt-6">

                    <!-- Étape 1 : Créneau -->
                    <div v-if="step === 1" class="space-y-4">
                        <h2 class="font-semibold">Sélectionner un créneau</h2>
                        <div v-if="sections.length" class="space-y-1.5">
                            <Label>Filtrer par classe</Label>
                            <Select v-model="sectionFilter">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Toutes les classes</SelectItem>
                                    <SelectItem v-for="sec in sections" :key="sec.id" :value="String(sec.id)">{{ sec.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Créneau *</Label>
                            <Select v-model="form.schedule_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Choisir un créneau" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in filteredSchedules" :key="s.id" :value="String(s.id)">
                                        {{ DAY_LABELS[s.day_of_week] }} · {{ s.start_time.slice(0,5) }}–{{ s.end_time.slice(0,5) }} · {{ s.name }}
                                        <span v-if="s.section_name" class="text-muted-foreground">— {{ s.section_name }}</span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="!filteredSchedules.length" class="text-xs text-muted-foreground">Aucun créneau pour cette classe.</p>
                        </div>
                        <div v-if="selectedSchedule" class="rounded-md bg-muted/40 p-3 text-sm">
                            <p><span class="font-medium">Jour :</span> {{ DAY_LABELS[selectedSchedule.day_of_week] }}</p>
                            <p><span class="font-medium">Horaire :</span> {{ selectedSchedule.start_time.slice(0,5) }} – {{ selectedSchedule.end_time.slice(0,5) }}</p>
                        </div>
                    </div>

                    <!-- Étape 2 : Date -->
                    <div v-else-if="step === 2" class="space-y-4">
                        <h2 class="font-semibold">Choisir la date</h2>
                        <div v-if="selectedSchedule" class="rounded-md bg-muted/40 p-3 text-sm">
                            <span class="text-muted-foreground">Jour du créneau :</span>
                            <span class="ml-1 font-medium">{{ DAY_LABELS[selectedSchedule.day_of_week] }}</span>
                        </div>

                        <!-- Navigation par semaine : garde toujours le bon jour de semaine -->
                        <div class="flex items-center gap-2">
                            <Button type="button" variant="outline" size="icon" :disabled="!form.date" @click="shiftDate(-7)">
                                <ChevronLeft class="size-4" />
                            </Button>
                            <div class="flex-1 rounded-md border border-border px-3 py-2 text-center text-sm font-medium capitalize">
                                {{ formattedDate || 'Sélectionnez un créneau d\'abord' }}
                            </div>
                            <Button type="button" variant="outline" size="icon" :disabled="!form.date" @click="shiftDate(7)">
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="date">Ou choisir une date précise *</Label>
                            <Input id="date" v-model="form.date" type="date" :class="{ 'border-destructive': form.errors.date }" />
                            <p v-if="form.errors.date" class="text-xs text-destructive">{{ form.errors.date }}</p>
                        </div>
                        <div v-if="dateWarning" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                            ⚠ {{ dateWarning }}
                        </div>
                    </div>

                    <!-- Étape 3 : Affectation -->
                    <div v-else-if="step === 3" class="space-y-4">
                        <h2 class="font-semibold">Affectation</h2>
                        <div class="space-y-1.5">
                            <Label>Professeur *</Label>
                            <Select v-model="form.user_school_role_id">
                                <SelectTrigger><SelectValue placeholder="Sélectionner un professeur" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in userSchoolRoles" :key="r.id" :value="String(r.id)">{{ r.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.user_school_role_id" class="text-xs text-destructive">{{ form.errors.user_school_role_id }}</p>
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
                            <Label for="hours">Heures effectuées *</Label>
                            <Input id="hours" v-model="form.hours_done" type="number" min="0" step="0.5" />
                            <p v-if="form.errors.hours_done" class="text-xs text-destructive">{{ form.errors.hours_done }}</p>
                        </div>
                    </div>

                    <!-- Étape 4 : Confirmation -->
                    <div v-else class="space-y-4">
                        <h2 class="font-semibold">Confirmation</h2>

                        <!-- Échec de la vérification des conflits (distinct d'un conflit réel) -->
                        <div v-if="conflictCheckFailed" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                            ⚠ La vérification des conflits n'a pas pu être effectuée (erreur réseau). Vous pouvez quand même enregistrer ; le serveur revalidera.
                        </div>

                        <!-- Alerte conflit : juste informative, le choix se fait à la validation -->
                        <div v-if="conflicts.length" class="space-y-1 rounded-md border border-destructive bg-destructive/10 p-3 text-sm">
                            <p class="font-medium text-destructive">Un cours existe déjà sur ce créneau.</p>
                            <p class="text-xs text-muted-foreground">
                                Vous choisirez lequel garder au moment de valider.
                            </p>
                        </div>

                        <!-- Erreurs de validation serveur (ex: rejet au submit) -->
                        <div v-if="Object.keys(form.errors).length" class="rounded-md border border-destructive bg-destructive/10 p-3">
                            <p class="mb-1 text-sm font-medium text-destructive">Erreur lors de l'enregistrement :</p>
                            <ul class="list-disc pl-4 text-sm text-destructive">
                                <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                            </ul>
                        </div>

                        <div class="rounded-md bg-muted/40 p-4 text-sm space-y-2">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                                <span class="text-muted-foreground">Créneau</span>
                                <span class="font-medium">{{ summary.schedule }}</span>
                                <span class="text-muted-foreground">Date</span>
                                <span class="font-medium">{{ summary.date }}</span>
                                <span class="text-muted-foreground">Professeur</span>
                                <span class="font-medium">{{ summary.teacher }}</span>
                                <span class="text-muted-foreground">Salle</span>
                                <span class="font-medium">{{ summary.classroom }}</span>
                                <span class="text-muted-foreground">Matière</span>
                                <span class="font-medium">{{ summary.subject }}</span>
                                <span class="text-muted-foreground">Heures</span>
                                <span class="font-medium">{{ summary.hours }}h</span>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation boutons -->
                    <div class="mt-6 flex gap-3">
                        <Button v-if="step > 1" variant="outline" @click="back">
                            <ChevronLeft class="size-4" />Retour
                        </Button>
                        <div class="flex-1" />
                        <Button
                            v-if="step < 4"
                            :disabled="!canGoNext || checkingConflicts"
                            @click="next"
                        >
                            {{ checkingConflicts ? 'Vérification…' : 'Suivant' }}<ChevronRight class="size-4" />
                        </Button>
                        <Button
                            v-else
                            :disabled="form.processing"
                            @click="attemptSubmit"
                        >
                            {{ t('action.save') }}
                        </Button>
                        <Button variant="outline" as-child>
                            <Link href="/timesheets">{{ t('action.cancel') }}</Link>
                        </Button>
                    </div>

                </CardContent>
            </Card>

            <!-- Confirmation finale en cas de conflit : ancien cours vs nouveau -->
            <Dialog v-model:open="showConfirmDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Un cours existe déjà sur ce créneau</DialogTitle>
                        <DialogDescription>
                            Choisissez lequel garder — l'autre sera retiré.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4">
                        <div v-for="c in conflicts" :key="c.id" class="grid grid-cols-2 gap-3 text-sm">
                            <div class="space-y-1 rounded bg-muted/40 p-2">
                                <p class="text-xs font-semibold text-muted-foreground">Ancien cours</p>
                                <template v-if="conflictDetails[c.id]">
                                    <p>{{ conflictDetails[c.id].teacher }}</p>
                                    <p>{{ conflictDetails[c.id].classroom }} — {{ conflictDetails[c.id].subject }}</p>
                                    <p>{{ conflictDetails[c.id].date }} · {{ conflictDetails[c.id].hours_done }}h</p>
                                </template>
                            </div>
                            <div class="space-y-1 rounded bg-primary/10 p-2">
                                <p class="text-xs font-semibold text-muted-foreground">Nouveau cours</p>
                                <p>{{ summary.teacher }}</p>
                                <p>{{ summary.classroom }} — {{ summary.subject }}</p>
                                <p>{{ summary.date }} · {{ summary.hours }}h</p>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" :disabled="form.processing" @click="keepOld">Garder l'ancien cours</Button>
                        <Button :disabled="form.processing" @click="keepNew">Garder le nouveau cours</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
