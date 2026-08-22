<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Plus, Trash2 } from 'lucide-vue-next';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type GradeRow = {
    id: number;
    period: string;
    grade: number;
    section_user_id: number;
    subject: { name: string } | null;
    section_user: {
        userschoolrole: { user: { firstname: string; lastname: string } | null } | null;
    } | null;
};

defineProps<{
    grades: GradeRow[];
}>();

const columns = [
    { key: 'section_user', label: 'Élève', format: (_v: unknown, row: GradeRow) => {
        const u = row.section_user?.userschoolrole?.user;
        return u ? `${u.lastname} ${u.firstname}` : '—';
    } },
    { key: 'subject', label: 'Matière', format: (_v: unknown, row: GradeRow) => row.subject?.name ?? '—' },
    { key: 'period', label: 'Période' },
    { key: 'grade', label: 'Note', format: (v: unknown) => `${Number(v).toFixed(2)} / 20` },
];

function destroy(id: number) {
    if (confirm('Supprimer cette note ?')) router.delete(`/grades/${id}`);
}
</script>

<template>
    <Head title="Notes" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Notes" :description="`${grades.length} note(s)`">
                <template #actions>
                    <Button size="sm" as-child>
                        <Link href="/grades/create"><Plus class="size-4" />Saisir une note</Link>
                    </Button>
                </template>
            </PageHeader>
            <DataTable :data="grades" :columns="columns" empty-message="Aucune note enregistrée.">
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button variant="ghost" size="icon" class="size-8" as-child title="Télécharger le bulletin">
                            <a :href="`/grades/bulletin/${row.section_user_id}`">
                                <Download class="size-4" />
                            </a>
                        </Button>
                        <Button variant="ghost" size="icon" class="size-8" @click="destroy(row.id)">
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
