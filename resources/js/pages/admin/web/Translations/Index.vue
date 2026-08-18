<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Trash2, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

type Translation = {
    id: number;
    tag_key: string;
    language_code: string;
    screen_name: string | null;
    translated_value: string;
    is_active: boolean;
};

type PaginatedTranslations = {
    data: Translation[];
    total: number;
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    translations: PaginatedTranslations;
    filters: { language_code?: string; screen_name?: string; search?: string };
    languages: string[];
}>();

const search         = ref(props.filters.search ?? '');
const filterLang     = ref(props.filters.language_code ?? '');
const filterScreen   = ref(props.filters.screen_name ?? '');

function applyFilters() {
    router.get('/translations', {
        search:        search.value || undefined,
        language_code: filterLang.value || undefined,
        screen_name:   filterScreen.value || undefined,
    }, { preserveScroll: true });
}

function clearFilters() {
    search.value = filterLang.value = filterScreen.value = '';
    router.get('/translations', {}, { preserveScroll: true });
}

function deleteTranslation(id: number) {
    if (!confirm('Supprimer cette traduction ?')) return;
    useForm({}).delete(`/translations/${id}`, { preserveScroll: true });
}

function truncate(s: string, n = 60) { return s.length > n ? s.slice(0, n) + '…' : s; }
</script>

<template>
    <Head title="Traductions" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Traductions" :description="`${translations.total} entrée(s)`">
                <template #actions>
                    <Button as-child size="sm">
                        <Link href="/translations/create"><Plus class="size-4" />Nouvelle</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Filtres -->
            <div class="mt-4 flex flex-wrap gap-2">
                <div class="relative w-48">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        class="pl-8"
                        placeholder="Rechercher clé…"
                        @keydown.enter="applyFilters"
                    />
                </div>
                <Select v-model="filterLang" @update:model-value="applyFilters">
                    <SelectTrigger class="w-32">
                        <SelectValue placeholder="Langue" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Toutes</SelectItem>
                        <SelectItem v-for="l in languages" :key="l" :value="l">{{ l }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input
                    v-model="filterScreen"
                    class="w-40"
                    placeholder="Screen…"
                    @keydown.enter="applyFilters"
                />
                <Button variant="outline" size="sm" @click="applyFilters">Filtrer</Button>
                <Button v-if="filters.search || filters.language_code || filters.screen_name" variant="ghost" size="sm" @click="clearFilters">
                    Effacer
                </Button>
            </div>

            <!-- Table -->
            <div class="mt-4 overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Clé</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Langue</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Screen</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Valeur</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Statut</th>
                            <th class="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!translations.data.length">
                            <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">Aucune traduction.</td>
                        </tr>
                        <tr
                            v-for="tr in translations.data" :key="tr.id"
                            class="border-b hover:bg-muted/30"
                        >
                            <td class="px-3 py-2 font-mono text-xs">{{ tr.tag_key }}</td>
                            <td class="px-3 py-2">
                                <Badge variant="outline">{{ tr.language_code }}</Badge>
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">{{ tr.screen_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-muted-foreground">{{ truncate(tr.translated_value) }}</td>
                            <td class="px-3 py-2">
                                <Badge :variant="tr.is_active ? 'default' : 'secondary'">
                                    {{ tr.is_active ? 'Actif' : 'Inactif' }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon" as-child>
                                        <Link :href="`/translations/${tr.id}/edit`"><Pencil class="size-4" /></Link>
                                    </Button>
                                    <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive" @click="deleteTranslation(tr.id)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="translations.last_page > 1" class="mt-4 flex flex-wrap gap-1">
                <Button
                    v-for="link in translations.links" :key="link.label"
                    :variant="link.active ? 'default' : 'outline'"
                    size="sm"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
