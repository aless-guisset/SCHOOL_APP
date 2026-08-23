<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface MonthSlot {
    id: number | string;
    date: string; // 'YYYY-MM-DD'
    label: string;
    sublabel?: string;
    href?: string;
}

const props = defineProps<{
    slots: MonthSlot[];
    monthStart: string; // 'YYYY-MM-DD', le 1er du mois affiché
    title?: string;
}>();

const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const MAX_PER_DAY = 3;

// Même palette que WeeklyCalendar (jour de la semaine → couleur), pour que la
// vue mois/trimestre reste visuellement cohérente avec la vue semaine.
const SLOT_COLORS: Record<number, string> = {
    1: 'bg-blue-100 border-blue-300 text-blue-900 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-200',
    2: 'bg-green-100 border-green-300 text-green-900 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200',
    3: 'bg-purple-100 border-purple-300 text-purple-900 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-200',
    4: 'bg-orange-100 border-orange-300 text-orange-900 dark:bg-orange-900/30 dark:border-orange-700 dark:text-orange-200',
    5: 'bg-rose-100 border-rose-300 text-rose-900 dark:bg-rose-900/30 dark:border-rose-700 dark:text-rose-200',
    6: 'bg-teal-100 border-teal-300 text-teal-900 dark:bg-teal-900/30 dark:border-teal-700 dark:text-teal-200',
    7: 'bg-gray-100 border-gray-300 text-gray-900 dark:bg-gray-800/40 dark:border-gray-600 dark:text-gray-200',
};

// Toutes les dates ci-dessous sont des chaînes 'YYYY-MM-DD' sans fuseau
// horaire — parsées/manipulées entièrement en UTC pour rester cohérentes
// quel que soit le fuseau du navigateur (même précaution que WeeklyCalendar/
// Timesheets/Index.vue).
function parseUTC(dateStr: string): Date {
    return new Date(`${dateStr}T00:00:00Z`);
}

function toDateStr(d: Date): string {
    return d.toISOString().slice(0, 10);
}

function isoDayOfWeek(d: Date): number {
    const day = d.getUTCDay(); // 0=dim … 6=sam
    return day === 0 ? 7 : day; // ISO : 1=lun … 7=dim
}

interface GridDay {
    date: string;
    dayNumber: number;
    inMonth: boolean;
    isToday: boolean;
}

const todayStr = toDateStr(new Date(new Date().toDateString()));

const gridDays = computed<GridDay[]>(() => {
    const first = parseUTC(props.monthStart);

    const gridStart = new Date(first);
    gridStart.setUTCDate(gridStart.getUTCDate() - (isoDayOfWeek(first) - 1));

    const lastOfMonth = new Date(first);
    lastOfMonth.setUTCMonth(lastOfMonth.getUTCMonth() + 1);
    lastOfMonth.setUTCDate(0);

    const gridEnd = new Date(lastOfMonth);
    gridEnd.setUTCDate(gridEnd.getUTCDate() + (7 - isoDayOfWeek(lastOfMonth)));

    const days: GridDay[] = [];
    const cursor = new Date(gridStart);
    while (cursor <= gridEnd) {
        const dateStr = toDateStr(cursor);
        days.push({
            date: dateStr,
            dayNumber: cursor.getUTCDate(),
            inMonth: cursor.getUTCMonth() === first.getUTCMonth(),
            isToday: dateStr === todayStr,
        });
        cursor.setUTCDate(cursor.getUTCDate() + 1);
    }

    return days;
});

const slotsByDate = computed(() => {
    const map: Record<string, MonthSlot[]> = {};
    for (const s of props.slots) {
        (map[s.date] ??= []).push(s);
    }

    return map;
});

const monthTitle = computed(() =>
    props.title ?? parseUTC(props.monthStart).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' })
);
</script>

<template>
    <div class="overflow-hidden rounded-md border border-border">
        <div class="border-b border-border bg-muted/40 px-3 py-1.5 text-center text-xs font-semibold capitalize text-muted-foreground">
            {{ monthTitle }}
        </div>
        <div class="grid grid-cols-7 border-b border-border bg-muted/20">
            <div v-for="d in DAY_LABELS" :key="d" class="px-1 py-1.5 text-center text-[10px] font-semibold text-muted-foreground">{{ d }}</div>
        </div>
        <div class="grid grid-cols-7">
            <div
                v-for="day in gridDays" :key="day.date"
                class="min-h-[5.5rem] border-r border-b border-border/50 p-1 last:border-r-0"
                :class="!day.inMonth ? 'bg-muted/10' : ''"
            >
                <div
                    class="mb-0.5 inline-flex size-5 items-center justify-center rounded-full text-[11px] font-medium"
                    :class="[
                        !day.inMonth ? 'text-muted-foreground/50' : 'text-foreground',
                        day.isToday ? 'bg-primary text-primary-foreground' : '',
                    ]"
                >{{ day.dayNumber }}</div>

                <div class="space-y-0.5">
                    <template v-for="s in (slotsByDate[day.date] ?? []).slice(0, MAX_PER_DAY)" :key="s.id">
                        <Link
                            v-if="s.href"
                            :href="s.href"
                            class="block truncate rounded border px-1 py-0.5 text-[10px] leading-tight transition-opacity hover:opacity-80"
                            :class="SLOT_COLORS[isoDayOfWeek(parseUTC(day.date))]"
                        >{{ s.label }}</Link>
                        <div
                            v-else
                            class="block truncate rounded border px-1 py-0.5 text-[10px] leading-tight"
                            :class="SLOT_COLORS[isoDayOfWeek(parseUTC(day.date))]"
                        >{{ s.label }}</div>
                    </template>
                    <div v-if="(slotsByDate[day.date]?.length ?? 0) > MAX_PER_DAY" class="px-1 text-[10px] text-muted-foreground">
                        +{{ (slotsByDate[day.date]!.length) - MAX_PER_DAY }} autre(s)
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
