<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{ role_reference: string | null; is_student: boolean }>();

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
        <div class="mb-4 rounded-lg border border-border bg-muted/30 p-3 text-xs text-muted-foreground">
            Pas encore de compte ?
            <Link href="/register" class="font-medium underline underline-offset-4">Créez-en un</Link>
            puis revenez ici pour rechercher votre école.
        </div>

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

            <Button type="submit" :disabled="processing" class="w-full">Envoyer la demande</Button>
        </Form>
    </AuthLayout>
</template>
