<script setup lang="ts">
import { Download, Share } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// Mécanisme principal : si beforeinstallprompt s'est déclenché (Chrome/Edge,
// mobile et desktop), on propose le prompt natif. Sinon (Safari iOS/macOS,
// Firefox desktop — l'événement n'existe sur aucun de ces navigateurs, et
// aucune API web ne permet de déclencher une installation programmatique sur
// Safari : c'est une restriction volontaire d'Apple, pas quelque chose de
// contournable côté code), on affiche des instructions manuelles — adaptées
// à Safari iOS/macOS spécifiquement puisque ce sont nos deux cas connus les
// plus fréquents, génériques sinon (Firefox desktop, etc.).
interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
}

declare global {
    interface Window {
        Capacitor?: { isNativePlatform?: () => boolean };
    }
}

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const isStandalone = ref(
    typeof window !== 'undefined' && window.matchMedia('(display-mode: standalone)').matches,
);
const isNativePlatform = typeof window !== 'undefined' && window.Capacitor?.isNativePlatform?.() === true;
const instructionsOpen = ref(false);

// iPadOS 13+ se présente comme "MacIntel" dans l'UA — le tactile est le seul
// signal fiable pour le distinguer d'un vrai Mac.
const isIOS = typeof navigator !== 'undefined' && (
    /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
);
// Exclut les navigateurs basés sur Chromium/Gecko qui incluent "Safari" dans
// leur UA par convention historique (Chrome, Edge, et sur iOS — Chrome/
// Firefox y tournent tous sur le moteur WebKit de Safari, même limitation).
const isSafari = typeof navigator !== 'undefined' &&
    /^((?!chrome|android|crios|fxios|edg).)*safari/i.test(navigator.userAgent);
// Chrome/Edge/Opera supportent beforeinstallprompt en général, mais Chrome ne
// le déclenche que si CETTE navigation s'est faite sous le contrôle d'un
// service worker déjà actif — impossible dès la toute première visite (ou
// juste après une désinstallation, qui semble réinitialiser ce contrôle) :
// le service worker n'existe pas encore au moment où la page se charge, quoi
// que fassent skipWaiting/clientsClaim une fois qu'il existe. Un rechargement
// est donc requis, une fois, dans ce cas précis — contrairement à Safari, ce
// n'est pas permanent, donc le message le dit plutôt que d'afficher les
// instructions génériques (fausses ici : Chrome n'a pas de "menu de partage").
const isChromiumInstallable = typeof navigator !== 'undefined' && !isIOS && !isSafari &&
    /Chrome|Chromium|Edg|OPR|SamsungBrowser/i.test(navigator.userAgent);

function handleBeforeInstallPrompt(event: Event) {
    event.preventDefault();
    deferredPrompt.value = event as BeforeInstallPromptEvent;
}

onMounted(() => {
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
});

async function handleClick() {
    if (deferredPrompt.value) {
        await deferredPrompt.value.prompt();
        deferredPrompt.value = null;
        return;
    }

    instructionsOpen.value = true;
}
</script>

<template>
    <Button v-if="!isStandalone && !isNativePlatform" variant="ghost" size="icon" @click="handleClick">
        <Download class="size-5" />
        <span class="sr-only">Télécharger l'application</span>
    </Button>

    <Dialog v-model:open="instructionsOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Installer l'application</DialogTitle>
                <DialogDescription v-if="isIOS" class="flex items-start gap-2">
                    <Share class="mt-0.5 size-4 shrink-0" />
                    <span>
                        Appuyez sur l'icône <strong>Partager</strong> en bas de l'écran (ou en haut,
                        selon votre navigateur), puis choisissez « <strong>Sur l'écran d'accueil</strong> ».
                    </span>
                </DialogDescription>
                <DialogDescription v-else-if="isSafari" class="flex items-start gap-2">
                    <Share class="mt-0.5 size-4 shrink-0" />
                    <span>
                        Cliquez sur l'icône <strong>Partager</strong> dans la barre d'outils Safari,
                        à côté de la barre d'adresse, puis choisissez « <strong>Ajouter au Dock</strong> ».
                    </span>
                </DialogDescription>
                <DialogDescription v-else-if="isChromiumInstallable">
                    L'installation n'est pas encore prête sur cette page — rechargez la page
                    (F5) puis réessayez. Ça ne se produit qu'une fois, à la première visite ou
                    juste après une désinstallation.
                </DialogDescription>
                <DialogDescription v-else>
                    Ouvrez le menu de partage ou d'options de votre navigateur, puis choisissez
                    « Ajouter à l'écran d'accueil » (ou « Installer l'application »).
                </DialogDescription>
            </DialogHeader>
        </DialogContent>
    </Dialog>
</template>
