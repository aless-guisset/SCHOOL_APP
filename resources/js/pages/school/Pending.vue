<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Hourglass } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
</script>

<template>
    <Head title="Demande en attente" />
    <AuthLayout>
        <div class="mx-auto w-full max-w-md space-y-6 px-4 py-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-amber-500/10">
                <Hourglass class="size-8 text-amber-600" />
            </div>
            <div class="space-y-2">
                <h1 class="text-2xl font-bold tracking-tight">Demande en attente</h1>
                <p class="text-muted-foreground text-sm">
                    Votre demande d'accès a été envoyée au Directeur de l'établissement.
                    Vous recevrez un email dès qu'elle sera traitée.
                </p>
            </div>
            <p class="text-xs text-muted-foreground">
                <Link :href="logout()" as="button" class="underline underline-offset-2">Se déconnecter</Link>
            </p>
        </div>
    </AuthLayout>
</template>
