<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Check, CheckCircle2, Clock, Copy, Mail, RefreshCw, UserRound, X, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

interface AccessRequest {
    id: number;
    name: string;
    email: string;
    role: string;
    requested_at: string;
}

interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    expired: boolean;
    sent_at: string;
}

const props = defineProps<{
    requests: AccessRequest[];
    invitations: PendingInvitation[];
}>();

const page = usePage<{ school: { access_code: string | null } | null }>();
const copied = ref(false);

function copyCode() {
    const code = page.props.school?.access_code;
    if (!code) return;
    navigator.clipboard.writeText(code);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

const processingId = ref<number | null>(null);

function approve(request: AccessRequest) {
    processingId.value = request.id;
    router.post(`/access-requests/${request.id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
}

function reject(request: AccessRequest) {
    processingId.value = request.id;
    router.post(`/access-requests/${request.id}/reject`, {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
}

const cancelingId = ref<number | null>(null);

function cancelInvitation(invitation: PendingInvitation) {
    if (!confirm(`Annuler l'invitation envoyée à ${invitation.email} ?`)) return;
    cancelingId.value = invitation.id;
    router.delete(`/invitations/${invitation.id}`, {
        preserveScroll: true,
        onFinish: () => { cancelingId.value = null; },
    });
}
</script>

<template>
    <Head title="Gestion des accès" />
    <AppLayout>
        <div class="p-4 md:p-6 space-y-6">
            <FlashMessage />

            <PageHeader
                title="Gestion des accès"
                description="Code d'accès, invitations et demandes en attente"
            />

            <!-- Code d'accès -->
            <Card>
                <CardHeader><CardTitle class="text-base">Code d'accès</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-3">
                    <code class="rounded-md border border-border bg-muted/40 px-3 py-1.5 font-mono text-lg tracking-wider">
                        {{ page.props.school?.access_code ?? '—' }}
                    </code>
                    <Button variant="outline" size="icon" @click="copyCode">
                        <Check v-if="copied" class="size-4" /><Copy v-else class="size-4" />
                    </Button>
                    <Form method="post" action="/school/access-code/regenerate" v-slot="{ processing }">
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            @click="(e: Event) => { if (!confirm('Régénérer le code ? L\'ancien cessera de fonctionner immédiatement.')) e.preventDefault(); }"
                        >
                            <RefreshCw class="size-4" />Régénérer
                        </Button>
                    </Form>
                </CardContent>
            </Card>

            <!-- Inviter par email -->
            <Card>
                <CardHeader><CardTitle class="text-base">Inviter par email</CardTitle></CardHeader>
                <CardContent>
                    <Form action="/invitations" method="post" reset-on-success v-slot="{ errors, processing }" class="flex flex-wrap items-end gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Email</Label>
                            <Input name="email" type="email" required class="h-9 w-56" placeholder="email@ecole.com" />
                            <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Rôle</Label>
                            <Select name="role_reference">
                                <SelectTrigger class="h-9 w-40"><SelectValue placeholder="Choisir" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="PROF">Professeur</SelectItem>
                                    <SelectItem value="SEC">Secrétariat</SelectItem>
                                    <SelectItem value="POWER">Power User</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.role_reference" class="text-xs text-destructive">{{ errors.role_reference }}</p>
                        </div>
                        <Button type="submit" size="sm" :disabled="processing">Envoyer l'invitation</Button>
                    </Form>
                </CardContent>
            </Card>

            <!-- Invitations en attente -->
            <Card v-if="props.invitations.length">
                <CardHeader>
                    <CardTitle class="text-base">Invitations en attente ({{ props.invitations.length }})</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-for="invitation in props.invitations" :key="invitation.id"
                        class="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <Mail class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-medium">{{ invitation.email }} <span class="font-normal text-muted-foreground">— {{ invitation.role }}</span></p>
                                <p class="text-xs text-muted-foreground">
                                    Envoyée {{ invitation.sent_at }}
                                    <Badge v-if="invitation.expired" variant="destructive" class="ml-1">Expirée</Badge>
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="outline" size="icon" class="size-8"
                            :disabled="cancelingId === invitation.id"
                            @click="cancelInvitation(invitation)"
                        >
                            <X class="size-4 text-destructive" />
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Demandes en attente -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Demandes en attente ({{ props.requests.length }})</CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Aucune demande -->
                    <div
                        v-if="props.requests.length === 0"
                        class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border py-16 text-center"
                    >
                        <CheckCircle2 class="size-10 text-green-500" />
                        <p class="font-semibold">Aucune demande en attente</p>
                        <p class="text-sm text-muted-foreground">Toutes les demandes ont été traitées.</p>
                    </div>

                    <!-- Liste des demandes -->
                    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="request in props.requests" :key="request.id" class="flex flex-col">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <UserRound class="size-5 shrink-0 text-primary" />
                                        <CardTitle class="text-base leading-tight">{{ request.name }}</CardTitle>
                                    </div>
                                    <Badge variant="secondary" class="gap-1">
                                        <Clock class="size-3" />
                                        En attente
                                    </Badge>
                                </div>
                            </CardHeader>

                            <CardContent class="flex-1 space-y-2 text-sm">
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <span class="w-16 shrink-0 font-medium text-foreground">Email</span>
                                    <span class="truncate">{{ request.email }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <span class="w-16 shrink-0 font-medium text-foreground">Rôle</span>
                                    <span>{{ request.role }}</span>
                                </div>
                                <p class="pt-1 text-xs text-muted-foreground">
                                    Demandé {{ request.requested_at }}
                                </p>
                            </CardContent>

                            <CardFooter class="gap-2 pt-3">
                                <Button
                                    class="flex-1 gap-1"
                                    size="sm"
                                    :disabled="processingId === request.id"
                                    @click="approve(request)"
                                >
                                    <CheckCircle2 class="size-4" />
                                    Approuver
                                </Button>
                                <Button
                                    variant="destructive"
                                    class="flex-1 gap-1"
                                    size="sm"
                                    :disabled="processingId === request.id"
                                    @click="reject(request)"
                                >
                                    <XCircle class="size-4" />
                                    Refuser
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
