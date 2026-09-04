import { ref } from 'vue';

export interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
}

/**
 * Ref au niveau du module, pas dans un composant : chaque page de l'app
 * enveloppe son propre <AppLayout> (pas de layout persistant Inertia), donc
 * DownloadAppButton est démonté/remonté à chaque navigation. Chrome ne
 * déclenche beforeinstallprompt qu'une seule fois par chargement de page
 * réel — un état local au composant perdait cet événement capturé dès la
 * première navigation vers une autre page, forçant le bouton sur le message
 * de repli pour le reste de la session même après un rechargement complet.
 * Un ref/listener au niveau du module survit à ces montages/démontages
 * puisque le module JS n'est évalué qu'une fois par chargement de page réel,
 * comme registerSW() dans app.ts.
 */
const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (event: Event) => {
        event.preventDefault();
        deferredPrompt.value = event as BeforeInstallPromptEvent;
    });
}

export function usePwaInstall() {
    return { deferredPrompt };
}
