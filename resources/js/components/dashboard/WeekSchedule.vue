<script setup lang="ts">
import { computed, ref } from 'vue';
import WeeklyCalendar from '@/components/WeeklyCalendar.vue';
import type {CalendarSlot} from '@/components/WeeklyCalendar.vue';

export type WeekScheduleSlot = {
    schedule_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    course_label: string;
    teacher: string | null;
    classroom: string | null;
    subject: string | null;
};

const props = defineProps<{
    weekSchedule: {
        week_start: string;
        slots: WeekScheduleSlot[];
    };
    canManage?: boolean;
    showFilter?: boolean;
}>();

const selectedLabel = ref('');

const distinctLabels = computed(() =>
    Array.from(new Set(props.weekSchedule.slots.map(s => s.course_label))).sort()
);

const filteredSlots = computed(() =>
    selectedLabel.value
        ? props.weekSchedule.slots.filter(s => s.course_label === selectedLabel.value)
        : props.weekSchedule.slots
);

const calendarSlots = computed<CalendarSlot[]>(() =>
    filteredSlots.value.map(s => ({
        id: s.schedule_id,
        dayOfWeek: s.day_of_week,
        startTime: s.start_time,
        endTime: s.end_time,
        label: s.course_label,
        sublabel: [s.teacher, s.classroom, s.subject].filter(Boolean).join(' · ') || undefined,
        ...(props.canManage ? { href: `/schedules/${s.schedule_id}` } : {}),
    }))
);

const weekStartLabel = computed(() => {
    const date = new Date(`${props.weekSchedule.week_start}T00:00:00`);

    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
});
</script>

<template>
    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium text-muted-foreground">Semaine du {{ weekStartLabel }}</p>
            <select
                v-if="showFilter && distinctLabels.length > 1"
                v-model="selectedLabel"
                class="rounded-md border border-input bg-background px-2 py-1 text-sm"
            >
                <option value="">Toutes mes matières</option>
                <option v-for="label in distinctLabels" :key="label" :value="label">{{ label }}</option>
            </select>
        </div>
        <div v-if="calendarSlots.length === 0" class="flex h-32 items-center justify-center rounded-lg border border-dashed border-border">
            <p class="text-sm text-muted-foreground">Aucun créneau cette semaine.</p>
        </div>
        <WeeklyCalendar v-else :slots="calendarSlots" />
    </div>
</template>
