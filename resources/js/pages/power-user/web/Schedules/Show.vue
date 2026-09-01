<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ViewingChildBanner from '@/components/ViewingChildBanner.vue';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
const { canManage } = useSchool();

const DAYS: Record<number, string> = { 1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi', 5: 'Vendredi', 6: 'Samedi', 7: 'Dimanche' };

const props = defineProps<{
    schedule: {
        id: number;
        name: string;
        day_of_week: number;
        start_time: string;
        end_time: string;
        is_active: boolean;
        section_course: { name: string; course: { name: string } } | null;
        timesheets: Array<{ id: number; date: string; hours_done: number; user_school_role: { user: { lastname: string; firstname: string } } | null }>;
    };
    viewing_child?: string | null;
}>();

// as_parent=1 doit survivre au retour vers la liste : même pattern que
// Schedules/Index.vue — sans lui, l'appelant à double rôle retomberait
// silencieusement sur sa vue enseignante sans que rien ne le signale.
const page = usePage();
const asParent = computed(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('as_parent'));

// Les actions de gestion n'ont aucun sens sur l'horaire d'un enfant consulté
// via "Mes enfants", même si le rôle réel de l'appelant y donne droit ailleurs.
const canWrite = computed(() => canManage.value && !props.viewing_child);

const breadcrumbs = computed(() => [
    { label: t('nav.schedules'), href: asParent.value ? `/schedules?as_parent=${asParent.value}` : '/schedules' },
    { label: props.schedule.name },
]);

function destroy() {
    if (confirm('Supprimer ce créneau ?')) router.delete(`/schedules/${props.schedule.id}`);
}
</script>

<template>
    <Head :title="schedule.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <ViewingChildBanner v-if="viewing_child" :name="viewing_child" />
            <PageHeader :title="schedule.name" :breadcrumbs="breadcrumbs">
                <template v-if="canWrite" #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/schedules/${schedule.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
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
                            <dd><Badge :variant="schedule.is_active ? 'default' : 'secondary'">{{ schedule.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Jour</dt>
                            <dd>{{ DAYS[schedule.day_of_week] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Horaire</dt>
                            <dd>{{ schedule.start_time.substring(0, 5) }} – {{ schedule.end_time.substring(0, 5) }}</dd>
                        </div>
                        <div v-if="schedule.section_course" class="flex justify-between">
                            <dt class="text-muted-foreground">Section/Cours</dt>
                            <dd>{{ schedule.section_course.name }} — {{ schedule.section_course.course?.name }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="schedule.timesheets?.length" class="mt-4">
                <CardHeader><CardTitle class="text-base">Feuilles de temps ({{ schedule.timesheets.length }})</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-1 text-sm">
                        <li v-for="ts in schedule.timesheets" :key="ts.id" class="flex items-center justify-between">
                            <Link :href="`/timesheets/${ts.id}`" class="hover:underline">{{ ts.date }}</Link>
                            <span class="text-muted-foreground">
                                {{ ts.user_school_role?.user.lastname }} {{ ts.user_school_role?.user.firstname }}
                                · {{ ts.hours_done }}h
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
