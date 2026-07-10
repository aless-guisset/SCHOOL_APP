<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const props = defineProps<{
    classroom: { id: number; name: string; location: string | null; is_active: boolean };
}>();

const breadcrumbs = [
    { label: 'Salles', href: '/classrooms' },
    { label: props.classroom.name },
];

function destroy() {
    if (confirm('Supprimer cette salle ?')) {
        router.delete(`/classrooms/${props.classroom.id}`);
    }
}
</script>

<template>
    <Head :title="classroom.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="classroom.name" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/classrooms/${classroom.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="size-4" />{{ t('action.delete') }}
                    </Button>
                </template>
            </PageHeader>
            <Card>
                <CardHeader><CardTitle class="text-base">Informations</CardTitle></CardHeader>
                <CardContent>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.status') }}</dt>
                            <dd><Badge :variant="classroom.is_active ? 'default' : 'secondary'">{{ classroom.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div v-if="classroom.location" class="flex justify-between">
                            <dt class="text-muted-foreground">Emplacement</dt>
                            <dd>{{ classroom.location }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
