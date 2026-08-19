<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const FIXED_LANGUAGES = ['fr', 'en', 'nl'];

defineProps<{
    languages: string[];
}>();

const form = useForm({
    tag_key:          '',
    language_code:    'fr',
    translated_value: '',
    screen_name:      '',
    description:      '',
});

const breadcrumbs = [
    { label: t('nav.translations'), href: '/translations' },
    { label: t('translations.new') },
];
</script>

<template>
    <Head title="Nouvelle traduction" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="t('translations.new')" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="form.post('/translations')">
                        <div class="space-y-1.5">
                            <Label for="tag_key">{{ t('label.key') }} *</Label>
                            <Input
                                id="tag_key"
                                v-model="form.tag_key"
                                placeholder="ex: dashboard.title"
                                :class="{ 'border-destructive': form.errors.tag_key }"
                            />
                            <p v-if="form.errors.tag_key" class="text-xs text-destructive">{{ form.errors.tag_key }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label>{{ t('label.language') }} *</Label>
                            <Select v-model="form.language_code">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.language_code }">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="l in FIXED_LANGUAGES" :key="l" :value="l">{{ l }}</SelectItem>
                                    <SelectItem
                                        v-for="l in languages.filter(l => !FIXED_LANGUAGES.includes(l))"
                                        :key="l" :value="l"
                                    >{{ l }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.language_code" class="text-xs text-destructive">{{ form.errors.language_code }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="translated_value">{{ t('label.value') }} *</Label>
                            <Textarea
                                id="translated_value"
                                v-model="form.translated_value"
                                :rows="3"
                                :class="{ 'border-destructive': form.errors.translated_value }"
                            />
                            <p v-if="form.errors.translated_value" class="text-xs text-destructive">{{ form.errors.translated_value }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="screen_name">{{ t('label.screen_name') }}</Label>
                            <Input
                                id="screen_name"
                                v-model="form.screen_name"
                                placeholder="ex: dashboard"
                                :class="{ 'border-destructive': form.errors.screen_name }"
                            />
                            <p v-if="form.errors.screen_name" class="text-xs text-destructive">{{ form.errors.screen_name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="description">{{ t('label.description') }}</Label>
                            <Textarea id="description" v-model="form.description" :rows="2" />
                        </div>

                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                            <Button variant="outline" as-child><Link href="/translations">{{ t('action.cancel') }}</Link></Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
