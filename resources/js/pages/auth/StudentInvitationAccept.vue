<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import JoinAuthLayout from '@/layouts/JoinAuthLayout.vue';

const props = defineProps<{
    email: string;
    student_name: string;
    account_exists: boolean;
}>();

const acceptUrl = usePage().url;

const firstname = ref('');
const lastname = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const processing = ref(false);
const errors = ref<Record<string, string>>({});

function submit() {
    processing.value = true;
    router.post(
        acceptUrl,
        props.account_exists
            ? {}
            : {
                  firstname: firstname.value,
                  lastname: lastname.value,
                  password: password.value,
                  password_confirmation: passwordConfirmation.value,
              },
        {
            onError: (e) => { errors.value = e as Record<string, string>; },
            onFinish: () => { processing.value = false; },
        }
    );
}
</script>

<template>
    <JoinAuthLayout
        badge="Invitation Parent/Tuteur"
        title="Invitation reçue"
        :description="`Suivez le parcours scolaire de ${props.student_name}`"
    >
        <Head title="Accepter l'invitation" />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label>Adresse email</Label>
                <Input :model-value="props.email" type="email" disabled />
                <!-- Canal d'erreur de accept() : compte déjà lié à un autre élève. -->
                <InputError :message="errors.email" />
            </div>

            <template v-if="!props.account_exists">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="firstname">Prénom</Label>
                        <Input id="firstname" v-model="firstname" type="text" required autofocus autocomplete="given-name" />
                        <InputError :message="errors.firstname" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lastname">Nom</Label>
                        <Input id="lastname" v-model="lastname" type="text" required autocomplete="family-name" />
                        <InputError :message="errors.lastname" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="password">Mot de passe</Label>
                    <PasswordInput id="password" v-model="password" required autocomplete="new-password" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmer le mot de passe</Label>
                    <PasswordInput id="password_confirmation" v-model="passwordConfirmation" required autocomplete="new-password" />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </template>

            <p v-else class="text-sm text-muted-foreground">
                Un compte existe déjà pour cette adresse. Cliquez ci-dessous pour accepter l'invitation.
            </p>

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Accepter l'invitation
            </Button>
        </form>
    </JoinAuthLayout>
</template>
