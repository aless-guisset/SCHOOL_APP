<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
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

type Student = { id: number; userschoolrole: { user: { lastname: string; firstname: string } } | null };

const props = defineProps<{
    students: Student[];
}>();

const form = useForm({
    section_user_id: '',
    starts_at: '',
    ends_at: '',
    reason: '',
    attachment: null as File | null,
});

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

function submit() {
    form.post('/medical-certificates', { forceFormData: true });
}

const breadcrumbs = computed(() => [
    { label: t('nav.medical_certificates'), href: '/medical-certificates' },
    { label: t('medical_certificate.breadcrumb_new') },
]);
</script>

<template>
    <Head :title="t('medical_certificate.create_title')" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-lg">
            <FlashMessage />
            <PageHeader :title="t('medical_certificate.create_title')" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <Label>{{ t('medical_certificate.field_student') }} *</Label>
                            <Select v-model="form.section_user_id">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.section_user_id }">
                                    <SelectValue :placeholder="t('medical_certificate.choose_student')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in props.students" :key="s.id" :value="String(s.id)">
                                        {{ s.userschoolrole?.user ? `${s.userschoolrole.user.lastname} ${s.userschoolrole.user.firstname}` : `#${s.id}` }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.section_user_id" class="text-xs text-destructive">{{ form.errors.section_user_id }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="starts_at">{{ t('medical_certificate.field_starts_at') }} *</Label>
                                <Input id="starts_at" v-model="form.starts_at" type="date" :class="{ 'border-destructive': form.errors.starts_at }" />
                                <p v-if="form.errors.starts_at" class="text-xs text-destructive">{{ form.errors.starts_at }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="ends_at">{{ t('medical_certificate.field_ends_at') }} *</Label>
                                <Input id="ends_at" v-model="form.ends_at" type="date" :class="{ 'border-destructive': form.errors.ends_at }" />
                                <p v-if="form.errors.ends_at" class="text-xs text-destructive">{{ form.errors.ends_at }}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="reason">{{ t('medical_certificate.field_reason') }}</Label>
                            <Textarea id="reason" v-model="form.reason" rows="3" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="attachment">{{ t('medical_certificate.field_attachment_optional') }}</Label>
                            <input id="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" @change="onFileChange" />
                            <p v-if="form.errors.attachment" class="text-xs text-destructive">{{ form.errors.attachment }}</p>
                        </div>
                        <Button type="submit" :disabled="form.processing">{{ t('action.save') }}</Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
