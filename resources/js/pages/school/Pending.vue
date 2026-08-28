<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Hourglass } from 'lucide-vue-next';
import JoinAuthLayout from '@/layouts/JoinAuthLayout.vue';
import { logout } from '@/routes';
</script>

<template>
    <Head title="Demande en attente" />
    <JoinAuthLayout badge="Traitement en cours">
        <div class="pending-body">
            <div class="pending-icon">
                <Hourglass class="pending-icon-svg" />
            </div>
            <h1 class="pending-title">Demande en attente</h1>
            <p class="pending-desc">
                Votre demande d'accès a été envoyée au Directeur de l'établissement.
                Vous recevrez un email dès qu'elle sera traitée.
            </p>
            <p class="join-footer">
                <Link :href="logout()" as="button" class="join-link">Se déconnecter</Link>
            </p>
        </div>
    </JoinAuthLayout>
</template>

<style scoped>
.pending-body {
    text-align: center;
}
.pending-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 20px;
    border-radius: 16px;
    background: #e8f5ee;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pending-icon-svg {
    width: 26px;
    height: 26px;
    color: #1a3d2b;
}
.pending-title {
    font-size: 20px;
    font-weight: 700;
    color: #111111;
    margin-bottom: 10px;
}
.pending-desc {
    font-size: 14px;
    color: #666666;
    line-height: 1.6;
}
.join-footer {
    margin-top: 24px;
    font-size: 13px;
    color: #666666;
}
.join-link {
    color: #1a3d2b;
    text-decoration: underline;
    text-underline-offset: 3px;
    font-weight: 500;
    background: none;
    border: none;
    cursor: pointer;
    font-size: inherit;
}
</style>
