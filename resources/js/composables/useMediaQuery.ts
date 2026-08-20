import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Retourne un ref réactif reflétant l'état d'une media query CSS.
 * Usage : const isMobile = useMediaQuery('(max-width: 639px)')
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
