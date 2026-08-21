import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Retourne un ref réactif reflétant l'état d'une media query CSS.
 * Usage : const isMobile = useMediaQuery('(max-width: 639px)')
 *
 * Codé à la main plutôt que d'utiliser le `useMediaQuery` de @vueuse/core :
 * cette implémentation évalue `matchMedia` de façon synchrone pendant le setup,
 * ce qui serait en désaccord avec le rendu initial du serveur maintenant que le
 * SSR est activé (config/inertia.php) et provoquerait un hydration mismatch.
 * Différer l'évaluation à `onMounted` garde le premier rendu serveur et client
 * en accord.
 */
export function useMediaQuery(query: string) {
    const matches = ref(false);
    let mql: MediaQueryList | null = null;

    function update() {
        matches.value = mql?.matches ?? false;
    }

    onMounted(() => {
        mql = window.matchMedia(query);
        update();
        mql.addEventListener('change', update);
    });

    onBeforeUnmount(() => {
        mql?.removeEventListener('change', update);
    });

    return matches;
}
