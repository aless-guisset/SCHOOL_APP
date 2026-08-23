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
    section: {
        id: number;
        name: string;
        reference: string | null;
        description: string | null;
        is_active: boolean;
        section_users: Array<{
            id: number;
            user_school_role: { user: { firstname: string; lastname: string } } | null;
        }>;
    };
}>();

const breadcrumbs = [
    { label: 'Sections', href: '/sections' },
    { label: props.section.name },
];

function destroy() {
    if (confirm('Supprimer cette section ?')) router.delete(`/sections/${props.section.id}`);
}
</script>

<template>
    <Head :title="section.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="section.name" :breadcrumbs="breadcrumbs">
                <template v-if="canManage" #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/sections/${section.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
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
                            <dd><Badge :variant="section.is_active ? 'default' : 'secondary'">{{ section.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div v-if="section.reference" class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.reference') }}</dt>
                            <dd>{{ section.reference }}</dd>
                        </div>
                        <div v-if="section.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ section.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="section.section_users?.length" class="mt-4">
                <CardHeader><CardTitle class="text-base">Membres ({{ section.section_users.length }})</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-1 text-sm">
                        <li v-for="su in section.section_users" :key="su.id">
                            {{ su.user_school_role?.user.lastname }} {{ su.user_school_role?.user.firstname }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
