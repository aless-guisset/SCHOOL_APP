<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Briefcase, GraduationCap, ShieldCheck, Users } from 'lucide-vue-next';
import AuthLayout from '@/layouts/AuthLayout.vue';

const roles = [
    { reference: 'ELEVE', label: 'Étudiant', icon: GraduationCap, description: 'Je rejoins ma classe' },
    { reference: 'PROF', label: 'Professeur', icon: Briefcase, description: "J'enseigne dans un établissement" },
    { reference: 'SEC', label: 'Secrétariat', icon: Users, description: 'Je gère l\'administratif' },
    { reference: 'POWER', label: 'Power User', icon: ShieldCheck, description: 'Gestion étendue de l\'école' },
];
</script>

<template>
    <Head title="Rejoindre une école" />
    <AuthLayout title="Rejoindre une école" description="Sélectionnez votre rôle pour continuer">
        <div class="grid grid-cols-2 gap-3">
            <Link
                v-for="r in roles" :key="r.reference"
                :href="r.reference === 'ELEVE' ? `/join/request?role=${r.reference}` : `/join/with-code?role=${r.reference}`"
                class="flex flex-col items-center gap-2 rounded-xl border-2 border-border px-4 py-5 text-sm font-medium text-muted-foreground transition-all hover:border-primary/50 hover:text-foreground"
            >
                <component :is="r.icon" class="size-7" />
                <span>{{ r.label }}</span>
                <span class="text-center text-xs font-normal opacity-70">{{ r.description }}</span>
            </Link>
        </div>
        <p class="mt-6 text-center text-sm text-muted-foreground">
            Vous fondez un nouvel établissement ?
            <Link href="/register" class="underline underline-offset-4">Créer une école</Link>
        </p>
    </AuthLayout>
</template>
