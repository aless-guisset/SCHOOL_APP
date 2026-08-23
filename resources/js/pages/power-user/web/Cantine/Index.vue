<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarCheck, Plus, Trash2 } from 'lucide-vue-next';
import DataTable from '@/components/DataTable.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useSchool } from '@/composables/useSchool';
import AppLayout from '@/layouts/AppLayout.vue';

const { canManage } = useSchool();

const DAYS = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

type Registration = {
    id: number;
    day_of_week: number;
    section_user: {
        section: { name: string } | null;
        userschoolrole: { user: { firstname: string; lastname: string } | null } | null;
    } | null;
};

defineProps<{
    registrations: Registration[];
}>();

const columns = [
    { key: 'section_user', label: 'Élève', format: (_v: unknown, row: Registration) => {
        const u = row.section_user?.userschoolrole?.user;
        return u ? `${u.lastname} ${u.firstname}` : '—';
    } },
    { key: 'section_user', label: 'Section', format: (_v: unknown, row: Registration) => row.section_user?.section?.name ?? '—' },
    { key: 'day_of_week', label: 'Jour', format: (v: unknown) => DAYS[v as number] ?? '—' },
];

function unregister(id: number) {
    if (confirm('Retirer cette inscription cantine ?')) router.delete(`/cantine/${id}`);
}
</script>

<template>
    <Head title="Cantine" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Cantine" :description="`${registrations.length} inscription(s)`">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/cantine/roster"><CalendarCheck class="size-4" />Présences du jour</Link>
                    </Button>
                    <Button v-if="canManage" size="sm" as-child>
                        <Link href="/cantine/create"><Plus class="size-4" />Inscrire un élève</Link>
                    </Button>
                </template>
            </PageHeader>
            <DataTable :data="registrations" :columns="columns" empty-message="Aucune inscription cantine.">
                <template v-if="canManage" #actions="{ row }">
                    <Button variant="ghost" size="icon" class="size-8" @click="unregister(row.id)">
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
