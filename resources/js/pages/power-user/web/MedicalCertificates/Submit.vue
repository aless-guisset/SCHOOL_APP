<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    as_parent?: boolean;
}>();

const form = useForm({
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
    form.post(`/medical-certificates/submit${props.as_parent ? '?as_parent=1' : ''}`, { forceFormData: true });
}

const breadcrumbs = [
    { label: 'Certificats médicaux', href: '/medical-certificates' },
    { label: 'Soumettre un certificat' },
];
</script>

<template>
    <Head title="Soumettre un certificat médical" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-lg">
            <FlashMessage />
            <PageHeader title="Soumettre un certificat médical" :breadcrumbs="breadcrumbs" />
            <Card>
                <CardContent class="pt-6">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="starts_at">Du *</Label>
                                <Input id="starts_at" v-model="form.starts_at" type="date" :class="{ 'border-destructive': form.errors.starts_at }" />
                                <p v-if="form.errors.starts_at" class="text-xs text-destructive">{{ form.errors.starts_at }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="ends_at">Au *</Label>
                                <Input id="ends_at" v-model="form.ends_at" type="date" :class="{ 'border-destructive': form.errors.ends_at }" />
                                <p v-if="form.errors.ends_at" class="text-xs text-destructive">{{ form.errors.ends_at }}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="reason">Motif (optionnel)</Label>
                            <Textarea id="reason" v-model="form.reason" rows="3" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="attachment">Justificatif *</Label>
                            <input id="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" @change="onFileChange" />
                            <p v-if="form.errors.attachment" class="text-xs text-destructive">{{ form.errors.attachment }}</p>
                        </div>
                        <Button type="submit" :disabled="form.processing">Soumettre</Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
