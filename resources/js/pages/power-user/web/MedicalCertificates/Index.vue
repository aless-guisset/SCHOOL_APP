<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

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
}>();

const STATUS_LABEL: Record<Certificate['status'], string> = { P: 'En attente', A: 'Actif', R: 'Rejeté' };
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

const breadcrumbs = [{ label: 'Certificats médicaux' }];
</script>

<template>
    <Head title="Certificats médicaux" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Certificats médicaux" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button v-if="!props.is_certificate_staff" size="sm" as-child>
                        <Link href="/medical-certificates/submit">Soumettre un certificat</Link>
                    </Button>
                    <Button v-else size="sm" as-child>
                        <Link href="/medical-certificates/create">Nouveau certificat</Link>
                    </Button>
                </template>
            </PageHeader>

            <div v-if="props.is_certificate_staff && pending.length" class="mb-6">
                <h2 class="mb-2 text-sm font-semibold">En attente de validation</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="c in pending" :key="c.id">
                        <CardContent class="space-y-2 pt-6 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ c.student_name }}</span>
                                <Badge :variant="STATUS_VARIANT[c.status]">{{ STATUS_LABEL[c.status] }}</Badge>
                            </div>
                            <p class="text-muted-foreground">Du {{ c.starts_at }} au {{ c.ends_at }}</p>
                            <p v-if="c.reason" class="text-xs text-muted-foreground">{{ c.reason }}</p>
                            <a v-if="c.has_attachment" :href="`/medical-certificates/${c.id}/attachment`" class="block text-xs text-primary underline">
                                Voir le justificatif
                            </a>
                            <div class="flex gap-2 pt-1">
                                <Button size="sm" @click="approve(c.id)">Approuver</Button>
                                <Button size="sm" variant="destructive" @click="openReject(c.id)">Rejeter</Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div v-if="others.length === 0 && pending.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                Aucun certificat.
            </div>
            <div v-else-if="others.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="c in others" :key="c.id">
                    <CardContent class="space-y-2 pt-6 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ c.student_name }}</span>
                            <Badge :variant="STATUS_VARIANT[c.status]">{{ STATUS_LABEL[c.status] }}</Badge>
                        </div>
                        <p class="text-muted-foreground">Du {{ c.starts_at }} au {{ c.ends_at }}</p>
                        <p v-if="c.reason" class="text-xs text-muted-foreground">{{ c.reason }}</p>
                        <p v-if="c.status === 'R' && c.rejection_reason" class="text-xs text-destructive">Motif de refus : {{ c.rejection_reason }}</p>
                        <a v-if="c.has_attachment" :href="`/medical-certificates/${c.id}/attachment`" class="text-xs text-primary underline">
                            Voir le justificatif
                        </a>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rejeter le certificat</DialogTitle>
                </DialogHeader>
                <div class="space-y-2 py-2">
                    <Label for="rejection_reason">Motif (optionnel)</Label>
                    <Input id="rejection_reason" v-model="rejectReason" placeholder="ex : Document illisible" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rejectDialogOpen = false">Annuler</Button>
                    <Button variant="destructive" @click="confirmReject">Confirmer le refus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
