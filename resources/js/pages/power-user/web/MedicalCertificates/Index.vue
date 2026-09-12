<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import ViewingChildBanner from '@/components/ViewingChildBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

type Certificate = {
    id: number;
    student_name: string;
    starts_at: string;
    ends_at: string;
    reason: string | null;
    status: 'P' | 'A' | 'R';
    has_attachment: boolean;
    rejection_reason: string | null;
};

const props = defineProps<{
    certificates: Certificate[];
    is_certificate_staff: boolean;
    is_certificate_submitter: boolean;
    viewing_child?: string | null;
}>();

function attachmentHref(id: number): string {
    return props.viewing_child ? `/medical-certificates/${id}/attachment?as_parent=1` : `/medical-certificates/${id}/attachment`;
}

function statusLabel(status: Certificate['status']): string {
    const key: Record<Certificate['status'], string> = {
        P: 'medical_certificate.status_pending',
        A: 'medical_certificate.status_active',
        R: 'medical_certificate.status_rejected',
    };
    return t(key[status]);
}
const STATUS_VARIANT: Record<Certificate['status'], 'secondary' | 'default' | 'destructive'> = { P: 'secondary', A: 'default', R: 'destructive' };

const pending = computed(() => props.certificates.filter(c => c.status === 'P'));
const others = computed(() => props.certificates.filter(c => c.status !== 'P'));

function approve(id: number) {
    router.post(`/medical-certificates/${id}/approve`, {}, { preserveScroll: true });
}

const rejectDialogOpen = ref(false);
const rejectTargetId = ref<number | null>(null);
const rejectReason = ref('');

function openReject(id: number) {
    rejectTargetId.value = id;
    rejectReason.value = '';
    rejectDialogOpen.value = true;
}

function confirmReject() {
    if (!rejectTargetId.value) return;
    router.post(`/medical-certificates/${rejectTargetId.value}/reject`, { rejection_reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { rejectDialogOpen.value = false; },
    });
}

const breadcrumbs = computed(() => [{ label: t('nav.medical_certificates') }]);
</script>

<template>
    <Head :title="t('nav.medical_certificates')" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <ViewingChildBanner v-if="viewing_child" :name="viewing_child" />
            <PageHeader :title="t('nav.medical_certificates')" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button v-if="props.is_certificate_submitter" size="sm" as-child>
                        <Link href="/medical-certificates/submit">{{ t('medical_certificate.breadcrumb_submit') }}</Link>
                    </Button>
                    <Button v-else-if="props.is_certificate_staff" size="sm" as-child>
                        <Link href="/medical-certificates/create">{{ t('medical_certificate.breadcrumb_new') }}</Link>
                    </Button>
                </template>
            </PageHeader>

            <div v-if="pending.length" class="mb-6">
                <h2 class="mb-2 text-sm font-semibold">{{ t('medical_certificate.pending_section_title') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="c in pending" :key="c.id">
                        <CardContent class="space-y-2 pt-6 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ c.student_name }}</span>
                                <Badge :variant="STATUS_VARIANT[c.status]">{{ statusLabel(c.status) }}</Badge>
                            </div>
                            <p class="text-muted-foreground">{{ t('medical_certificate.date_range', { start: c.starts_at, end: c.ends_at }) }}</p>
                            <p v-if="c.reason" class="text-xs text-muted-foreground">{{ c.reason }}</p>
                            <a v-if="c.has_attachment" :href="attachmentHref(c.id)" class="block text-xs text-primary underline">
                                {{ t('medical_certificate.view_attachment') }}
                            </a>
                            <div v-if="props.is_certificate_staff" class="flex gap-2 pt-1">
                                <Button size="sm" @click="approve(c.id)">{{ t('action.approve') }}</Button>
                                <Button size="sm" variant="destructive" @click="openReject(c.id)">{{ t('action.reject') }}</Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div v-if="others.length === 0 && pending.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                {{ t('medical_certificate.empty') }}
            </div>
            <div v-else-if="others.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="c in others" :key="c.id">
                    <CardContent class="space-y-2 pt-6 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ c.student_name }}</span>
                            <Badge :variant="STATUS_VARIANT[c.status]">{{ statusLabel(c.status) }}</Badge>
                        </div>
                        <p class="text-muted-foreground">{{ t('medical_certificate.date_range', { start: c.starts_at, end: c.ends_at }) }}</p>
                        <p v-if="c.reason" class="text-xs text-muted-foreground">{{ c.reason }}</p>
                        <p v-if="c.status === 'R' && c.rejection_reason" class="text-xs text-destructive">{{ t('medical_certificate.rejection_reason_display', { reason: c.rejection_reason ?? '' }) }}</p>
                        <a v-if="c.has_attachment" :href="attachmentHref(c.id)" class="text-xs text-primary underline">
                            {{ t('medical_certificate.view_attachment') }}
                        </a>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('medical_certificate.reject_dialog_title') }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-2 py-2">
                    <Label for="rejection_reason">{{ t('medical_certificate.field_reason') }}</Label>
                    <Input id="rejection_reason" v-model="rejectReason" :placeholder="t('medical_certificate.rejection_reason_placeholder')" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rejectDialogOpen = false">{{ t('action.cancel') }}</Button>
                    <Button variant="destructive" @click="confirmReject">{{ t('medical_certificate.confirm_reject') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
