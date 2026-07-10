<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { GraduationCap, Mail } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
</script>

<template>
    <Head title="En attente d'invitation" />

    <AuthLayout>
        <div class="mx-auto w-full max-w-md space-y-6 px-4 py-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-primary/10">
                <GraduationCap class="size-8 text-primary" />
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-bold tracking-tight">Votre compte est prêt !</h1>
                <p class="text-muted-foreground text-sm">
                    Pour accéder à la plateforme, un administrateur doit vous ajouter à un établissement.
                </p>
            </div>

            <div class="rounded-xl border border-blue-500/20 bg-blue-500/10 p-4 text-left text-sm text-blue-600 dark:text-blue-400">
                <div class="flex items-start gap-3">
                    <Mail class="mt-0.5 size-5 shrink-0" />
                    <p>
                        Transmettez votre adresse email à votre administrateur d'école.
                        Dès qu'il vous aura ajouté, vous pourrez vous connecter et accéder à votre espace.
                    </p>
                </div>
            </div>

            <div class="space-y-2 pt-2">
                <p class="text-xs text-muted-foreground">
                    Vous souhaitez plutôt créer ou rejoindre un établissement ?
                </p>
                <Button variant="outline" as-child class="w-full">
                    <Link href="/school/create">Soumettre une demande d'école</Link>
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                <a href="/logout" class="underline underline-offset-2">Se déconnecter</a>
            </p>
        </div>
    </AuthLayout>
</template>
