<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

export type CurrentCourse = {
    status: 'in_progress' | 'upcoming';
    course_label: string;
    start_time: string;
    end_time: string;
    timesheet_id: number | null;
    attendance_submitted: boolean;
};

const props = defineProps<{
    currentCourse: CurrentCourse | null;
}>();

function fmt(t: string): string {
    return t.slice(0, 5);
}
</script>

<template>
    <Card>
        <CardContent class="flex flex-wrap items-center justify-between gap-4 pt-6">
            <div v-if="props.currentCourse" class="flex items-center gap-3">
                <Clock class="size-8 shrink-0 text-primary" />
                <div>
                    <Badge :variant="props.currentCourse.status === 'in_progress' ? 'default' : 'secondary'" class="mb-1">
                        {{ props.currentCourse.status === 'in_progress' ? t('dashboard.current_course.in_progress') : t('dashboard.current_course.upcoming') }}
                    </Badge>
                    <p class="font-semibold">{{ props.currentCourse.course_label }}</p>
                    <p class="text-xs text-muted-foreground">{{ fmt(props.currentCourse.start_time) }} – {{ fmt(props.currentCourse.end_time) }}</p>
                </div>
            </div>
            <div v-else class="flex items-center gap-3 text-muted-foreground">
                <Clock class="size-8 shrink-0" />
                <p class="font-medium">{{ t('dashboard.current_course.none_today') }}</p>
            </div>
            <Button v-if="props.currentCourse?.timesheet_id" size="sm" as-child>
                <Link :href="`/timesheets/${props.currentCourse.timesheet_id}`">
                    {{ props.currentCourse.attendance_submitted ? t('dashboard.current_course.view_attendance') : t('dashboard.current_course.take_attendance') }}
                </Link>
            </Button>
        </CardContent>
    </Card>
</template>
