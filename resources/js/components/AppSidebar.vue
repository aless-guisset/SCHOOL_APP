<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Calendar, ChevronsUpDown, Home, NotebookText, Star, UserRound, Utensils } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useSchool } from '@/composables/useSchool';
import { useSidebarNav } from '@/composables/useSidebarNav';

const { isAdmin } = useSchool();

const page = usePage<{
    currentRole: string | null;
    school: { id: number; name: string; cantine_enabled?: boolean } | null;
    userSchools: Array<{ id: number; name: string; is_active: boolean; is_default: boolean }>;
    pendingCount: number;
    accessRequestsPendingCount: number;
    routeName: string | null;
    myChildren: Array<{ id: number; name: string; is_active: boolean }>;
    hasParentAccess: boolean;
}>();

const currentRole = computed(() => page.props.currentRole);
const activeSchool = computed(() => page.props.school);
const userSchools = computed(() => page.props.userSchools ?? []);
const pendingCount = computed(() => page.props.pendingCount ?? 0);
const accessRequestsPendingCount = computed(() => page.props.accessRequestsPendingCount ?? 0);
const hasMultipleSchools = computed(() => userSchools.value.length > 1);
const myChildren = computed(() => page.props.myChildren ?? []);
const hasMultipleChildren = computed(() => myChildren.value.length > 1);
const hasParentAccess = computed(() => page.props.hasParentAccess ?? false);

function switchChild(linkId: number) {
    router.post('/my-children/activate', { link_id: linkId });
}

// Le nav "Cantine" est déclaré statiquement dans useSidebarNav (qui n'a pas
// accès à l'état de l'école active) ; on le retire ici si le module n'est pas
// activé pour l'école active.
const navGroups = computed(() => {
    const cantineEnabled = activeSchool.value?.cantine_enabled ?? false;
    let groups = useSidebarNav(currentRole.value);

    if (!cantineEnabled) {
        groups = groups.map((group) => ({
            ...group,
            items: group.items.filter((item) => item.routeName !== 'cantine.index' && item.routeName !== 'cantine.wallet.index'),
        }));
    }

    // Double rôle : le rôle principal (currentRole) pilote la nav normale ;
    // si l'utilisateur a AUSSI un rôle Parent actif, on ajoute une section
    // dédiée pointant vers les mêmes pages avec ?as_parent=1 — jamais un
    // remplacement de la nav existante, toujours un ajout.
    if (hasParentAccess.value && currentRole.value !== 'Parent') {
        groups = [
            ...groups,
            {
                section: 'Mes enfants',
                items: [
                    { label: 'Accueil', icon: Home, route: '/dashboard?as_parent=1', routeName: 'dashboard' },
                    { label: 'Horaire', icon: Calendar, route: '/schedules?as_parent=1', routeName: 'schedules.index' },
                    { label: 'Notes', icon: NotebookText, route: '/grades?as_parent=1', routeName: 'grades.index' },
                    ...(cantineEnabled
                        ? [{ label: 'Cantine', icon: Utensils, route: '/cantine?as_parent=1', routeName: 'cantine.index' }]
                        : []),
                ],
            },
        ];
    }

    return groups;
});

function switchSchool(schoolId: number) {
    router.post('/school/activate', { school_id: schoolId });
}

function setDefault(schoolId: number, event: Event) {
    event.stopPropagation();
    router.post('/school/set-default', { school_id: schoolId });
}

