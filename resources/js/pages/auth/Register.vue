<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
</script>

<template>
    <AuthBase
        title="Créer un compte"
        description="Renseignez vos informations pour créer votre compte"
    >
        <Head title="Inscription" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <!-- ── Profil automatique (school_owner) ─────────────────────────────────────── -->
                <input type="hidden" name="profile" value="school_owner" />

                <div class="rounded-lg border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
                    Ce formulaire crée un compte fondateur d'établissement — votre
                    demande sera examinée par un administrateur.
                    <TextLink href="/join/role" class="font-medium underline underline-offset-4">
                        Vous rejoignez une école existante ?
                    </TextLink>
                </div>

                <!-- ── Prénom + Nom ────────────────────────────────────────── -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="firstname">Prénom</Label>
                        <Input
                            id="firstname"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="given-name"
                            name="firstname"
                            placeholder="Alessandro"
                        />
                        <InputError :message="errors.firstname" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="lastname">Nom</Label>
                        <Input
                            id="lastname"
                            type="text"
                            required
                            :tabindex="2"
                            autocomplete="family-name"
                            name="lastname"
                            placeholder="Guisset"
                        />
                        <InputError :message="errors.lastname" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="email">Adresse email</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="3"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Mot de passe</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Mot de passe"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmer le mot de passe</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirmer le mot de passe"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="6"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Créer le compte
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Déjà un compte ?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="7"
                >
                    Se connecter
                </TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
