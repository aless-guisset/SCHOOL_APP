<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

type StudentOption = {
    id: number;
    section: { name: string } | null;
    userschoolrole: { user: { firstname: string; lastname: string } | null } | null;
};

defineProps<{
    students: StudentOption[];
    subjects: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    section_user_id: '',
    subject_id: '',
    period: '',
    grade: '',
    max_grade: '20',
    attachment: null as File | null,
});

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

const breadcrumbs = [
    { label: 'Notes', href: '/grades' },
    { label: 'Saisir une note' },
];
</script>

<template>
    <Head title="Saisir une note" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Saisir une note" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/grades')">
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
                            <Label>Matière *</Label>
                            <Select v-model="form.subject_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.subject_id }">
                                    <SelectValue placeholder="Sélectionner une matière" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.subject_id" class="text-xs text-destructive">{{ form.errors.subject_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="period">Période *</Label>
                            <Input id="period" v-model="form.period" placeholder="ex : Trimestre 1" :class="{ 'border-destructive': form.errors.period }" />
                            <p v-if="form.errors.period" class="text-xs text-destructive">{{ form.errors.period }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="max_grade">Barème *</Label>
                                <Input id="max_grade" v-model="form.max_grade" type="number" min="1" max="1000" step="0.5" :class="{ 'border-destructive': form.errors.max_grade }" />
                                <p v-if="form.errors.max_grade" class="text-xs text-destructive">{{ form.errors.max_grade }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="grade">Note / {{ form.max_grade || 20 }} *</Label>
                                <Input id="grade" v-model="form.grade" type="number" min="0" :max="form.max_grade || 20" step="0.25" :class="{ 'border-destructive': form.errors.grade }" />
                                <p v-if="form.errors.grade" class="text-xs text-destructive">{{ form.errors.grade }}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="attachment">Pièce jointe (PDF, JPG ou PNG, 10 Mo max)</Label>
                            <input
                                id="attachment"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm"
                                @change="onFileChange"
                            />
                            <p v-if="form.errors.attachment" class="text-xs text-destructive">{{ form.errors.attachment }}</p>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                            <Button variant="outline" as-child><Link href="/grades">Annuler</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
