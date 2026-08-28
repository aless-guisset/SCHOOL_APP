<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{ role_reference: string | null; is_student: boolean }>();

// Anonyme : le formulaire crée le compte au passage (mêmes champs que
// InvitationAccept.vue / JoinWithCode.vue). Déjà connecté : formulaire inchangé.
const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth.user);

type SchoolResult = { id: number; name: string };
const query = ref('');
const results = ref<SchoolResult[]>([]);
const selectedSchool = ref<SchoolResult | null>(null);
let debounceHandle: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    selectedSchool.value = null;
    clearTimeout(debounceHandle);
    if (value.trim().length < 2) {
        results.value = [];
        return;
    }
    debounceHandle = setTimeout(async () => {
        const res = await fetch(`/schools/search?q=${encodeURIComponent(value.trim())}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        results.value = res.ok ? await res.json() : [];
    }, 300);
});
</script>

<template>
    <Head title="Demander l'accès" />
    <AuthLayout
        title="Demander l'accès"
        :description="props.is_student ? 'Recherchez votre établissement' : 'Recherchez l\'établissement, votre demande sera validée par le Directeur'"
    >
        <div class="grid gap-2">
            <Label for="school_search">Nom de l'établissement</Label>
            <Input id="school_search" v-model="query" placeholder="Lycée..." autofocus />
            <ul v-if="results.length" class="rounded-md border border-border divide-y divide-border">
                <li
                    v-for="s in results" :key="s.id"
                    class="cursor-pointer px-3 py-2 text-sm hover:bg-muted"
                    :class="{ 'bg-primary/10 font-medium': selectedSchool?.id === s.id }"
                    @click="selectedSchool = s"
                >
                    {{ s.name }}
                </li>
            </ul>
        </div>

        <Form
            v-if="selectedSchool"
            action="/join/request" method="post"
            v-slot="{ errors, processing }"
            class="mt-4 flex flex-col gap-4"
        >
            <input type="hidden" name="school_id" :value="selectedSchool.id" />
            <input type="hidden" name="role_reference" :value="props.role_reference ?? ''" />
            <input type="hidden" name="is_student" :value="props.is_student ? '1' : '0'" />

            <p class="text-sm">
                Demande d'accès à <span class="font-medium">{{ selectedSchool.name }}</span>
            </p>
            <InputError :message="errors.school_id" />
            <InputError :message="errors.role_reference" />

            <template v-if="!isAuthenticated">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="firstname">Prénom</Label>
                        <Input id="firstname" name="firstname" type="text" required autocomplete="given-name" />
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

            <Button type="submit" :disabled="processing" class="w-full">Envoyer la demande</Button>
        </Form>
    </AuthLayout>
</template>
