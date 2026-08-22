<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

interface SectionUserOption {
    id: number;
    sections: { id: number; name: string } | null;
    userschoolrole: { user: { id: number; firstname: string; lastname: string } | null } | null;
}

defineProps<{
    courses: Array<{ id: number; name: string }>;
    sectionUsers: SectionUserOption[];
}>();

const form = useForm({
    section_user_id: '',
    course_id: '',
    name: '',
    total_hours: '',
    hours_per_session: '',
    description: '',
});

const breadcrumbs = [
    { label: 'Associations section-cours', href: '/section-courses' },
    { label: t('action.create') },
];
</script>

<template>
    <Head title="Nouvelle association" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader title="Nouvelle association section-cours" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/section-courses')">
                        <div class="space-y-1.5">
                            <Label>Inscription (section + élève) *</Label>
                            <Select v-model="form.section_user_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.section_user_id }">
                                    <SelectValue placeholder="Sélectionner une inscription" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="su in sectionUsers" :key="su.id" :value="String(su.id)">
                                        {{ su.sections?.name ?? '—' }}
                                        <template v-if="su.userschoolrole?.user"> — {{ su.userschoolrole.user.firstname }} {{ su.userschoolrole.user.lastname }}</template>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.section_user_id" class="text-xs text-destructive">{{ form.errors.section_user_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Cours *</Label>
                            <Select v-model="form.course_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.course_id }">
                                    <SelectValue placeholder="Sélectionner un cours" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in courses" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.course_id" class="text-xs text-destructive">{{ form.errors.course_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="name">{{ t('label.name') }} *</Label>
                            <Input id="name" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="total_hours">Heures totales *</Label>
                                <Input id="total_hours" v-model="form.total_hours" type="number" min="1" :class="{ 'border-destructive': form.errors.total_hours }" />
                                <p v-if="form.errors.total_hours" class="text-xs text-destructive">{{ form.errors.total_hours }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="hours_per_session">Heures / séance *</Label>
                                <Input id="hours_per_session" v-model="form.hours_per_session" type="number" min="1" :class="{ 'border-destructive': form.errors.hours_per_session }" />
                                <p v-if="form.errors.hours_per_session" class="text-xs text-destructive">{{ form.errors.hours_per_session }}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="description">{{ t('label.description') }}</Label>
                            <Textarea id="description" v-model="form.description" rows="2" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child><Link href="/section-courses">{{ t('action.cancel') }}</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
