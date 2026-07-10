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
    lesson: { id: number; name: string; description: string | null; is_active: boolean; subject: { id: number; name: string } | null };
}>();

const breadcrumbs = [
    { label: 'Leçons', href: '/lessons' },
    { label: props.lesson.name },
];

function destroy() {
    if (confirm('Supprimer cette leçon ?')) {
        router.delete(`/lessons/${props.lesson.id}`);
    }
}
</script>

<template>
    <Head :title="lesson.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="lesson.name" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/lessons/${lesson.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
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
                            <dd><Badge :variant="lesson.is_active ? 'default' : 'secondary'">{{ lesson.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div v-if="lesson.subject" class="flex justify-between">
                            <dt class="text-muted-foreground">Matière</dt>
                            <dd>
                                <Link :href="`/subjects/${lesson.subject.id}`" class="underline underline-offset-2">{{ lesson.subject.name }}</Link>
                            </dd>
                        </div>
                        <div v-if="lesson.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ lesson.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
