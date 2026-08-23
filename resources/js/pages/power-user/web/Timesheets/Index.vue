<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarCheck, CalendarDays, ChevronLeft, ChevronRight, List, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import WeeklyCalendar, { type CalendarSlot } from '@/components/WeeklyCalendar.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
const { canManage } = useSchool();

type Timesheet = {
    id: number;
    date: string;
    hours_done: number;
    is_active: boolean;
    schedule: { start_time: string; end_time: string } | null;
    user_school_role: { user: { lastname: string; firstname: string } } | null;
    subject: { name: string } | null;
    classroom: { name: string } | null;
};

type Period = 'week' | 'month' | 'trimester';

const props = defineProps<{
    timesheets: Timesheet[];
    period: Period;
    anchor_date: string; // YYYY-MM-DD
    range_start: string; // YYYY-MM-DD
    range_end: string;   // YYYY-MM-DD
    week_start: string;  // YYYY-MM-DD (lundi) — pour WeeklyCalendar, vue semaine uniquement
}>();

const viewMode = ref<'calendar' | 'list'>(props.period === 'week' ? 'calendar' : 'list');

// ── Navigation (semaine / mois / trimestre) ──────────────────────────────────
function addDays(dateStr: string, days: number): string {
    // dateStr is a date-only 'YYYY-MM-DD' string — parse and mutate entirely in UTC
    // so the result doesn't shift across DST boundaries depending on the browser's
    // local timezone (see displayRange()/dayOfWeekISO() for the same pattern).
    const d = new Date(`${dateStr}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}

function addMonths(dateStr: string, months: number): string {
    const d = new Date(`${dateStr}T00:00:00Z`);
    d.setUTCMonth(d.getUTCMonth() + months);
    return d.toISOString().slice(0, 10);
}

const STEP: Record<Period, number> = { week: 7, month: 1, trimester: 3 };

function shift(direction: 1 | -1): string {
    return props.period === 'week'
        ? addDays(props.anchor_date, direction * STEP.week)
        : addMonths(props.anchor_date, direction * STEP[props.period]);
}

const prevAnchor = computed(() => shift(-1));
const nextAnchor = computed(() => shift(1));

function displayRange(): string {
    // range_start/range_end sont des chaînes 'YYYY-MM-DD' sans fuseau horaire —
    // parsées et formatées entièrement en UTC pour rester cohérentes quel que
    // soit le fuseau du navigateur.
    const start = new Date(`${props.range_start}T00:00:00Z`);
    const end = new Date(`${props.range_end}T00:00:00Z`);

    if (props.period === 'month') {
        return start.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    }

    const opts: Intl.DateTimeFormatOptions = props.period === 'trimester'
        ? { month: 'short', year: 'numeric', timeZone: 'UTC' }
        : { day: 'numeric', month: 'short', timeZone: 'UTC' };

    return `${start.toLocaleDateString('fr-FR', opts)} – ${end.toLocaleDateString('fr-FR', opts)}`;
}

function navigate(date: string, period: Period = props.period) {
    router.get('/timesheets', { date, period }, { preserveScroll: true, preserveState: true });
}

function setPeriod(period: Period) {
    if (period === props.period) return;
    if (period !== 'week') viewMode.value = 'list';
    navigate(props.anchor_date, period);
}

// Browser's local calendar day as 'YYYY-MM-DD' (NOT toISOString(), which is UTC-based).
function todayLocal(): string {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

// ── Conversion Timesheet → CalendarSlot ──────────────────────────────────────
function dayOfWeekISO(dateStr: string): number {
    // dateStr is a date-only 'YYYY-MM-DD' string, parsed by `new Date()` as UTC
    // midnight — read it back with the UTC getter to avoid a local-timezone
    // off-by-one-day shift west of UTC.
    const d = new Date(dateStr);
    const day = d.getUTCDay(); // 0=dim … 6=sam
    return day === 0 ? 7 : day; // ISO: 1=lun … 7=dim
}

const calendarSlots = computed<CalendarSlot[]>(() =>
    props.timesheets
        .filter(ts => ts.schedule)
        .map(ts => ({
            id:        ts.id,
            dayOfWeek: dayOfWeekISO(ts.date),
            startTime: ts.schedule!.start_time,
            endTime:   ts.schedule!.end_time,
            label:     ts.user_school_role
                ? `${ts.user_school_role.user.lastname} ${ts.user_school_role.user.firstname}`
                : '—',
            sublabel: ts.subject?.name,
            href:     `/timesheets/${ts.id}`,
        }))
);

// ── DataTable columns ─────────────────────────────────────────────────────────
const columns = [
    { key: 'date', label: 'Date' },
    {
        key: 'user_school_role', label: 'Professeur',
        format: (_: unknown, row: Timesheet) =>
            row.user_school_role
                ? `${row.user_school_role.user.lastname} ${row.user_school_role.user.firstname}`
                : '—',
    },
    { key: 'subject', label: 'Matière', format: (_: unknown, row: Timesheet) => row.subject?.name ?? '—' },
    { key: 'classroom', label: 'Salle', format: (_: unknown, row: Timesheet) => row.classroom?.name ?? '—' },
    { key: 'hours_done', label: 'Heures', format: (v: unknown) => `${v}h` },
];
</script>

<template>
    <Head title="Feuilles de temps" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />

            <PageHeader
                title="Feuilles de temps"
                :description="`${timesheets.length} entrée(s) · ${displayRange()}`"
            >
                <template #actions>
                    <!-- Toggle période -->
                    <div class="flex items-center overflow-hidden rounded-md border border-border">
                        <button
                            v-for="p in (['week', 'month', 'trimester'] as const)" :key="p"
                            class="px-2.5 py-1.5 text-xs font-medium transition-colors"
                            :class="period === p ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                            @click="setPeriod(p)"
                        >{{ p === 'week' ? 'Semaine' : p === 'month' ? 'Mois' : 'Trimestre' }}</button>
                    </div>

                    <!-- Navigation -->
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" @click="navigate(prevAnchor)">
                            <ChevronLeft class="size-4" />
                        </Button>
                        <Button variant="outline" size="sm" @click="navigate(todayLocal())">
                            <span class="sr-only sm:not-sr-only">Aujourd'hui</span>
                            <CalendarCheck class="size-4 sm:hidden" />
                        </Button>
                        <Button variant="outline" size="icon" @click="navigate(nextAnchor)">
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>

                    <!-- Toggle vue (calendrier disponible seulement en vue semaine) -->
                    <div class="flex items-center overflow-hidden rounded-md border border-border">
                        <button
                            v-if="period === 'week'"
                            class="px-2.5 py-1.5 transition-colors"
                            :class="viewMode === 'calendar' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                            title="Vue calendrier"
                            @click="viewMode = 'calendar'"
                        ><CalendarDays class="size-4" /></button>
                        <button
                            class="px-2.5 py-1.5 transition-colors"
                            :class="viewMode === 'list' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                            title="Vue liste"
                            @click="viewMode = 'list'"
                        ><List class="size-4" /></button>
                    </div>

                    <Button v-if="canManage" as-child size="sm">
                        <Link href="/timesheets/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Calendrier (vue semaine uniquement) -->
            <div v-if="period === 'week' && viewMode === 'calendar'" class="mt-4">
                <div v-if="calendarSlots.length === 0" class="rounded-md border border-border py-12 text-center text-sm text-muted-foreground">
                    Aucune feuille de temps cette semaine.
                </div>
                <WeeklyCalendar v-else :slots="calendarSlots" />
            </div>

            <!-- Liste -->
            <DataTable
                v-else
                class="mt-4"
                :data="timesheets"
                :columns="columns"
                :row-href="(row: Timesheet) => `/timesheets/${row.id}`"
                empty-message="Aucune feuille de temps."
            />
        </div>
    </AppLayout>
</template>
