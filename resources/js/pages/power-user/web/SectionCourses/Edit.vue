<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const props = defineProps<{
    sectionCourse: {
        id: number;
        name: string;
        total_hours: number;
        hours_per_session: number;
        description: string | null;
        is_active: boolean;
        course: { id: number; name: string } | null;
        section_user: { section: { id: number; name: string } | null } | null;
    };
}>();

const form = useForm({
    name: props.sectionCourse.name,
    total_hours: props.sectionCourse.total_hours,
    hours_per_session: props.sectionCourse.hours_per_session,
    description: props.sectionCourse.description ?? '',
    is_active: props.sectionCourse.is_active,
});

const breadcrumbs = [
    { label: 'Associations section-cours', href: '/section-courses' },
    { label: props.sectionCourse.name, href: `/section-courses/${props.sectionCourse.id}` },
    { label: t('action.edit') },
];
</script>

<template>
    <Head :title="`Modifier — ${sectionCourse.name}`" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="`Modifier : ${sectionCourse.name}`" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.patch(`/section-courses/${sectionCourse.id}`)">
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
                            <Textarea id="description" v-model="form.description" rows="3" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child>
                                <Link :href="`/section-courses/${sectionCourse.id}`">{{ t('action.cancel') }}</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
