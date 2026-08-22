<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

type Assignment = {
    id: number;
    is_active: boolean;
    user: { id: number; firstname: string; lastname: string; email: string } | null;
    role: { id: number; name: string } | null;
};

defineProps<{
    assignments: {
        data: Assignment[];
        total: number; current_page: number; last_page: number;
        next_page_url: string | null; prev_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        per_page: number;
    };
}>();

const columns = [
    { key: 'user', label: t('label.name'), format: (_v: unknown, row: Assignment) => row.user ? `${row.user.firstname} ${row.user.lastname}` : '—' },
    { key: 'user', label: 'Email', format: (_v: unknown, row: Assignment) => row.user?.email ?? '—' },
    { key: 'role', label: t('nav.roles'), format: (_v: unknown, row: Assignment) => row.role?.name ?? '—' },
    {
        key: 'is_active', label: t('label.status'), badge: true,
        badgeVariant: (row: Assignment) => row.is_active ? 'default' : 'secondary' as const,
        format: (v: unknown) => v ? t('label.active') : t('label.inactive'),
    },
];

function destroy(id: number) {
    if (confirm('Supprimer cette assignation ?')) router.delete(`/user-school-roles/${id}`);
}
</script>

<template>
    <Head title="Assignations rôles" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Assignations rôles" :description="`${assignments.total} assignations`">
                <template #actions>
                    <Button as-child size="sm">
                        <Link href="/user-school-roles/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>
            <DataTable :data="assignments" :columns="columns" empty-message="Aucune assignation.">
                <template #actions="{ row }">
                    <Button variant="ghost" size="icon" class="size-8" @click="destroy(row.id)">
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
