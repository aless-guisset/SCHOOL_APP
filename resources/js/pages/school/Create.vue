<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Building2, CheckCircle2, Info } from 'lucide-vue-next';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const page = usePage<{ flash?: { type: string; message: string } }>();

const form = useForm({
    name: '',
    email: '',
    phone_number: '',
    address: '',
    description: '',
});

function submit() {
    form.post('/school/create');
}
</script>

<template>
    <Head title="Rejoindre un établissement" />

    <AuthLayout>
        <div class="mx-auto w-full max-w-lg space-y-6 px-4 py-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary/10">
                    <Building2 class="size-7 text-primary" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Rejoindre un établissement</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Soumettez une demande pour rejoindre ou créer votre école.
                </p>
            </div>

            <!-- Message succès -->
            <div
                v-if="page.props.flash?.type === 'success'"
                class="flex items-start gap-3 rounded-xl border border-green-500/30 bg-green-500/10 p-4 text-sm text-green-600 dark:text-green-400"
            >
                <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                <p>{{ page.props.flash.message }}</p>
            </div>

            <!-- Info box -->
            <div class="flex items-start gap-3 rounded-xl border border-blue-500/20 bg-blue-500/10 p-4 text-sm text-blue-600 dark:text-blue-400">
                <Info class="mt-0.5 size-5 shrink-0" />
                <p>
                    Votre demande sera examinée par un administrateur de la plateforme.
                    Vous serez notifié(e) dès qu'elle sera traitée.
                </p>
            </div>

            <!-- Formulaire -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Informations de l'établissement</CardTitle>
                    <CardDescription>Renseignez les informations de base de votre école.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <!-- Nom -->
                        <div class="space-y-1.5">
                            <Label for="name">Nom de l'établissement <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="ex : Institut Saint-Joseph"
                                :class="{ 'border-destructive': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <!-- Email -->
                        <div class="space-y-1.5">
                            <Label for="email">Email de contact</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="contact@ecole.be"
                                :class="{ 'border-destructive': form.errors.email }"
                            />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <!-- Téléphone -->
                        <div class="space-y-1.5">
                            <Label for="phone">Téléphone</Label>
                            <Input
                                id="phone"
                                v-model="form.phone_number"
                                type="tel"
                                placeholder="+32 2 123 45 67"
                            />
                        </div>

                        <!-- Adresse -->
                        <div class="space-y-1.5">
                            <Label for="address">Adresse</Label>
                            <Input
                                id="address"
                                v-model="form.address"
                                placeholder="Rue de l'École 12, 1000 Bruxelles"
                            />
                        </div>

                        <!-- Description -->
                        <div class="space-y-1.5">
                            <Label for="description">Description (optionnel)</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Quelques mots sur votre établissement..."
                                rows="3"
                            />
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <span v-if="form.processing">Envoi en cours...</span>
                            <span v-else>Soumettre la demande</span>
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Lien retour login -->
            <p class="text-center text-xs text-muted-foreground">
                <a href="/school/select" class="font-medium text-primary underline-offset-2 hover:underline">
                    ← Retour à la sélection
                </a>
            </p>
        </div>
    </AuthLayout>
</template>
