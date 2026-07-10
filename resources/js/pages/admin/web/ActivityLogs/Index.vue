<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

type LogEntry = {
    id: number;
    event: 'created' | 'updated' | 'deleted' | 'login';
    model_type: string | null;
    model_id: number | null;
    model_label: string | null;
    user_email: string | null;
    ip_address: string | null;
    changes: { before: Record<string, unknown>; after: Record<string, unknown> } | null;
    created_at: string;
};

const props = defineProps<{
    logs: {
        data: LogEntry[];
        total: number; current_page: number; last_page: number;
        next_page_url: string | null; prev_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { event?: string; model?: string; user_id?: string; date_from?: string; date_to?: string };
}>();

const form = ref({
    event:     props.filters.event ?? '',
    model:     props.filters.model ?? '',
    date_from: props.filters.date_from ?? '',
    date_to:   props.filters.date_to ?? '',
});

function search() {
    router.get('/logs', form.value, { preserveState: true, replace: true });
}

function reset() {
    form.value = { event: '', model: '', date_from: '', date_to: '' };
    router.get('/logs', {}, { preserveState: false });
}

watch(() => form.value.event, search);

const eventVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    created: 'default',
    updated: 'outline',
    deleted: 'destructive',
    login:   'secondary',
};

function shortModelType(type: string | null): string {
    if (!type) return '—';
    const parts = type.split('\\');
    return parts[parts.length - 1];
}
</script>

<template>
    <Head title="Logs d'activité" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Logs d'activité" :description="`${logs.total} événement(s)`" />

            <!-- Filtres -->
            <Card class="mb-4">
                <CardContent class="pt-4">
                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                        <div class="space-y-1">
                            <Label>Événement</Label>
                            <Select v-model="form.event">
                                <SelectTrigger><SelectValue placeholder="Tous" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Tous</SelectItem>
                                    <SelectItem value="created">Créé</SelectItem>
                                    <SelectItem value="updated">Modifié</SelectItem>
                                    <SelectItem value="deleted">Supprimé</SelectItem>
                                    <SelectItem value="login">Connexion</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label>Modèle</Label>
                            <Input v-model="form.model" placeholder="ex : School, User…" @keyup.enter="search" />
                        </div>
                        <div class="space-y-1">
                            <Label>Du</Label>
                            <Input v-model="form.date_from" type="date" @change="search" />
                        </div>
                        <div class="space-y-1">
                            <Label>Au</Label>
                            <Input v-model="form.date_to" type="date" @change="search" />
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <Button size="sm" @click="search"><Search class="size-3.5" />Filtrer</Button>
                        <Button size="sm" variant="outline" @click="reset"><X class="size-3.5" />Réinitialiser</Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Date</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Événement</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Modèle</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Objet</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Utilisateur</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">Aucun log trouvé.</td>
                        </tr>
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="border-b transition-colors hover:bg-muted/30"
                        >
                            <td class="px-3 py-2 text-xs text-muted-foreground whitespace-nowrap">{{ log.created_at }}</td>
                            <td class="px-3 py-2">
                                <Badge :variant="eventVariant[log.event] ?? 'secondary'" class="text-xs">{{ log.event }}</Badge>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ shortModelType(log.model_type) }}</td>
                            <td class="px-3 py-2 text-xs">{{ log.model_label ?? `#${log.model_id}` }}</td>
                            <td class="px-3 py-2 text-xs">{{ log.user_email ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="mt-4 flex flex-wrap gap-1">
                <Button
                    v-for="link in logs.links"
                    :key="link.label"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url)"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
