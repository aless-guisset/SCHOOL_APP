<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

const DAYS = [
    { value: '1', label: 'Lundi' },
    { value: '2', label: 'Mardi' },
    { value: '3', label: 'Mercredi' },
    { value: '4', label: 'Jeudi' },
    { value: '5', label: 'Vendredi' },
    { value: '6', label: 'Samedi' },
    { value: '7', label: 'Dimanche' },
];

type StudentOption = {
    id: number;
    section: { name: string } | null;
    userschoolrole: { user: { firstname: string; lastname: string } | null } | null;
};

defineProps<{
    students: StudentOption[];
}>();

const form = useForm({ section_user_id: '', day_of_week: '' });

const breadcrumbs = [
    { label: 'Cantine', href: '/cantine' },
    { label: 'Inscrire un élève' },
];
</script>

<template>
    <Head title="Inscrire un élève à la cantine" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Inscrire un élève à la cantine" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/cantine')">
                        <div class="space-y-1.5">
                            <Label>Élève *</Label>
                            <Select v-model="form.section_user_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.section_user_id }">
                                    <SelectValue placeholder="Sélectionner un élève" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in students" :key="s.id" :value="String(s.id)">
                                        <template v-if="s.userschoolrole?.user">{{ s.userschoolrole.user.lastname }} {{ s.userschoolrole.user.firstname }}</template>
                                        <template v-else>Élève #{{ s.id }}</template>
                                        <template v-if="s.section"> — {{ s.section.name }}</template>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.section_user_id" class="text-xs text-destructive">{{ form.errors.section_user_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Jour *</Label>
                            <Select v-model="form.day_of_week">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.day_of_week }">
                                    <SelectValue placeholder="Sélectionner un jour" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="d in DAYS" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.day_of_week" class="text-xs text-destructive">{{ form.errors.day_of_week }}</p>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                            <Button variant="outline" as-child><Link href="/cantine">Annuler</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
