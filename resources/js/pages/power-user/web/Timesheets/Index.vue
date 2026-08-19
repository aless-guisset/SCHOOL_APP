<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, List, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import WeeklyCalendar, { type CalendarSlot } from '@/components/WeeklyCalendar.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

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

const props = defineProps<{
    timesheets: Timesheet[];
    week_start: string; // YYYY-MM-DD (lundi)
}>();

const viewMode = ref<'calendar' | 'list'>('calendar');

// ── Navigation semaine ────────────────────────────────────────────────────────
function addDays(dateStr: string, days: number): string {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

const prevWeek = computed(() => addDays(props.week_start, -7));
const nextWeek = computed(() => addDays(props.week_start, 7));

function displayWeek(weekStart: string): string {
    const start = new Date(weekStart);
    const end   = new Date(weekStart);
    end.setDate(end.getDate() + 6);
    const opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' };
    return `${start.toLocaleDateString('fr-FR', opts)} – ${end.toLocaleDateString('fr-FR', opts)}`;
}

function navigate(week: string) {
    router.get('/timesheets', { week }, { preserveScroll: true, preserveState: true });
}

// ── Conversion Timesheet → CalendarSlot ──────────────────────────────────────
function dayOfWeekISO(dateStr: string): number {
    const d = new Date(dateStr);
    const day = d.getDay(); // 0=dim … 6=sam
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
                :description="`${timesheets.length} entrée(s) · ${displayWeek(week_start)}`"
            >
                <template #actions>
                    <!-- Navigation semaine -->
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" @click="navigate(prevWeek)">
                            <ChevronLeft class="size-4" />
                        </Button>
                        <Button variant="outline" size="sm" @click="navigate(new Date().toISOString().slice(0,10))">
                            Aujourd'hui
                        </Button>
                        <Button variant="outline" size="icon" @click="navigate(nextWeek)">
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>

                    <!-- Toggle vue -->
                    <div class="flex items-center overflow-hidden rounded-md border border-border">
                        <button
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

                    <Button as-child size="sm">
                        <Link href="/timesheets/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Calendrier -->
            <div v-if="viewMode === 'calendar'" class="mt-4">
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
