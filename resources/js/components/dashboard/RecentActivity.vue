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
            <span class="shrink-0 text-xs text-muted-foreground">{{ entry.user_name ?? '—' }}</span>
        </li>
    </ul>
</template>
