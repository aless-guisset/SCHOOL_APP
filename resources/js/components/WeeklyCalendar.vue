<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface CalendarSlot {
    id: number | string;
    dayOfWeek: number;   // 1=Lun … 7=Dim (ISO-8601)
    startTime: string;   // "HH:MM:SS"
    endTime: string;     // "HH:MM:SS"
    label: string;
    sublabel?: string;
    href: string;
}

const props = defineProps<{
    slots: CalendarSlot[];
}>();

const DAY_LABELS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
const HOUR_START  = 7;
const HOUR_END    = 20;
const TOTAL_HOURS = HOUR_END - HOUR_START;
const HOUR_PX     = 64;

const hours = Array.from({ length: TOTAL_HOURS + 1 }, (_, i) => HOUR_START + i);

const activeDays = computed(() => {
    const used = new Set(props.slots.map(s => s.dayOfWeek));
    return [1, 2, 3, 4, 5, 6, 7].filter(d => d <= 5 || used.has(d));
});

const byDay = computed(() => {
    const map: Record<number, CalendarSlot[]> = {};
    for (const s of props.slots) {
        (map[s.dayOfWeek] ??= []).push(s);
    }
    return map;
});

const SLOT_COLORS: Record<number, string> = {
    1: 'bg-blue-100 border-blue-300 text-blue-900 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-200',
    2: 'bg-green-100 border-green-300 text-green-900 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200',
    3: 'bg-purple-100 border-purple-300 text-purple-900 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-200',
    4: 'bg-orange-100 border-orange-300 text-orange-900 dark:bg-orange-900/30 dark:border-orange-700 dark:text-orange-200',
    5: 'bg-rose-100 border-rose-300 text-rose-900 dark:bg-rose-900/30 dark:border-rose-700 dark:text-rose-200',
    6: 'bg-teal-100 border-teal-300 text-teal-900 dark:bg-teal-900/30 dark:border-teal-700 dark:text-teal-200',
    7: 'bg-gray-100 border-gray-300 text-gray-900 dark:bg-gray-800/40 dark:border-gray-600 dark:text-gray-200',
};

function toMin(time: string): number {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
}

function slotStyle(s: CalendarSlot) {
    const startMin = toMin(s.startTime) - HOUR_START * 60;
    const duration = toMin(s.endTime) - toMin(s.startTime);
    return {
        top:    `${(startMin / 60) * HOUR_PX}px`,
        height: `${Math.max((duration / 60) * HOUR_PX - 2, 20)}px`,
    };
}

function fmt(t: string) { return t.substring(0, 5); }
</script>

<template>
    <div class="overflow-x-auto rounded-md border border-border">
        <div :style="`min-width: ${48 + activeDays.length * 120}px`">
            <!-- En-têtes jours -->
            <div
                class="grid border-b border-border bg-muted/40"
                :style="`grid-template-columns: 48px repeat(${activeDays.length}, 1fr)`"
            >
                <div />
                <div
                    v-for="day in activeDays" :key="day"
                    class="flex h-10 items-center justify-center border-l border-border text-xs font-semibold text-muted-foreground"
                >{{ DAY_LABELS[day - 1] }}</div>
            </div>

            <!-- Corps grille -->
            <div
                class="grid"
                :style="`grid-template-columns: 48px repeat(${activeDays.length}, 1fr); height: ${TOTAL_HOURS * HOUR_PX}px`"
            >
                <!-- Labels heures -->
                <div class="relative border-r border-border bg-muted/20">
                    <div
                        v-for="h in hours.slice(0, -1)" :key="h"
                        class="absolute right-0 left-0 flex items-start justify-end pr-1.5"
                        :style="`top: ${(h - HOUR_START) * HOUR_PX}px`"
                    >
                        <span class="mt-0.5 text-[10px] leading-none text-muted-foreground">{{ h }}h</span>
                    </div>
                </div>

                <!-- Colonnes jours -->
                <div
                    v-for="day in activeDays" :key="day"
                    class="relative border-l border-border"
                >
                    <div
                        v-for="h in hours.slice(0, -1)" :key="h"
                        class="pointer-events-none absolute inset-x-0 border-t border-border/30"
                        :style="`top: ${(h - HOUR_START) * HOUR_PX}px; height: ${HOUR_PX}px`"
                    />
                    <Link
                        v-for="s in (byDay[day] ?? [])"
                        :key="s.id"
                        :href="s.href"
                        class="absolute inset-x-1 overflow-hidden rounded border px-1.5 py-1 text-xs transition-opacity hover:opacity-80"
                        :class="SLOT_COLORS[day]"
                        :style="slotStyle(s)"
                    >
                        <p class="truncate font-semibold leading-tight">{{ s.label }}</p>
                        <p class="truncate text-[10px] opacity-75">{{ fmt(s.startTime) }}–{{ fmt(s.endTime) }}</p>
                        <p v-if="s.sublabel" class="truncate text-[10px] opacity-60">{{ s.sublabel }}</p>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
