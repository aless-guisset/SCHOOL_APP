<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, ChevronsUpDown, Star } from 'lucide-vue-next';
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
import { useSidebarNav } from '@/composables/useSidebarNav';

const page = usePage<{
    currentRole: string | null;
    school: { id: number; name: string; cantine_enabled?: boolean } | null;
    userSchools: Array<{ id: number; name: string; is_active: boolean; is_default: boolean }>;
    pendingCount: number;
    routeName: string | null;
}>();

const currentRole = computed(() => page.props.currentRole);
const activeSchool = computed(() => page.props.school);
const userSchools = computed(() => page.props.userSchools ?? []);
const pendingCount = computed(() => page.props.pendingCount ?? 0);
const hasMultipleSchools = computed(() => userSchools.value.length > 1);

// Le nav "Cantine" est déclaré statiquement dans useSidebarNav (qui n'a pas
// accès à l'état de l'école active) ; on le retire ici si le module n'est pas
// activé pour l'école active.
const navGroups = computed(() => {
    const cantineEnabled = activeSchool.value?.cantine_enabled ?? false;
    const groups = useSidebarNav(currentRole.value);

    if (cantineEnabled) return groups;

    return groups.map((group) => ({
        ...group,
        items: group.items.filter((item) => item.routeName !== 'cantine.index'),
    }));
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

            <!-- School switcher (affiché seulement si l'utilisateur a une école) -->
            <div v-if="activeSchool" class="px-2 pb-1">
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
