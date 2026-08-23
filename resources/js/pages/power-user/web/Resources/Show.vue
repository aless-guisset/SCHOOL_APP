<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
const { canManage } = useSchool();

const props = defineProps<{
    resource: { id: number; name: string; type: string | null; description: string | null; is_active: boolean };
}>();

const breadcrumbs = [
    { label: 'Ressources', href: '/resources' },
    { label: props.resource.name },
];

function destroy() {
    if (confirm('Supprimer cette ressource ?')) {
        router.delete(`/resources/${props.resource.id}`);
    }
}
</script>

<template>
    <Head :title="resource.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="resource.name" :breadcrumbs="breadcrumbs">
                <template v-if="canManage" #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/resources/${resource.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
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
                            <dd><Badge :variant="resource.is_active ? 'default' : 'secondary'">{{ resource.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div v-if="resource.type" class="flex justify-between">
                            <dt class="text-muted-foreground">Type</dt>
                            <dd>{{ resource.type }}</dd>
                        </div>
                        <div v-if="resource.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ resource.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
