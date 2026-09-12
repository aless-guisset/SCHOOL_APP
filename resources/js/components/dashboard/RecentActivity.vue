<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

export type ActivityEntry = {
    id: number;
    event: 'created' | 'updated' | 'deleted';
    model_label: string | null;
    model_type: string;
    user_name: string | null;
    created_at: string;
};

defineProps<{
    activity: ActivityEntry[];
}>();

const eventVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    created: 'default',
    updated: 'outline',
    deleted: 'destructive',
};

function eventLabel(event: string): string {
    const key: Record<string, string> = {
        created: 'dashboard.recent_activity.event_created',
        updated: 'dashboard.recent_activity.event_updated',
        deleted: 'dashboard.recent_activity.event_deleted',
    };
    return key[event] ? t(key[event]) : event;
}

function relativeTime(isoDate: string): string {
    const diffMs = Date.now() - new Date(isoDate).getTime();
    const diffMin = Math.floor(diffMs / 60000);

    if (diffMin < 1) {
        return t('dashboard.recent_activity.just_now');
    }

    if (diffMin < 60) {
        return t('dashboard.recent_activity.minutes_ago', { n: String(diffMin) });
    }

    const diffH = Math.floor(diffMin / 60);

    if (diffH < 24) {
        return t('dashboard.recent_activity.hours_ago', { n: String(diffH) });
    }

    const diffJ = Math.floor(diffH / 24);

    return t('dashboard.recent_activity.days_ago', { n: String(diffJ) });
}
</script>

<template>
    <div v-if="activity.length === 0" class="flex h-32 items-center justify-center rounded-lg border border-dashed border-border">
        <p class="text-sm text-muted-foreground">{{ t('dashboard.recent_activity.empty') }}</p>
    </div>
    <ul v-else class="divide-y divide-border">
        <li v-for="entry in activity" :key="entry.id" class="flex items-center gap-3 py-2.5 text-sm">
            <Badge :variant="eventVariant[entry.event] ?? 'secondary'" class="shrink-0 text-xs">
                {{ eventLabel(entry.event) }}
            </Badge>
            <span class="min-w-0 flex-1 truncate">
                <span class="font-medium">{{ entry.model_type }}</span>
                <span v-if="entry.model_label" class="text-muted-foreground"> · {{ entry.model_label }}</span>
            </span>
            <span class="flex shrink-0 flex-col items-end text-xs text-muted-foreground">
                <span>{{ entry.user_name ?? '—' }}</span>
                <span class="text-[10px] opacity-75">{{ relativeTime(entry.created_at) }}</span>
            </span>
        </li>
    </ul>
</template>
