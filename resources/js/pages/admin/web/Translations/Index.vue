<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Trash2, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

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
    per_page: number;
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
const filterLang     = ref(props.filters.language_code ?? 'all');
const filterScreen   = ref(props.filters.screen_name ?? '');
const deletingId     = ref<number | null>(null);

function applyFilters() {
    router.get('/translations', {
        search:        search.value || undefined,
        language_code: filterLang.value !== 'all' ? filterLang.value : undefined,
        screen_name:   filterScreen.value || undefined,
    }, { preserveScroll: true });
}

function clearFilters() {
    search.value = '';
    filterLang.value = 'all';
    filterScreen.value = '';
    router.get('/translations', {}, { preserveScroll: true });
}

function deleteTranslation(id: number) {
    if (!confirm(t('translations.confirm_delete'))) return;
    if (deletingId.value !== null) return; // Prevent double-submit
    deletingId.value = id;
    useForm({}).delete(`/translations/${id}`, {
        preserveScroll: true,
        onFinish: () => { deletingId.value = null; },
    });
}

function truncate(s: string, n = 60) { return s.length > n ? s.slice(0, n) + '…' : s; }

const columns = [
    { key: 'tag_key', label: t('label.key'), class: 'font-mono text-xs' },
    { key: 'language_code', label: t('label.language'), badge: true, badgeVariant: () => 'outline' as const },
    { key: 'screen_name', label: t('label.screen_name') },
    { key: 'translated_value', label: t('label.value'), format: (v: unknown) => truncate(String(v ?? '')) },
    {
        key: 'is_active', label: t('label.status'), badge: true,
        badgeVariant: (row: Translation) => row.is_active ? 'default' : 'secondary' as const,
        format: (v: unknown) => v ? t('label.active') : t('label.inactive'),
    },
];
</script>

<template>
    <Head title="Traductions" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader :title="t('nav.translations')" :description="`${translations.total} entrée(s)`">
                <template #actions>
                    <Button as-child size="sm">
                        <Link href="/translations/create"><Plus class="size-4" />{{ t('translations.new') }}</Link>
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
                        :placeholder="`${t('action.search')}…`"
                        @keydown.enter="applyFilters"
                    />
                </div>
                <Select v-model="filterLang" @update:model-value="applyFilters">
                    <SelectTrigger class="w-32">
                        <SelectValue :placeholder="t('label.language')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Toutes</SelectItem>
                        <SelectItem v-for="l in languages" :key="l" :value="l">{{ l }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input
                    v-model="filterScreen"
                    class="w-40"
                    :placeholder="`${t('label.screen_name')}…`"
                    @keydown.enter="applyFilters"
                />
                <Button variant="outline" size="sm" @click="applyFilters">{{ t('action.filter') }}</Button>
                <Button v-if="filters.search || filters.language_code || filters.screen_name" variant="ghost" size="sm" @click="clearFilters">
                    {{ t('action.clear_filters') }}
                </Button>
            </div>

            <!-- Table -->
            <div class="mt-4">
                <DataTable :data="translations" :columns="columns" :empty-message="t('translations.empty')">
                    <template #actions="{ row }">
                        <div class="flex justify-end gap-1">
                            <Button variant="ghost" size="icon" as-child>
                                <Link :href="`/translations/${row.id}/edit`"><Pencil class="size-4" /></Link>
                            </Button>
                            <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive" :disabled="deletingId === row.id" @click="deleteTranslation(row.id)">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
