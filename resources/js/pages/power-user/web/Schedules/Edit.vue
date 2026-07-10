<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const DAYS = [
    { value: '1', label: 'Lundi' }, { value: '2', label: 'Mardi' },
    { value: '3', label: 'Mercredi' }, { value: '4', label: 'Jeudi' },
    { value: '5', label: 'Vendredi' }, { value: '6', label: 'Samedi' },
    { value: '7', label: 'Dimanche' },
];

const props = defineProps<{
    schedule: {
        id: number; name: string; day_of_week: number;
        start_time: string; end_time: string;
    };
}>();

const form = useForm({
    name: props.schedule.name,
    day_of_week: String(props.schedule.day_of_week),
    start_time: props.schedule.start_time.substring(0, 5),
    end_time: props.schedule.end_time.substring(0, 5),
});

const breadcrumbs = [
    { label: t('nav.schedules'), href: '/schedules' },
    { label: props.schedule.name, href: `/schedules/${props.schedule.id}` },
    { label: t('action.edit') },
];
</script>

<template>
    <Head :title="`Modifier — ${schedule.name}`" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="`Modifier : ${schedule.name}`" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.patch(`/schedules/${schedule.id}`)">
                        <div class="space-y-1.5">
                            <Label for="name">{{ t('label.name') }} *</Label>
                            <Input id="name" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Jour</Label>
                            <Select v-model="form.day_of_week">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="d in DAYS" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="start">{{ t('label.start_time') }}</Label>
                                <Input id="start" v-model="form.start_time" type="time" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="end">{{ t('label.end_time') }}</Label>
                                <Input id="end" v-model="form.end_time" type="time" />
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child>
                                <Link :href="`/schedules/${schedule.id}`">{{ t('action.cancel') }}</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
