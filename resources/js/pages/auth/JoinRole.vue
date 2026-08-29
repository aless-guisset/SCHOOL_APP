<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Briefcase, GraduationCap, HeartHandshake, ShieldCheck, Users, type LucideIcon } from 'lucide-vue-next';
import JoinAuthLayout from '@/layouts/JoinAuthLayout.vue';

// `wide` : la carte occupe toute la largeur de la grille (2 colonnes) — utilisé
// pour le dernier parcours, qui n'a pas de voisin sur sa ligne.
type RoleCard = {
    reference: string;
    label: string;
    icon: LucideIcon;
    description: string;
    href: string;
    wide?: boolean;
};

const roles: RoleCard[] = [
    { reference: 'ELEVE', label: 'Étudiant', icon: GraduationCap, description: 'Je rejoins ma classe', href: '/join/request?role=ELEVE' },
    { reference: 'PROF', label: 'Professeur', icon: Briefcase, description: "J'enseigne dans un établissement", href: '/join/with-code?role=PROF' },
    { reference: 'SEC', label: 'Secrétariat', icon: Users, description: 'Je gère l\'administratif', href: '/join/with-code?role=SEC' },
    { reference: 'POWER', label: 'Power User', icon: ShieldCheck, description: 'Gestion étendue de l\'école', href: '/join/with-code?role=POWER' },
    { reference: 'PARENT', label: 'Parent/Tuteur', icon: HeartHandshake, description: 'Je suis le parcours de mon enfant', href: '/join/parent', wide: true },
];
</script>

<template>
    <Head title="Rejoindre une école" />
    <JoinAuthLayout badge="Plateforme de gestion scolaire" title="Rejoindre une école" description="Sélectionnez votre rôle pour continuer">
        <div class="role-grid">
            <Link
                v-for="r in roles" :key="r.reference"
                :href="r.href"
                class="role-card"
                :class="{ 'role-card--wide': r.wide }"
            >
                <component :is="r.icon" class="role-icon" />
                <span class="role-label">{{ r.label }}</span>
                <span class="role-desc">{{ r.description }}</span>
            </Link>
        </div>
        <p class="join-footer">
            Vous fondez un nouvel établissement ?
            <Link href="/register" class="join-link">Créer une école</Link>
        </p>
    </JoinAuthLayout>
</template>

<style scoped>
.role-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.role-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 22px 14px;
    border: 1px solid #e8e8e4;
    border-radius: 10px;
    text-decoration: none;
    color: #666666;
    text-align: center;
    transition: border-color 0.2s, color 0.2s;
}
.role-card--wide {
    grid-column: 1 / -1;
}
.role-card:hover {
    border-color: #b7dfc8;
    color: #111111;
}
.role-icon {
    width: 26px;
    height: 26px;
    color: #1a3d2b;
}
.role-label {
    font-size: 14px;
    font-weight: 600;
    color: #111111;
}
.role-desc {
    font-size: 12px;
    line-height: 1.4;
}
.join-footer {
    margin-top: 24px;
    text-align: center;
    font-size: 13px;
    color: #666666;
}
.join-link {
    color: #1a3d2b;
    text-decoration: underline;
    text-underline-offset: 3px;
    font-weight: 500;
}
</style>
