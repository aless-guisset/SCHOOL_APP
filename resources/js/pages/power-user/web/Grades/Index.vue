<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useSchool } from '@/composables/useSchool';
import AppLayout from '@/layouts/AppLayout.vue';

const { canManage } = useSchool();

type GradeRow = {
    id: number;
    period: string;
    grade: number;
    max_grade: number;
    subject_id: number;
    section_user_id: number;
    attachment_path: string | null;
    subject: { name: string } | null;
    section_user: {
        userschoolrole: { user: { firstname: string; lastname: string } | null } | null;
    } | null;
};

const props = defineProps<{
    grades: GradeRow[];
    subjects: Array<{ id: number; name: string }>;
    subject_id: number | null;
}>();

const subjectFilter = ref(props.subject_id ? String(props.subject_id) : 'all');

function applySubjectFilter() {
    router.get('/grades', subjectFilter.value === 'all' ? {} : { subject_id: subjectFilter.value }, { preserveScroll: true });
}

const columns = [
    { key: 'section_user', label: 'Élève', format: (_v: unknown, row: GradeRow) => {
        const u = row.section_user?.userschoolrole?.user;
        return u ? `${u.lastname} ${u.firstname}` : '—';
    } },
    { key: 'subject', label: 'Matière', format: (_v: unknown, row: GradeRow) => row.subject?.name ?? '—' },
    { key: 'period', label: 'Période' },
    { key: 'grade', label: 'Note', format: (v: unknown, row: GradeRow) => `${Number(v).toFixed(2)} / ${Number(row.max_grade).toFixed(2)}` },
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
                    <div class="flex items-center gap-2">
                        <Select v-model="subjectFilter" @update:model-value="applySubjectFilter">
                            <SelectTrigger class="w-44">
                                <SelectValue placeholder="Toutes les matières" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Toutes les matières</SelectItem>
                                <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button v-if="canManage" size="sm" as-child>
                            <Link href="/grades/create"><Plus class="size-4" />Saisir une note</Link>
                        </Button>
                    </div>
                </template>
            </PageHeader>
            <DataTable :data="grades" :columns="columns" empty-message="Aucune note enregistrée.">
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button v-if="row.attachment_path" variant="ghost" size="icon" class="size-8" as-child title="Télécharger la pièce jointe">
                            <a :href="`/grades/${row.id}/attachment`">
                                <Download class="size-4" />
                            </a>
                        </Button>
                        <Button variant="ghost" size="icon" class="size-8" as-child title="Télécharger le bulletin">
                            <a :href="`/grades/bulletin/${row.section_user_id}`">
                                <Download class="size-4" />
                            </a>
                        </Button>
                        <Button v-if="canManage" variant="ghost" size="icon" class="size-8" @click="destroy(row.id)">
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
