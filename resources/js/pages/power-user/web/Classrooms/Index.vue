<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
const { canManage } = useSchool();

defineProps<{
    classrooms: {
        data: Array<{ id: number; name: string; location: string | null; is_active: boolean }>;
        total: number; current_page: number; last_page: number;
        next_page_url: string | null; prev_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        per_page: number;
    };
}>();

const columns = [
    { key: 'name',     label: t('label.name') },
    { key: 'location', label: 'Emplacement' },
    {
        key: 'is_active', label: t('label.status'), badge: true,
        badgeVariant: (row: { is_active: boolean }) => row.is_active ? 'default' : 'secondary' as const,
        format: (v: unknown) => v ? t('label.active') : t('label.inactive'),
    },
];
</script>

<template>
    <Head title="Salles" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Salles" :description="`${classrooms.total} salle(s)`">
                <template v-if="canManage" #actions>
                    <Button as-child size="sm">
                        <Link href="/classrooms/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>
            <DataTable :data="classrooms" :columns="columns" :row-href="(row) => `/classrooms/${row.id}`" empty-message="Aucune salle." />
        </div>
    </AppLayout>
</template>
