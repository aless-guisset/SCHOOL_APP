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

type SectionCourseRow = {
    id: number;
    name: string;
    total_hours: number;
    is_active: boolean;
    course: { id: number; name: string } | null;
    section_user: { section: { id: number; name: string } | null } | null;
};

defineProps<{
    sectionCourses: {
        data: SectionCourseRow[];
        total: number; current_page: number; last_page: number;
        next_page_url: string | null; prev_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        per_page: number;
    };
}>();

const columns = [
    { key: 'name', label: t('label.name') },
    { key: 'course', label: 'Cours', format: (_v: unknown, row: SectionCourseRow) => row.course?.name ?? '—' },
    { key: 'section_user', label: 'Section', format: (_v: unknown, row: SectionCourseRow) => row.section_user?.section?.name ?? '—' },
    { key: 'total_hours', label: 'Heures totales', format: (v: unknown) => `${v}h` },
    {
        key: 'is_active', label: t('label.status'), badge: true,
        badgeVariant: (row: SectionCourseRow) => row.is_active ? 'default' : 'secondary' as const,
        format: (v: unknown) => v ? t('label.active') : t('label.inactive'),
    },
];
</script>

<template>
    <Head title="Associations section-cours" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Associations section-cours" :description="`${sectionCourses.total} associations`">
                <template v-if="canManage" #actions>
                    <Button as-child size="sm">
                        <Link href="/section-courses/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>
            <DataTable :data="sectionCourses" :columns="columns" :row-href="(row) => `/section-courses/${row.id}`" empty-message="Aucune association." />
        </div>
    </AppLayout>
</template>
