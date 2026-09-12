<script setup lang="ts">
import { Download, Share } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

// Mécanisme principal : si beforeinstallprompt s'est déclenché (Chrome/Edge,
// mobile et desktop), on propose le prompt natif. Sinon (Safari iOS/macOS,
// Firefox desktop — l'événement n'existe sur aucun de ces navigateurs, et
// aucune API web ne permet de déclencher une installation programmatique sur
// Safari : c'est une restriction volontaire d'Apple, pas quelque chose de
// contournable côté code), on affiche des instructions manuelles — adaptées
// à Safari iOS/macOS spécifiquement puisque ce sont nos deux cas connus les
// plus fréquents, génériques sinon (Firefox desktop, etc.).
declare global {
    interface Window {
        Capacitor?: { isNativePlatform?: () => boolean };
    }
    interface Navigator {
        // API récente, absente des types DOM standard — cf. related_applications
        // dans le manifest (vite.config.ts) pour la config qui la rend utilisable.
        getInstalledRelatedApps?: () => Promise<unknown[]>;
    }
}

// deferredPrompt vit au niveau du module (usePwaInstall), pas ici : chaque
// page enveloppe son propre <AppLayout> (pas de layout persistant Inertia),
// donc ce composant est démonté/remonté à chaque navigation — un état local
// perdait l'événement capturé dès la première navigation vers une autre
// page, forçant le message de repli pour le reste de la session.
const { deferredPrompt } = usePwaInstall();
const isStandalone = typeof window !== 'undefined' && window.matchMedia('(display-mode: standalone)').matches;
const isNativePlatform = typeof window !== 'undefined' && window.Capacitor?.isNativePlatform?.() === true;
const instructionsOpen = ref(false);

// Détecte une installation existante — soit synchronement (isStandalone :
// on tourne littéralement DANS l'app installée en ce moment, pas besoin
// d'attendre l'API async pour le savoir), soit via
// navigator.getInstalledRelatedApps() pour le cas moins évident, consulté
// depuis un onglet normal alors que l'app est installée ailleurs sur
// l'appareil : le menu ⋮ du navigateur peut le savoir sans que notre JS le
// sache par défaut. Le bouton reste affiché dans les deux cas (pas caché) —
// cliquer dessus affiche l'alerte "déjà installée" au lieu du dialogue
// d'instructions habituel. Async, seulement sur Chrome/Edge — pas de faux
// négatif gênant sur Safari/Firefox, ils passent simplement par les
// branches existantes du dialogue de repli.
const isAlreadyInstalled = ref(isStandalone);
onMounted(async () => {
    if (isAlreadyInstalled.value || typeof navigator === 'undefined' || !navigator.getInstalledRelatedApps) {
        return;
    }
    const related = await navigator.getInstalledRelatedApps();
    isAlreadyInstalled.value = related.length > 0;
});

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
// Chrome/Edge/Opera supportent beforeinstallprompt en général, mais sa
// disponibilité réelle (SW actif+contrôlant la page, timing interne à
// Chrome) s'est révélée peu fiable en test réel — le menu ⋮ natif du
// navigateur propose "Installer l'application" indépendamment de cet
// événement JS, de façon bien plus fiable. Le message pointe donc vers ce
// menu natif plutôt que de dépendre de la capture de l'événement, plutôt
// que d'afficher les instructions génériques (fausses ici : Chrome n'a pas
// de "menu de partage").
const isChromiumInstallable = typeof navigator !== 'undefined' && !isIOS && !isSafari &&
    /Chrome|Chromium|Edg|OPR|SamsungBrowser/i.test(navigator.userAgent);

async function handleClick() {
    if (isAlreadyInstalled.value) {
        instructionsOpen.value = true;
        return;
    }

    if (deferredPrompt.value) {
        await deferredPrompt.value.prompt();
        deferredPrompt.value = null;
        return;
    }

    instructionsOpen.value = true;
}
</script>

<template>
    <Button v-if="!isNativePlatform" variant="ghost" size="icon" @click="handleClick">
        <Download class="size-5" />
        <span class="sr-only">{{ t('pwa.download_app_sr') }}</span>
    </Button>

    <Dialog v-model:open="instructionsOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('pwa.install_title') }}</DialogTitle>
                <DialogDescription v-if="isAlreadyInstalled">
                    {{ t('pwa.already_installed') }}
                </DialogDescription>
                <DialogDescription v-else-if="isIOS" class="flex items-start gap-2">
                    <Share class="mt-0.5 size-4 shrink-0" />
                    <span>{{ t('pwa.instructions_ios') }}</span>
                </DialogDescription>
                <DialogDescription v-else-if="isSafari" class="flex items-start gap-2">
                    <Share class="mt-0.5 size-4 shrink-0" />
                    <span>{{ t('pwa.instructions_safari') }}</span>
                </DialogDescription>
                <DialogDescription v-else-if="isChromiumInstallable" class="flex items-start gap-2">
                    <Share class="mt-0.5 size-4 shrink-0" />
                    <span>{{ t('pwa.instructions_chromium') }}</span>
                </DialogDescription>
                <DialogDescription v-else>
                    {{ t('pwa.instructions_generic') }}
                </DialogDescription>
            </DialogHeader>
        </DialogContent>
    </Dialog>
</template>
