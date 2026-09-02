<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// Un seul mécanisme, pas de détection par OS : si beforeinstallprompt s'est
// déclenché (Chrome/Edge, mobile et desktop), on propose le prompt natif.
// Sinon (Safari iOS, Firefox desktop — l'événement n'existe pas), on affiche
// des instructions manuelles génériques.
interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
}

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const isStandalone = ref(window.matchMedia('(display-mode: standalone)').matches);
const instructionsOpen = ref(false);

function handleBeforeInstallPrompt(event: Event) {
    event.preventDefault();
    deferredPrompt.value = event as BeforeInstallPromptEvent;
}

window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);

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
    <Button v-if="!isStandalone" variant="ghost" size="icon" @click="handleClick">
        <Download class="size-5" />
        <span class="sr-only">Télécharger l'application</span>
    </Button>

    <Dialog v-model:open="instructionsOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Installer l'application</DialogTitle>
                <DialogDescription>
                    Ouvrez le menu de partage ou d'options de votre navigateur, puis choisissez
                    « Ajouter à l'écran d'accueil » (ou « Installer l'application »).
                </DialogDescription>
            </DialogHeader>
        </DialogContent>
    </Dialog>
</template>
