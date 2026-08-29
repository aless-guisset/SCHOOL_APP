<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

// Emplacement réservé pour le futur téléchargement de l'app (mobile/desktop) :
// bouton désactivé en attendant que la fonctionnalité soit prête côté backend.
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <Tooltip>
            <TooltipTrigger as-child>
                <span tabindex="0">
                    <Button variant="ghost" size="icon" disabled class="pointer-events-none opacity-50">
                        <Download class="size-5" />
                        <span class="sr-only">Télécharger l'application</span>
                    </Button>
                </span>
            </TooltipTrigger>
            <TooltipContent>Pas disponible pour le moment</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
