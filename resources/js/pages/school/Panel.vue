<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Calendar,
    Clock,
    DoorOpen,
    FileText,
    GraduationCap,
    Library,
    ScrollText,
    Settings,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
const { currentRole, canManage, isAdmin, hasRole } = useSchool();

interface UpcomingSchedule {
    id: number;
    name: string;
    day: number;
    start_time: string;
    end_time: string;
    course: string | null;
}

const props = defineProps<{
    school: { id: number; name: string; email: string | null; phone_number: string | null; address: string | null; is_active: boolean };
    stats: { users: number; sections: number; courses: number; classrooms: number; schedules: number; timesheets: number };
    upcomingSchedules: UpcomingSchedule[];
}>();

const DAYS: Record<number, string> = { 1: 'Lun', 2: 'Mar', 3: 'Mer', 4: 'Jeu', 5: 'Ven', 6: 'Sam', 7: 'Dim' };

const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: props.school.name },
];

// Modules visibles selon le rôle
const modules = computed(() => {
    const all = [
        // Toujours visible
        { label: 'Horaires',         icon: Calendar,      href: '/schedules',       color: 'bg-blue-500/10 text-blue-500',    roles: ['all'] },
        { label: 'Feuilles de temps',icon: Clock,         href: '/timesheets',      color: 'bg-orange-500/10 text-orange-500', roles: ['all'] },
        { label: 'Ressources',       icon: FileText,      href: '/resources',       color: 'bg-green-500/10 text-green-500',   roles: ['all'] },
        // Gestion
        { label: 'Utilisateurs',     icon: Users,         href: '/users',           color: 'bg-purple-500/10 text-purple-500', roles: ['manage'] },
        { label: 'Sections',         icon: GraduationCap, href: '/sections',        color: 'bg-cyan-500/10 text-cyan-500',     roles: ['manage'] },
        { label: 'Cours',            icon: BookOpen,      href: '/courses',         color: 'bg-indigo-500/10 text-indigo-500', roles: ['manage'] },
        { label: 'Matières',         icon: Library,       href: '/subjects',        color: 'bg-pink-500/10 text-pink-500',     roles: ['manage'] },
        { label: 'Salles',           icon: DoorOpen,      href: '/classrooms',      color: 'bg-yellow-500/10 text-yellow-500', roles: ['manage'] },
        // Admin uniquement
        { label: 'Rôles',            icon: ScrollText,    href: '/roles',           color: 'bg-red-500/10 text-red-500',       roles: ['admin'] },
        { label: 'Config école',     icon: Settings,      href: `/schools/${props.school.id}/edit`, color: 'bg-gray-500/10 text-gray-400', roles: ['admin'] },
    ];

    return all.filter(mod => {
        if (mod.roles.includes('all')) return true;
        if (mod.roles.includes('manage') && canManage.value) return true;
        if (mod.roles.includes('admin') && isAdmin.value) return true;
        return false;
    });
});

const statCards = computed(() => [
    { label: 'Utilisateurs',   value: props.stats.users,      icon: Users,         show: canManage.value },
    { label: 'Sections',       value: props.stats.sections,   icon: GraduationCap, show: canManage.value },
    { label: 'Cours',          value: props.stats.courses,    icon: BookOpen,      show: canManage.value },
    { label: 'Salles',         value: props.stats.classrooms, icon: DoorOpen,      show: canManage.value },
    { label: 'Créneaux',       value: props.stats.schedules,  icon: Calendar,      show: true },
    { label: 'Feuilles',       value: props.stats.timesheets, icon: Clock,         show: true },
].filter(s => s.show));
</script>

<template>
    <Head :title="school.name" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <FlashMessage />

            <!-- Header -->
            <PageHeader :title="school.name" :description="`Panel de gestion — ${currentRole}`" :breadcrumbs="breadcrumbs">
                <template v-if="isAdmin" #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/schools/${school.id}/edit`">
                            <Settings class="size-4" />
                            Modifier
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Infos école -->
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card px-5 py-3">
                <Building2 class="size-7 shrink-0 text-primary" />
                <div>
                    <p class="font-semibold">{{ school.name }}</p>
                    <p v-if="school.email" class="text-xs text-muted-foreground">{{ school.email }}</p>
                </div>
                <Badge :variant="school.is_active ? 'default' : 'secondary'" class="ml-auto">
                    {{ school.is_active ? 'Active' : 'Inactive' }}
                </Badge>
            </div>

            <!-- Stats -->
            <div v-if="statCards.length" class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    v-for="stat in statCards"
                    :key="stat.label"
                    class="flex flex-col gap-1 rounded-xl border border-border bg-card p-4"
                >
                    <component :is="stat.icon" class="size-5 text-muted-foreground" />
                    <p class="text-2xl font-bold">{{ stat.value }}</p>
                    <p class="text-xs text-muted-foreground">{{ stat.label }}</p>
                </div>
            </div>

            <!-- Modules -->
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Modules</p>
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    <Link
                        v-for="mod in modules"
                        :key="mod.href"
                        :href="mod.href"
                        class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 transition hover:border-primary hover:shadow-sm"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg" :class="mod.color">
                            <component :is="mod.icon" class="size-5" />
                        </div>
                        <span class="text-sm font-medium">{{ mod.label }}</span>
                        <span class="ml-auto text-muted-foreground opacity-0 transition group-hover:opacity-100">→</span>
                    </Link>
                </div>
            </div>

            <!-- Prochains créneaux -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Calendar class="size-4 text-primary" />
                        Prochains créneaux
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="upcomingSchedules.length" class="divide-y divide-border">
                        <div
                            v-for="schedule in upcomingSchedules"
                            :key="schedule.id"
                            class="flex items-center gap-4 py-3"
                        >
                            <!-- Jour -->
                            <div class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-primary/10">
                                <span class="text-[10px] font-bold uppercase text-primary">{{ DAYS[schedule.day] }}</span>
                            </div>
                            <!-- Infos -->
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-sm font-medium">{{ schedule.name }}</p>
                                <p v-if="schedule.course" class="truncate text-xs text-muted-foreground">{{ schedule.course }}</p>
                            </div>
                            <!-- Horaire -->
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold tabular-nums">{{ schedule.start_time }}</p>
                                <p class="text-xs text-muted-foreground">→ {{ schedule.end_time }}</p>
                            </div>
                            <!-- Lien -->
                            <Link :href="`/schedules/${schedule.id}`" class="shrink-0 text-xs text-primary hover:underline">
                                Voir
                            </Link>
                        </div>
                    </div>
                    <div v-else class="flex h-20 items-center justify-center rounded-lg border border-dashed border-border">
                        <p class="text-sm text-muted-foreground">Aucun créneau planifié.</p>
                    </div>

                    <div class="mt-3 text-right">
                        <Link href="/schedules" class="text-xs text-primary hover:underline">
                            Voir tous les horaires →
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
