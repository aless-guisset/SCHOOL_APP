<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Copy, Mail, RefreshCw, UserRound, X } from 'lucide-vue-next';
import { ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface LinkedParent {
    id: number;
    name: string;
    email: string;
}

interface PendingInvitation {
    id: number;
    email: string;
    expired: boolean;
    sent_at: string;
}

const props = defineProps<{
    access_code: string;
    parents: LinkedParent[];
    invitations: PendingInvitation[];
}>();

const copied = ref(false);

function copyCode() {
    navigator.clipboard.writeText(props.access_code);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

const revokingId = ref<number | null>(null);

function revokeParent(parent: LinkedParent) {
    if (!confirm(`Retirer l'accès de ${parent.name} ?`)) return;
    revokingId.value = parent.id;
    router.delete(`/my-access/parents/${parent.id}`, {
        preserveScroll: true,
        onFinish: () => { revokingId.value = null; },
    });
}

const cancelingId = ref<number | null>(null);

function cancelInvitation(invitation: PendingInvitation) {
    if (!confirm(`Annuler l'invitation envoyée à ${invitation.email} ?`)) return;
    cancelingId.value = invitation.id;
    router.delete(`/my-access/invitations/${invitation.id}`, {
        preserveScroll: true,
        onFinish: () => { cancelingId.value = null; },
    });
}
</script>

<template>
    <Head title="Donner l'accès" />
    <AppLayout>
        <div class="p-4 md:p-6 space-y-6">
            <FlashMessage />

            <PageHeader
                title="Donner l'accès"
                description="Permettez à un parent ou tuteur de suivre vos notes, votre horaire et vos présences"
            />

            <Card>
                <CardHeader><CardTitle class="text-base">Code d'accès</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-3">
                    <code class="rounded-md border border-border bg-muted/40 px-3 py-1.5 font-mono text-lg tracking-wider">
                        {{ props.access_code }}
                    </code>
                    <Button variant="outline" size="icon" @click="copyCode">
                        <Check v-if="copied" class="size-4" /><Copy v-else class="size-4" />
                    </Button>
                    <Form method="post" action="/my-access/regenerate-code" v-slot="{ processing }">
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

            <Card>
                <CardHeader><CardTitle class="text-base">Inviter par email</CardTitle></CardHeader>
                <CardContent>
                    <Form action="/my-access/invitations" method="post" reset-on-success v-slot="{ errors, processing }" class="flex flex-wrap items-end gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Email du parent/tuteur</Label>
                            <Input name="email" type="email" required class="h-9 w-64" placeholder="parent@example.com" />
                            <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email }}</p>
                        </div>
                        <Button type="submit" size="sm" :disabled="processing">Envoyer l'invitation</Button>
                    </Form>
                </CardContent>
            </Card>

            <Card v-if="props.invitations.length">
                <CardHeader><CardTitle class="text-base">Invitations en attente ({{ props.invitations.length }})</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-for="invitation in props.invitations" :key="invitation.id"
                        class="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <Mail class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-medium">{{ invitation.email }}</p>
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

            <Card>
                <CardHeader><CardTitle class="text-base">Parents/tuteurs liés ({{ props.parents.length }})</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <p v-if="props.parents.length === 0" class="text-sm text-muted-foreground">Aucun parent lié pour le moment.</p>
                    <div
                        v-for="parent in props.parents" :key="parent.id"
                        class="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <UserRound class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-medium">{{ parent.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ parent.email }}</p>
                            </div>
                        </div>
                        <Button
                            variant="outline" size="icon" class="size-8"
                            :disabled="revokingId === parent.id"
                            @click="revokeParent(parent)"
                        >
                            <X class="size-4 text-destructive" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
