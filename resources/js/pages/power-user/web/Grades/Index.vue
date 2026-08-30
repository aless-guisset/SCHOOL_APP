<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, Paperclip, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ViewingChildBanner from '@/components/ViewingChildBanner.vue';
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
    has_attachment: boolean;
    subject: { name: string } | null;
    section_user: {
        userschoolrole: { user: { firstname: string; lastname: string } | null } | null;
    } | null;
};

const props = defineProps<{
    grades: GradeRow[];
    subjects: Array<{ id: number; name: string }>;
    subject_id: number | null;
    viewing_child?: string | null;
}>();

const subjectFilter = ref(props.subject_id ? String(props.subject_id) : 'all');

// as_parent=1 doit survivre aux navigations internes (filtre matière) : sans
// lui, l'appelant à double rôle retomberait silencieusement sur sa vue
// enseignante sans que rien ne le signale.
const page = usePage();
const asParent = computed(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('as_parent'));

function withAsParent(params: Record<string, string>): Record<string, string> {
    return asParent.value ? { ...params, as_parent: asParent.value } : params;
}

// Les actions d'écriture n'ont aucun sens sur les notes d'un enfant consulté
// via "Mes enfants", même si le rôle réel de l'appelant y donne droit ailleurs.
const canWrite = computed(() => canManage.value && !props.viewing_child);

function applySubjectFilter() {
    router.get('/grades', withAsParent(subjectFilter.value === 'all' ? {} : { subject_id: subjectFilter.value }), { preserveScroll: true });
}

const columns = [
    { key: 'section_user', label: 'Élève', format: (_v: unknown, row: GradeRow) => {
        const u = row.section_user?.userschoolrole?.user;
        return u ? `${u.lastname} ${u.firstname}` : '—';
    } },
    { key: 'subject', label: 'Matière', format: (_v: unknown, row: GradeRow) => row.subject?.name ?? '—' },
    { key: 'period', label: 'Période' },
    { key: 'grade', label: 'Note', format: (v: unknown, row: GradeRow) => {
        const max = Number.isInteger(row.max_grade) ? row.max_grade : Number(row.max_grade).toFixed(2);
        return `${Number(v).toFixed(2)} / ${max}`;
    } },
];

function destroy(id: number) {
    if (!confirm('Supprimer cette note ?')) return;
    router.delete(`/grades/${id}`, {
        onSuccess: () => {
            if (subjectFilter.value !== 'all') router.get('/grades', withAsParent({ subject_id: subjectFilter.value }), { preserveScroll: true });
        },
    });
}
</script>

<template>
    <Head title="Notes" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <ViewingChildBanner v-if="viewing_child" :name="viewing_child" />
            <PageHeader title="Notes" :description="`${grades.length} note(s)`">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Select v-if="subjects.length" v-model="subjectFilter" @update:model-value="applySubjectFilter">
                            <SelectTrigger class="w-44">
                                <SelectValue placeholder="Toutes les matières" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Toutes les matières</SelectItem>
                                <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button v-if="canWrite" size="sm" as-child>
                            <Link href="/grades/create"><Plus class="size-4" />Saisir une note</Link>
                        </Button>
                    </div>
                </template>
            </PageHeader>
            <DataTable :data="grades" :columns="columns" empty-message="Aucune note enregistrée.">
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button v-if="row.has_attachment" variant="ghost" size="icon" class="size-8" as-child title="Télécharger la pièce jointe">
                            <a :href="`/grades/${row.id}/attachment`">
                                <Paperclip class="size-4" />
                            </a>
                        </Button>
                        <Button variant="ghost" size="icon" class="size-8" as-child title="Télécharger le bulletin">
                            <a :href="`/grades/bulletin/${row.section_user_id}`">
                                <Download class="size-4" />
                            </a>
                        </Button>
                        <Button v-if="canWrite" variant="ghost" size="icon" class="size-8" @click="destroy(row.id)">
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
