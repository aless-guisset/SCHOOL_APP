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
    role: {
        id: number;
        name: string;
        reference: string | null;
        description: string | null;
        is_active: boolean;
    };
}>();

const breadcrumbs = [
    { label: t('nav.roles'), href: '/roles' },
    { label: props.role.name },
];

function destroy() {
    if (confirm('Supprimer ce rôle ?')) router.delete(`/roles/${props.role.id}`);
}
</script>

<template>
    <Head :title="role.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="role.name" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/roles/${role.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
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
                            <dd><Badge :variant="role.is_active ? 'default' : 'secondary'">{{ role.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div v-if="role.reference" class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.reference') }}</dt>
                            <dd>{{ role.reference }}</dd>
                        </div>
                        <div v-if="role.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ role.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
