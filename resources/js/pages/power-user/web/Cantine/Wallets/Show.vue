<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Transaction = { id: number; type: string; amount: number; note: string | null; created_at: string };

const props = defineProps<{
    student_name: string;
    section_user_id: number;
    balance: number;
    transactions: Transaction[];
    can_write: boolean;
}>();

const typeLabels: Record<string, string> = {
    stripe_topup: 'Recharge en ligne',
    manual_credit: 'Crédit manuel',
    order_debit: 'Commande',
    order_refund: 'Remboursement',
};

const creditForm = useForm({ amount: 0, note: '' });
function credit() {
    creditForm.post(`/cantine/wallet/${props.section_user_id}/manual-credit`, {
        preserveScroll: true,
        onSuccess: () => creditForm.reset(),
    });
}

function voidCredit(id: number) {
    if (confirm('Annuler ce crédit manuel ?')) {
        creditForm.delete(`/cantine/wallet/manual-credit/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Solde — ${student_name}`" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="student_name" :description="`Solde actuel : ${balance.toFixed(2)} €`" />

            <Card v-if="can_write" class="mb-4">
                <CardHeader><CardTitle class="text-base">Créditer manuellement</CardTitle></CardHeader>
                <CardContent>
                    <form class="flex flex-wrap items-end gap-2" @submit.prevent="credit">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Montant (€)</Label>
                            <Input v-model.number="creditForm.amount" type="number" min="0.01" step="0.01" class="h-8 w-28" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Note</Label>
                            <Input v-model="creditForm.note" placeholder="ex: Espèces reçues le 12/09" class="h-8 w-56" />
                        </div>
                        <Button type="submit" size="sm" :disabled="creditForm.processing || creditForm.amount <= 0">Créditer</Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle class="text-base">Historique</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <div v-if="!transactions.length" class="py-6 text-center text-sm text-muted-foreground">Aucune transaction.</div>
                    <div v-for="t in transactions" :key="t.id" class="flex items-center justify-between border-b border-border pb-2 text-sm last:border-0">
                        <div>
                            <span>{{ typeLabels[t.type] ?? t.type }}</span>
                            <span v-if="t.note" class="block text-xs text-muted-foreground">{{ t.note }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span :class="t.amount < 0 ? 'text-destructive' : 'text-green-600'">{{ t.amount > 0 ? '+' : '' }}{{ t.amount.toFixed(2) }} €</span>
                            <button v-if="can_write && t.type === 'manual_credit'" class="text-xs text-muted-foreground underline" @click="voidCredit(t.id)">
                                Annuler
                            </button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
