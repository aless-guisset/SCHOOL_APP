<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

defineProps<{
    timesheets: {
        data: Array<{
            id: number;
            date: string;
            hours_done: number;
            is_active: boolean;
            user_school_role: { user: { lastname: string; firstname: string } } | null;
            subject: { name: string } | null;
            classroom: { name: string } | null;
        }>;
        total: number; current_page: number; last_page: number;
        next_page_url: string | null; prev_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        per_page: number;
    };
}>();

const columns = [
    { key: 'date', label: 'Date' },
    {
        key: 'user_school_role', label: 'Professeur',
        format: (_: unknown, row: { user_school_role: { user: { lastname: string; firstname: string } } | null }) =>
            row.user_school_role ? `${row.user_school_role.user.lastname} ${row.user_school_role.user.firstname}` : '—',
    },
    { key: 'subject', label: 'Matière', format: (_: unknown, row: { subject: { name: string } | null }) => row.subject?.name ?? '—' },
    { key: 'classroom', label: 'Salle', format: (_: unknown, row: { classroom: { name: string } | null }) => row.classroom?.name ?? '—' },
    { key: 'hours_done', label: 'Heures', format: (v: unknown) => `${v}h` },
];
</script>

<template>
    <Head title="Feuilles de temps" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Feuilles de temps" :description="`${timesheets.total} entrée(s)`">
                <template #actions>
                    <Button as-child size="sm">
                        <Link href="/timesheets/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>
            <DataTable :data="timesheets" :columns="columns" :row-href="(row) => `/timesheets/${row.id}`" empty-message="Aucune feuille de temps." />
        </div>
    </AppLayout>
</template>
