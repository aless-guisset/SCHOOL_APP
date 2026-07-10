import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface User {
    id: number;
    firstname: string;
    lastname: string;
    email: string;
    phone_number: string | null;
    is_active: boolean;
    default_school_id: number | null;
}

/**
 * Composable d'authentification — lit l'utilisateur depuis usePage().props.
 * Toujours synchronisé avec les données Inertia (pas de stale state).
 *
 * Usage :
 *   const { user, isAuthenticated, fullName } = useAuth()
 */
export function useAuth() {
    const page = usePage<{ auth: { user: User | null } }>();

    const user = computed(() => page.props.auth?.user ?? null);
    const isAuthenticated = computed(() => user.value !== null);
    const fullName = computed(() => {
        if (!user.value) return '';
        return `${user.value.firstname ?? ''} ${user.value.lastname ?? ''}`.trim();
    });
    const initials = computed(() => {
        if (!user.value) return '?';
        const f = user.value.firstname?.[0] ?? '';
        const l = user.value.lastname?.[0] ?? '';
        return `${f}${l}`.toUpperCase() || '?';
    });

    return {
        user,
        isAuthenticated,
        fullName,
        initials,
    };
}
