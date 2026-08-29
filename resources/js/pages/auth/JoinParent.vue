<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import JoinAuthLayout from '@/layouts/JoinAuthLayout.vue';

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth.user);
</script>

<template>
    <Head title="Rejoindre en tant que parent" />
    <JoinAuthLayout badge="Espace Parent/Tuteur" title="Suivre le parcours de votre enfant" description="Renseignez le code fourni par votre enfant">
        <Form
            action="/join/parent" method="post"
            :reset-on-success="['access_code']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
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
                <Label for="access_code">Code de l'élève</Label>
                <Input
                    id="access_code" name="access_code" type="text" required placeholder="ABCD1234" class="uppercase"
                    :autofocus="isAuthenticated"
                />
                <InputError :message="errors.access_code" />
            </div>

            <Button type="submit" :disabled="processing" class="w-full">Rejoindre</Button>
        </Form>
    </JoinAuthLayout>
</template>
