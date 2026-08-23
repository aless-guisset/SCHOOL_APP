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

const DAYS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

const props = defineProps<{
    sectionCourse: {
        id: number;
        name: string;
        description: string | null;
        total_hours: number;
        hours_per_session: number;
        is_active: boolean;
        course: { id: number; name: string } | null;
        section_user: { section: { id: number; name: string } | null } | null;
        schedules: Array<{ id: number; day_of_week: number; start_time: string; end_time: string }>;
    };
    hoursPlanned: number;
    hoursConsumed: number;
    hoursRemaining: number;
    completion: number;
}>();

const breadcrumbs = [
    { label: 'Associations section-cours', href: '/section-courses' },
    { label: props.sectionCourse.name },
];

function destroy() {
    if (confirm('Supprimer cette association ?')) router.delete(`/section-courses/${props.sectionCourse.id}`);
}
</script>

<template>
    <Head :title="sectionCourse.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="sectionCourse.name" :breadcrumbs="breadcrumbs">
                <template v-if="canManage" #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/section-courses/${sectionCourse.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
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
                            <dd><Badge :variant="sectionCourse.is_active ? 'default' : 'secondary'">{{ sectionCourse.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Cours</dt>
                            <dd>{{ sectionCourse.course?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Section</dt>
                            <dd>{{ sectionCourse.section_user?.section?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Heures planifiées / consommées / restantes</dt>
                            <dd>{{ hoursPlanned }}h / {{ hoursConsumed }}h / {{ hoursRemaining }}h ({{ completion }}%)</dd>
                        </div>
                        <div v-if="sectionCourse.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ sectionCourse.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="sectionCourse.schedules?.length" class="mt-4">
                <CardHeader><CardTitle class="text-base">Créneaux ({{ sectionCourse.schedules.length }})</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-1">
                        <li v-for="s in sectionCourse.schedules" :key="s.id" class="flex items-center justify-between text-sm">
                            <span>{{ DAYS[s.day_of_week] }}</span>
                            <span class="text-muted-foreground">{{ s.start_time }} — {{ s.end_time }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
