<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2, GraduationCap } from 'lucide-vue-next';
import { ref } from 'vue';
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

const profile = ref<'student' | 'school_owner' | null>(null);
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
                <!-- ── Choix du profil ─────────────────────────────────────── -->
                <div class="grid gap-2">
                    <Label>Je suis… <span class="text-destructive">*</span></Label>
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Étudiant -->
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 rounded-xl border-2 px-4 py-4 text-sm font-medium transition-all"
                            :class="profile === 'student'
                                ? 'border-primary bg-primary/5 text-primary'
                                : 'border-border text-muted-foreground hover:border-primary/50 hover:text-foreground'"
                            @click="profile = 'student'"
                        >
                            <GraduationCap class="size-7" />
                            <span>Étudiant</span>
                            <span class="text-xs font-normal opacity-70">Je rejoins une école</span>
                        </button>

                        <!-- Créateur d'école -->
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 rounded-xl border-2 px-4 py-4 text-sm font-medium transition-all"
                            :class="profile === 'school_owner'
                                ? 'border-primary bg-primary/5 text-primary'
                                : 'border-border text-muted-foreground hover:border-primary/50 hover:text-foreground'"
                            @click="profile = 'school_owner'"
                        >
                            <Building2 class="size-7" />
                            <span>Établissement</span>
                            <span class="text-xs font-normal opacity-70">Je crée une école</span>
                        </button>
                    </div>

                    <!-- Input caché soumis avec le formulaire -->
                    <input type="hidden" name="profile" :value="profile ?? ''" />
                    <p v-if="errors.profile" class="text-xs text-destructive">{{ errors.profile }}</p>
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
                    :disabled="processing || profile === null"
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
