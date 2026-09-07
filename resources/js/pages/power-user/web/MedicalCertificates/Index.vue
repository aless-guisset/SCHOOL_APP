<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

const breadcrumbs = [{ label: 'Certificats médicaux' }];
</script>

<template>
    <Head title="Certificats médicaux" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />
            <PageHeader title="Certificats médicaux" :breadcrumbs="breadcrumbs">
                <template v-if="props.is_certificate_staff" #actions>
                    <Button size="sm" as-child>
                        <Link href="/medical-certificates/create">Nouveau certificat</Link>
                    </Button>
                </template>
            </PageHeader>

            <div v-if="props.certificates.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                Aucun certificat.
            </div>
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="c in props.certificates" :key="c.id">
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
    </AppLayout>
</template>
