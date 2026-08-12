<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

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

const eventLabel: Record<string, string> = {
    created: 'Créé',
    updated: 'Modifié',
    deleted: 'Supprimé',
};

function relativeTime(isoDate: string): string {
    const diffMs = Date.now() - new Date(isoDate).getTime();
    const diffMin = Math.floor(diffMs / 60000);

    if (diffMin < 1) {
return 'à l\'instant';
}

    if (diffMin < 60) {
return `il y a ${diffMin}min`;
}

    const diffH = Math.floor(diffMin / 60);

    if (diffH < 24) {
return `il y a ${diffH}h`;
}

    const diffJ = Math.floor(diffH / 24);

    return `il y a ${diffJ}j`;
}
</script>

<template>
    <div v-if="activity.length === 0" class="flex h-32 items-center justify-center rounded-lg border border-dashed border-border">
        <p class="text-sm text-muted-foreground">Aucune activité récente.</p>
    </div>
    <ul v-else class="divide-y divide-border">
        <li v-for="entry in activity" :key="entry.id" class="flex items-center gap-3 py-2.5 text-sm">
            <Badge :variant="eventVariant[entry.event] ?? 'secondary'" class="shrink-0 text-xs">
                {{ eventLabel[entry.event] ?? entry.event }}
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
