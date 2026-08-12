<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Calendar,
    Clock,
    GraduationCap,
    Settings2,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import RecentActivity from '@/components/dashboard/RecentActivity.vue';
import type {ActivityEntry} from '@/components/dashboard/RecentActivity.vue';
import WeekSchedule from '@/components/dashboard/WeekSchedule.vue';
import type {WeekScheduleSlot} from '@/components/dashboard/WeekSchedule.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuth } from '@/composables/useAuth';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    week_schedule: { week_start: string; slots: WeekScheduleSlot[] };
    recent_activity: ActivityEntry[] | null;
}>();

const { fullName } = useAuth();
const { activeSchool, currentRole, canManage, isAdmin } = useSchool();
const { t } = useTranslation();

const greeting = computed(() => t('dashboard.welcome', { name: fullName.value }));

// Stat cards selon le rôle
const statCards = computed(() => {
    if (isAdmin.value) {
        return [
            { label: t('nav.schools'),   icon: Building2,    href: '/schools',    color: 'text-blue-500' },
            { label: t('nav.users'),     icon: Users,        href: '/users',      color: 'text-purple-500' },
            { label: t('nav.roles'),     icon: GraduationCap, href: '/roles',     color: 'text-green-500' },
        ];
    }

    if (canManage.value) {
        return [
            { label: t('nav.sections'),  icon: GraduationCap, href: '/sections',  color: 'text-blue-500' },
            { label: t('nav.courses'),   icon: BookOpen,      href: '/courses',   color: 'text-purple-500' },
            { label: t('nav.schedules'), icon: Calendar,      href: '/schedules', color: 'text-green-500' },
            { label: t('nav.timesheets'),icon: Clock,         href: '/timesheets',color: 'text-orange-500' },
        ];
    }

    // Professeur / Élève
    return [
        { label: t('nav.schedules'),  icon: Calendar,  href: '/schedules',  color: 'text-blue-500' },
        { label: t('nav.subjects'),   icon: BookOpen,  href: '/subjects',   color: 'text-purple-500' },
        { label: t('nav.resources'),  icon: BookOpen,  href: '/resources',  color: 'text-green-500' },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <FlashMessage />

            <PageHeader :title="t('dashboard.title')" :description="greeting" />

            <!-- École active -->
            <div
                v-if="activeSchool"
                class="flex items-center gap-3 rounded-xl border border-border bg-card px-5 py-4"
            >
                <Building2 class="size-8 shrink-0 text-primary" />
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('label.school') }}</p>
                    <p class="font-semibold">{{ activeSchool.name }}</p>
                </div>
                <span
                    v-if="currentRole"
                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                >
                    {{ currentRole }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="ml-auto"
                >
                    <Link :href="`/schools/${activeSchool.id}/panel`">
                        <Settings2 class="size-4" />
                        Gérer l'école
                    </Link>
                </Button>
            </div>

            <!-- Raccourcis par rôle -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a
                    v-for="card in statCards"
                    :key="card.href"
                    :href="card.href"
                    class="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 shadow-sm transition hover:border-primary hover:shadow-md"
                >
                    <div class="flex items-center justify-between">
                        <component :is="card.icon" class="size-6" :class="card.color" />
                        <span class="text-xs text-muted-foreground opacity-0 transition group-hover:opacity-100">→</span>
                    </div>
                    <p class="text-sm font-semibold">{{ card.label }}</p>
                </a>
            </div>

            <!-- Widgets dashboard -->
            <div class="grid gap-4" :class="props.recent_activity ? 'lg:grid-cols-2' : ''">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('nav.schedules') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <WeekSchedule :week-schedule="props.week_schedule" :can-manage="canManage" />
                    </CardContent>
                </Card>

                <Card v-if="props.recent_activity">
                    <CardHeader>
                        <CardTitle class="text-base">Activité récente</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RecentActivity :activity="props.recent_activity ?? []" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
