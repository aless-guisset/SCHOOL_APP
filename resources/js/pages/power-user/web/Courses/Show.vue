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
    course: {
        id: number;
        name: string;
        reference: string | null;
        description: string | null;
        is_active: boolean;
        subjects: Array<{ id: number; name: string; is_active: boolean }>;
    };
}>();

const breadcrumbs = [
    { label: t('nav.courses'), href: '/courses' },
    { label: props.course.name },
];

function destroy() {
    if (confirm('Supprimer ce cours ?')) router.delete(`/courses/${props.course.id}`);
}
</script>

<template>
    <Head :title="course.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="course.name" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/courses/${course.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
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
                            <dd><Badge :variant="course.is_active ? 'default' : 'secondary'">{{ course.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div v-if="course.reference" class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.reference') }}</dt>
                            <dd>{{ course.reference }}</dd>
                        </div>
                        <div v-if="course.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ course.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="course.subjects?.length" class="mt-4">
                <CardHeader><CardTitle class="text-base">Matières ({{ course.subjects.length }})</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-1">
                        <li v-for="s in course.subjects" :key="s.id" class="flex items-center justify-between text-sm">
                            <Link :href="`/subjects/${s.id}`" class="hover:underline">{{ s.name }}</Link>
                            <Badge :variant="s.is_active ? 'default' : 'secondary'" class="text-xs">{{ s.is_active ? t('label.active') : t('label.inactive') }}</Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