function isCurrentRoute(item: { route: string; routeName?: string }): boolean {
    const currentRouteName = page.props.routeName;
    if (item.routeName && currentRouteName) {
        return item.routeName === currentRouteName;
    }
    return window.location.pathname === item.route;
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <!-- ── Header : Logo + School switcher ──────────────────────── -->
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- School switcher : jamais pour l'Administrateur, qui n'a pas d'autorité
                 sur le contenu académique d'une école en particulier (CLAUDE.md) —
                 même s'il possède une ligne UserSchoolRole (role Administrateur)
                 rattachée à une school_id précise, ce n'est pas une école qu'il gère. -->
            <div v-if="activeSchool && !isAdmin" class="px-2 pb-1">
                <DropdownMenu v-if="hasMultipleSchools">
                    <DropdownMenuTrigger as-child>
                        <button
                            class="flex w-full items-center gap-2 rounded-md border border-sidebar-border bg-sidebar-accent px-3 py-2 text-sm font-medium text-sidebar-accent-foreground transition hover:bg-sidebar-accent/80"
                        >
                            <Building2 class="size-4 shrink-0 text-sidebar-primary" />
                            <span class="flex-1 truncate text-left text-xs">{{ activeSchool.name }}</span>
                            <ChevronsUpDown class="size-3 shrink-0 text-muted-foreground" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-52">
                        <DropdownMenuItem
                            v-for="school in userSchools"
                            :key="school.id"
                            class="flex cursor-pointer items-center gap-2"
                            :class="{ 'font-semibold text-primary': school.is_active }"
                            @click="switchSchool(school.id)"
                        >
                            <Building2 class="size-4 shrink-0" />
                            <span class="flex-1 truncate text-xs">{{ school.name }}</span>
                            <button
                                class="ml-auto text-muted-foreground hover:text-yellow-400"
                                :class="{ 'text-yellow-400': school.is_default }"
                                title="Définir par défaut"
                                @click="setDefault(school.id, $event)"
                            >
                                <Star class="size-3" />
                            </button>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link href="/school/create" class="text-xs text-muted-foreground">
                                + Rejoindre un autre établissement
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Une seule école : affichage simple -->
                <div
                    v-else
                    class="flex items-center gap-2 rounded-md border border-sidebar-border bg-sidebar-accent px-3 py-2"
                >
                    <Building2 class="size-4 shrink-0 text-sidebar-primary" />
                    <span class="truncate text-xs font-medium text-sidebar-accent-foreground">{{ activeSchool.name }}</span>
                </div>
            </div>

            <!-- Sélecteur d'enfant : uniquement si l'utilisateur a un rôle Parent
                 actif à cette école et plus d'un enfant lié (sinon rien à choisir). -->
            <div v-if="hasMultipleChildren" class="px-2 pb-1">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="flex w-full items-center gap-2 rounded-md border border-sidebar-border bg-sidebar-accent px-3 py-2 text-sm font-medium text-sidebar-accent-foreground transition hover:bg-sidebar-accent/80"
                        >
                            <UserRound class="size-4 shrink-0 text-sidebar-primary" />
                            <span class="flex-1 truncate text-left text-xs">
                                {{ myChildren.find((c) => c.is_active)?.name ?? myChildren[0]?.name }}
                            </span>
                            <ChevronsUpDown class="size-3 shrink-0 text-muted-foreground" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-52">
                        <DropdownMenuItem
                            v-for="child in myChildren"
                            :key="child.id"
                            class="flex cursor-pointer items-center gap-2"
                            :class="{ 'font-semibold text-primary': child.is_active }"
                            @click="switchChild(child.id)"
                        >
                            <UserRound class="size-4 shrink-0" />
                            <span class="flex-1 truncate text-xs">{{ child.name }}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </SidebarHeader>

        <!-- ── Content : Navigation par rôle ────────────────────────── -->
        <SidebarContent>
            <SidebarGroup
                v-for="group in navGroups"
                :key="group.section"
                class="px-2 py-0"
            >
                <SidebarGroupLabel>{{ group.section }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in group.items" :key="item.label">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentRoute(item)"
                            :tooltip="item.label"
                        >
                            <Link :href="item.route">
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuBadge
                            v-if="item.route === '/schools/pending' && pendingCount > 0"
                            class="bg-amber-500 text-white"
                        >
                            {{ pendingCount }}
                        </SidebarMenuBadge>
                        <SidebarMenuBadge
                            v-if="item.route === '/access-requests' && accessRequestsPendingCount > 0"
                            class="bg-amber-500 text-white"
                        >
                            {{ accessRequestsPendingCount }}
                        </SidebarMenuBadge>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <!-- ── Footer : User ────────────────────────────────────────── -->
        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
