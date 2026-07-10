import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface School {
    id: number;
    name: string;
}

interface UserSchool {
    id: number;
    name: string;
    is_active: boolean;
    is_default: boolean;
}

/**
 * Composable école active — lit le contexte école depuis usePage().props.
 * Toujours synchronisé avec les données Inertia.
 *
 * Usage :
 *   const { activeSchool, currentRole, userSchools, switchSchool } = useSchool()
 */
export function useSchool() {
    const page = usePage<{
        school: School | null;
        currentRole: string | null;
        userSchools: UserSchool[];
    }>();

    const activeSchool = computed(() => page.props.school ?? null);
    const currentRole = computed(() => page.props.currentRole ?? null);
    const userSchools = computed(() => page.props.userSchools ?? []);
    const hasMultipleSchools = computed(() => userSchools.value.length > 1);
    const hasSchool = computed(() => activeSchool.value !== null);

    /**
     * Bascule vers une autre école (POST /school/activate).
     */
    function switchSchool(schoolId: number) {
        router.post('/school/activate', { school_id: schoolId });
    }

    /**
     * Définit l'école par défaut (POST /school/set-default).
     */
    function setDefaultSchool(schoolId: number) {
        router.post('/school/set-default', { school_id: schoolId });
    }

    /**
     * Vérifie si l'utilisateur a le rôle donné dans l'école active.
     */
    function hasRole(...roles: string[]): boolean {
        return currentRole.value !== null && roles.includes(currentRole.value);
    }

    /**
     * Vérifie si l'utilisateur est admin de plateforme.
     */
    const isAdmin = computed(() => hasRole('Administrateur'));

    /**
     * Vérifie si l'utilisateur est power user ou admin.
     */
    const canManage = computed(() => hasRole('Administrateur', 'Power User', 'Directeur'));

    return {
        activeSchool,
        currentRole,
        userSchools,
        hasMultipleSchools,
        hasSchool,
        isAdmin,
        canManage,
        switchSchool,
        setDefaultSchool,
        hasRole,
    };
}
