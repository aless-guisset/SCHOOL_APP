<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import JoinAuthLayout from '@/layouts/JoinAuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <JoinAuthLayout badge="Bienvenue" title="Se connecter" description="Entrez votre email et mot de passe pour continuer">
        <Head title="Connexion" />

        <div v-if="status" class="status-message">
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Adresse email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Mot de passe</Label>
                        <a v-if="canResetPassword" :href="request()" class="join-link text-xs" :tabindex="5">
                            Mot de passe oublié ?
                        </a>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Mot de passe"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Se souvenir de moi</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Se connecter
                </Button>
            </div>

            <div class="join-footer" v-if="canRegister">
                Pas encore de compte ?
                <a :href="register()" class="join-link" :tabindex="5">S'inscrire</a>
            </div>
        </Form>
    </JoinAuthLayout>
</template>

<style scoped>
.status-message {
    margin-bottom: 16px;
    text-align: center;
    font-size: 14px;
    font-weight: 500;
    color: #1a3d2b;
}
.join-footer {
    margin-top: 20px;
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
