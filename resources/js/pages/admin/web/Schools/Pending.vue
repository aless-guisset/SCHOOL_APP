<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Building2, CheckCircle2, Clock, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface PendingSchool {
    id: number;
    name: string;
    email: string | null;
    phone_number: string | null;
    address: string | null;
    description: string | null;
    created_at: string;
    has_conflict: boolean;
}

const props = defineProps<{
    schools: {
        data: PendingSchool[];
        total: number;
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const breadcrumbs = [
    { label: 'Écoles', href: '/schools' },
    { label: 'Demandes en attente' },
];

const conflictCount = computed(() => props.schools.data.filter(s => s.has_conflict).length);

// ── Approbation ─────────────────────────────────────────────────────────────
const approveDialogOpen = ref(false);
const schoolToApprove = ref<PendingSchool | null>(null);
const approveProcessing = ref(false);

function approve(school: PendingSchool) {
    if (school.has_conflict) {
        schoolToApprove.value = school;
        approveDialogOpen.value = true;
        return;
    }
    doApprove(school.id);
}

function confirmApprove() {
    if (!schoolToApprove.value) return;
    doApprove(schoolToApprove.value.id);
}

function doApprove(id: number) {
    approveProcessing.value = true;
    router.post(`/schools/${id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => { approveDialogOpen.value = false; },
        onFinish: () => { approveProcessing.value = false; },
    });
}

// ── Refus avec raison ────────────────────────────────────────────────────────
const rejectDialogOpen = ref(false);
const selectedSchool = ref<PendingSchool | null>(null);
const rejectForm = useForm({ reason: '' });

function openRejectDialog(school: PendingSchool) {
    selectedSchool.value = school;
    rejectDialogOpen.value = true;
}

function confirmReject() {
    if (!selectedSchool.value) return;
    rejectForm.post(`/schools/${selectedSchool.value.id}/reject`, {
        onSuccess: () => {
            rejectDialogOpen.value = false;
            rejectForm.reset();
        },
        preserveScroll: true,
    });
}

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('fr-BE', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}
</script>

<template>
    <Head title="Demandes en attente" />

    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />

            <PageHeader
                title="Demandes en attente"
                :description="`${schools.total} demande(s) à traiter`"
                :breadcrumbs="breadcrumbs"
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/schools">← Toutes les écoles</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Bandeau conflits -->
            <div
                v-if="conflictCount > 0"
                class="mb-4 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                <p class="text-sm">
                    <strong>{{ conflictCount }} demande(s) en conflit</strong> — un établissement actif porte déjà
                    le même nom. Vérifiez avant d'approuver.
                </p>
            </div>

            <!-- Aucune demande -->
            <div
                v-if="schools.data.length === 0"
                class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border py-16 text-center"
            >
                <CheckCircle2 class="size-10 text-green-500" />
                <p class="font-semibold">Aucune demande en attente</p>
                <p class="text-sm text-muted-foreground">Toutes les demandes ont été traitées.</p>
            </div>

            <!-- Liste des demandes -->
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="school in schools.data"
                    :key="school.id"
                    class="flex flex-col"
                    :class="school.has_conflict ? 'border-amber-400 dark:border-amber-600' : ''"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <Building2
                                    class="size-5 shrink-0"
                                    :class="school.has_conflict ? 'text-amber-500' : 'text-primary'"
                                />
                                <CardTitle class="text-base leading-tight">{{ school.name }}</CardTitle>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <Badge variant="secondary" class="gap-1">
                                    <Clock class="size-3" />
                                    En attente
                                </Badge>
                                <Badge
                                    v-if="school.has_conflict"
                                    class="gap-1 bg-amber-100 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/40 dark:text-amber-400"
                                >
                                    <AlertTriangle class="size-3" />
                                    Conflit de nom
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="flex-1 space-y-2 text-sm">
                        <!-- Avertissement conflit -->
                        <div
                            v-if="school.has_conflict"
                            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                        >
                            Un établissement actif porte déjà ce nom. L'approbation créera un doublon.
                        </div>

                        <div v-if="school.email" class="flex items-center gap-2 text-muted-foreground">
                            <span class="w-16 shrink-0 font-medium text-foreground">Email</span>
                            <span class="truncate">{{ school.email }}</span>
                        </div>
                        <div v-if="school.phone_number" class="flex items-center gap-2 text-muted-foreground">
                            <span class="w-16 shrink-0 font-medium text-foreground">Tél.</span>
                            <span>{{ school.phone_number }}</span>
                        </div>
                        <div v-if="school.address" class="flex items-start gap-2 text-muted-foreground">
                            <span class="w-16 shrink-0 font-medium text-foreground">Adresse</span>
                            <span>{{ school.address }}</span>
                        </div>
                        <div v-if="school.description" class="rounded-lg bg-muted p-2 text-xs text-muted-foreground">
                            {{ school.description }}
                        </div>
                        <p class="pt-1 text-xs text-muted-foreground">
                            Soumis le {{ formatDate(school.created_at) }}
                        </p>
                    </CardContent>

                    <CardFooter class="gap-2 pt-3">
                        <Button
                            class="flex-1 gap-1"
                            :class="school.has_conflict ? 'bg-amber-500 hover:bg-amber-600 text-white' : ''"
                            :variant="school.has_conflict ? undefined : 'default'"
                            size="sm"
                            :disabled="approveProcessing"
                            @click="approve(school)"
                        >
                            <AlertTriangle v-if="school.has_conflict" class="size-4" />
                            <CheckCircle2 v-else class="size-4" />
                            Approuver
                        </Button>
                        <Button variant="destructive" class="flex-1 gap-1" size="sm" @click="openRejectDialog(school)">
                            <XCircle class="size-4" />
                            Refuser
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="schools.last_page > 1" class="mt-6 flex justify-center gap-2">
                <Button v-if="schools.prev_page_url" variant="outline" size="sm" as-child>
                    <Link :href="schools.prev_page_url">← Précédent</Link>
                </Button>
                <span class="flex items-center px-3 text-sm text-muted-foreground">
                    {{ schools.current_page }} / {{ schools.last_page }}
                </span>
                <Button v-if="schools.next_page_url" variant="outline" size="sm" as-child>
                    <Link :href="schools.next_page_url">Suivant →</Link>
                </Button>
            </div>
        </div>

        <!-- Dialog approbation avec conflit -->
        <Dialog v-model:open="approveDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="size-5 text-amber-500" />
                        Conflit de nom détecté
                    </DialogTitle>
                    <DialogDescription>
                        Un établissement actif porte déjà le nom
                        <strong>« {{ schoolToApprove?.name }} »</strong>.
                        Approuver cette demande créera un doublon dans le système.
                    </DialogDescription>
                </DialogHeader>
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                    Confirmez uniquement si vous êtes certain qu'il s'agit d'un établissement distinct (ex&nbsp;: même nom, ville différente).
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="approveDialogOpen = false">Annuler</Button>
                    <Button
                        class="bg-amber-500 hover:bg-amber-600 text-white"
                        :disabled="approveProcessing"
                        @click="confirmApprove"
                    >
                        Approuver quand même
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog refus -->
        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Refuser la demande</DialogTitle>
                    <DialogDescription>
                        Vous allez refuser la demande de <strong>{{ selectedSchool?.name }}</strong>.
                        Une raison optionnelle sera enregistrée.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2 py-2">
                    <Label for="reason">Raison (optionnel)</Label>
                    <Input
                        id="reason"
                        v-model="rejectForm.reason"
                        placeholder="ex : Informations insuffisantes"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rejectDialogOpen = false">Annuler</Button>
                    <Button variant="destructive" :disabled="rejectForm.processing" @click="confirmReject">
                        Confirmer le refus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
