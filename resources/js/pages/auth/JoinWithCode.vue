<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{ role_reference: string | null }>();

// Anonyme : le formulaire crée le compte au passage (mêmes champs que
// InvitationAccept.vue). Déjà connecté : formulaire code-only, inchangé.
const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth.user);
</script>

<template>
    <Head title="Rejoindre avec un code" />
    <AuthLayout title="Rejoindre avec un code d'accès" description="Renseignez le code fourni par votre établissement">
        <Form
            action="/join/with-code" method="post"
            :reset-on-success="['access_code']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <input type="hidden" name="role_reference" :value="props.role_reference ?? ''" />

            <template v-if="!isAuthenticated">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="firstname">Prénom</Label>
                        <Input id="firstname" name="firstname" type="text" required autofocus autocomplete="given-name" />
                        <InputError :message="errors.firstname" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lastname">Nom</Label>
                        <Input id="lastname" name="lastname" type="text" required autocomplete="family-name" />
                        <InputError :message="errors.lastname" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="email">Adresse email</Label>
                    <Input id="email" name="email" type="email" required autocomplete="email" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Mot de passe</Label>
                    <PasswordInput id="password" name="password" required autocomplete="new-password" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmer le mot de passe</Label>
                    <PasswordInput id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </template>

            <div class="grid gap-2">
                <Label for="access_code">Code d'accès</Label>
                <Input
                    id="access_code" name="access_code" type="text" required placeholder="ABCD1234" class="uppercase"
                    :autofocus="isAuthenticated"
                />
                <InputError :message="errors.access_code" />
                <InputError :message="errors.role_reference" />
            </div>

            <Button type="submit" :disabled="processing" class="w-full">Rejoindre</Button>
        </Form>

        <p class="mt-4 text-center text-sm text-muted-foreground">
            Pas de code ?
            <Link :href="`/join/request?role=${props.role_reference ?? ''}`" class="underline underline-offset-4">
                Demander l'accès sans code
            </Link>
        </p>
    </AuthLayout>
</template>
