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
    school: { id: number; name: string; email: string | null; phone_number: string | null; address: string | null; description: string | null; is_active: boolean };
}>();

const breadcrumbs = [
    { label: t('nav.schools'), href: '/schools' },
    { label: props.school.name },
];

function destroy() {
    if (confirm('Supprimer cet établissement ?')) {
        router.delete(`/schools/${props.school.id}`);
    }
}
</script>

<template>
    <Head :title="school.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="school.name" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/schools/${school.id}/edit`">
                            <Edit class="size-4" />
                            {{ t('action.edit') }}
                        </Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="size-4" />
                        {{ t('action.delete') }}
                    </Button>
                </template>
            </PageHeader>

            <Card>
                <CardHeader><CardTitle class="text-base">Informations</CardTitle></CardHeader>
                <CardContent>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.status') }}</dt>
                            <dd>
                                <Badge :variant="school.is_active ? 'default' : 'secondary'">
                                    {{ school.is_active ? t('label.active') : t('label.inactive') }}
                                </Badge>
                            </dd>
                        </div>
                        <div v-if="school.email" class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.email') }}</dt>
                            <dd>{{ school.email }}</dd>
                        </div>
                        <div v-if="school.phone_number" class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.phone') }}</dt>
                            <dd>{{ school.phone_number }}</dd>
                        </div>
                        <div v-if="school.address" class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.address') }}</dt>
                            <dd class="text-right">{{ school.address }}</dd>
                        </div>
                        <div v-if="school.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ school.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
