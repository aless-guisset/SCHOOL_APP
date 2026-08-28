<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Briefcase, GraduationCap, ShieldCheck, Users } from 'lucide-vue-next';
import JoinAuthLayout from '@/layouts/JoinAuthLayout.vue';

const roles = [
    { reference: 'ELEVE', label: 'Étudiant', icon: GraduationCap, description: 'Je rejoins ma classe' },
    { reference: 'PROF', label: 'Professeur', icon: Briefcase, description: "J'enseigne dans un établissement" },
    { reference: 'SEC', label: 'Secrétariat', icon: Users, description: 'Je gère l\'administratif' },
    { reference: 'POWER', label: 'Power User', icon: ShieldCheck, description: 'Gestion étendue de l\'école' },
];
</script>

<template>
    <Head title="Rejoindre une école" />
    <JoinAuthLayout badge="Plateforme de gestion scolaire" title="Rejoindre une école" description="Sélectionnez votre rôle pour continuer">
        <div class="role-grid">
            <Link
                v-for="r in roles" :key="r.reference"
                :href="r.reference === 'ELEVE' ? `/join/request?role=${r.reference}` : `/join/with-code?role=${r.reference}`"
                class="role-card"
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
