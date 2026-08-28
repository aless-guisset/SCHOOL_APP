<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Clock, UserRound, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

interface AccessRequest {
    id: number;
    name: string;
    email: string;
    role: string;
    requested_at: string;
}

const props = defineProps<{
    requests: AccessRequest[];
}>();

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
</script>

<template>
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />

            <PageHeader
                title="Demandes d'accès"
                :description="`${props.requests.length} demande(s) à traiter`"
            />

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
        </div>
    </AppLayout>
</template>
