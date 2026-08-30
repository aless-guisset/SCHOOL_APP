<script setup lang="ts">
import { Users } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';

/**
 * Bandeau affiché sur les pages consultées via ?as_parent=1 (double rôle) :
 * la prop partagée currentRole reste celle du rôle réel de l'utilisateur, ce
 * bandeau est donc le seul repère indiquant que les données affichées sont
 * celles de son enfant et non les siennes.
 */
defineProps<{ name: string }>();
</script>

<template>
    <Alert class="mb-4 border-primary/30 bg-primary/10">
        <Users class="size-4" />
        <AlertDescription class="text-foreground">
            Vous consultez les informations de <span class="font-medium">{{ name }}</span>.
        </AlertDescription>
    </Alert>
</template>
