<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Building2, LogIn, Star } from 'lucide-vue-next';
import AuthLayout from '@/layouts/AuthLayout.vue';

interface School {
    id: number;
    name: string;
    email: string | null;
    role: string | null;
    is_default: boolean;
}

defineProps<{
    schools: School[];
}>();

const page = usePage<{ auth: { user: { firstname: string } } }>();

function enter(schoolId: number) {
    router.post('/school/activate', { school_id: schoolId });
}

function setDefault(schoolId: number, event: Event) {
    event.stopPropagation();
    router.post('/school/set-default', { school_id: schoolId });
}
</script>

<template>
    <Head title="Choisir un établissement" />

    <AuthLayout>
        <div class="mx-auto w-full max-w-2xl space-y-6 px-4 py-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary/10">
                    <Building2 class="size-7 text-primary" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Choisissez votre établissement
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Bonjour {{ page.props.auth.user?.firstname ?? '' }}, vous êtes inscrit(e) dans plusieurs écoles.
                </p>
            </div>

            <!-- École cards -->
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="school in schools"
                    :key="school.id"
                    class="group relative flex flex-col items-start gap-3 rounded-xl border border-border bg-card p-5 text-left shadow-sm transition hover:border-primary hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary"
                    @click="enter(school.id)"
                >
                    <!-- Icône école -->
                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                        <Building2 class="size-5 text-primary" />
                    </div>

                    <!-- Nom + rôle -->
                    <div class="flex-1">
                        <p class="font-semibold leading-tight">{{ school.name }}</p>
                        <p v-if="school.role" class="mt-0.5 text-xs text-muted-foreground">
                            {{ school.role }}
                        </p>
                        <p v-if="school.email" class="mt-0.5 text-xs text-muted-foreground">
                            {{ school.email }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex w-full items-center justify-between">
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium text-primary opacity-0 transition group-hover:opacity-100"
                        >
                            <LogIn class="size-3" />
                            Accéder
                        </span>

                        <!-- Étoile défaut -->
                        <button
                            class="ml-auto rounded p-1 text-muted-foreground transition hover:text-yellow-400"
                            :class="{ 'text-yellow-400': school.is_default }"
                            :title="school.is_default ? 'École par défaut' : 'Définir par défaut'"
                            @click="setDefault(school.id, $event)"
                        >
                            <Star class="size-4" :fill="school.is_default ? 'currentColor' : 'none'" />
                        </button>
                    </div>

                    <!-- Badge défaut -->
                    <span
                        v-if="school.is_default"
                        class="absolute right-3 top-3 rounded-full bg-yellow-400/15 px-2 py-0.5 text-[10px] font-semibold text-yellow-500"
                    >
                        Par défaut
                    </span>
                </button>
            </div>

            <!-- Lien rejoindre autre école -->
            <p class="text-center text-xs text-muted-foreground">
                Votre école n'est pas dans la liste ?
                <a href="/school/create" class="font-medium text-primary underline-offset-2 hover:underline">
                    Soumettre une demande
                </a>
            </p>
        </div>
    </AuthLayout>
</template>
