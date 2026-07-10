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
    users: {
        data: Array<{ id: number; firstname: string; lastname: string; email: string; phone_number: string | null; is_active: boolean }>;
        total: number; current_page: number; last_page: number;
        next_page_url: string | null; prev_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        per_page: number;
    };
}>();

const columns = [
    { key: 'lastname',  label: t('label.lastname'),  format: (_: unknown, row: { firstname: string; lastname: string }) => `${row.lastname} ${row.firstname}` },
    { key: 'email',     label: t('label.email') },
    { key: 'phone_number', label: t('label.phone') },
    {
        key: 'is_active', label: t('label.status'), badge: true,
        badgeVariant: (row: { is_active: boolean }) => row.is_active ? 'default' : 'secondary' as const,
        format: (v: unknown) => v ? t('label.active') : t('label.inactive'),
    },
];
</script>

<template>
    <Head title="Utilisateurs" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader :title="t('nav.users')" :description="`${users.total} utilisateur(s)`">
                <template #actions>
                    <Button as-child size="sm">
                        <Link href="/users/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>
            <DataTable :data="users" :columns="columns" :row-href="(row) => `/users/${row.id}`" empty-message="Aucun utilisateur." />
        </div>
    </AppLayout>
</template>
