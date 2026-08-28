<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{ role_reference: string | null }>();
</script>

<template>
    <Head title="Rejoindre avec un code" />
    <AuthLayout title="Rejoindre avec un code d'accès" description="Renseignez le code fourni par votre établissement">
        <!-- Compte pas encore créé : inscription standard, puis redirection manuelle vers ce même formulaire -->
        <div class="mb-4 rounded-lg border border-border bg-muted/30 p-3 text-xs text-muted-foreground">
            Pas encore de compte ?
            <Link href="/register" class="font-medium underline underline-offset-4">Créez-en un</Link>
            puis revenez ici pour saisir votre code.
        </div>

        <Form
            action="/join/with-code" method="post"
            :reset-on-success="['access_code']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <input type="hidden" name="role_reference" :value="props.role_reference ?? ''" />

            <div class="grid gap-2">
                <Label for="access_code">Code d'accès</Label>
                <Input id="access_code" name="access_code" type="text" required autofocus placeholder="ABCD1234" class="uppercase" />
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
