<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

type Transaction = { id: number; type: string; amount: number; note: string | null; created_at: string };

const props = defineProps<{
    student_name: string;
    section_user_id: number;
    balance: number;
    transactions: Transaction[];
    can_write: boolean;
}>();

const { t } = useTranslation();

function typeLabel(type: string): string {
    const key: Record<string, string> = {
        stripe_topup: 'cantine.tx_type_stripe_topup',
        manual_credit: 'cantine.tx_type_manual_credit',
        order_debit: 'cantine.tx_type_order_debit',
        order_refund: 'cantine.tx_type_order_refund',
    };
    return key[type] ? t(key[type]) : type;
}

const creditForm = useForm({ amount: 0, note: '' });
function credit() {
    creditForm.post(`/cantine/wallet/${props.section_user_id}/manual-credit`, {
        preserveScroll: true,
        onSuccess: () => creditForm.reset(),
    });
}

function voidCredit(id: number) {
    if (confirm(t('cantine.confirm_void_credit'))) {
        creditForm.delete(`/cantine/wallet/manual-credit/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('cantine.wallet_head_title', { name: student_name })" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="student_name" :description="t('cantine.current_balance', { balance: balance.toFixed(2) })" />

            <Card v-if="can_write" class="mb-4">
                <CardHeader><CardTitle class="text-base">{{ t('cantine.credit_manually_title') }}</CardTitle></CardHeader>
                <CardContent>
                    <form class="flex flex-wrap items-end gap-2" @submit.prevent="credit">
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('cantine.amount_label') }}</Label>
                            <Input v-model.number="creditForm.amount" type="number" min="0.01" step="0.01" class="h-8 w-28" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('cantine.note_label') }}</Label>
                            <Input v-model="creditForm.note" :placeholder="t('cantine.note_example_placeholder')" class="h-8 w-56" />
                        </div>
                        <Button type="submit" size="sm" :disabled="creditForm.processing || creditForm.amount <= 0">{{ t('action.credit') }}</Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle class="text-base">{{ t('cantine.history_title') }}</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <div v-if="!transactions.length" class="py-6 text-center text-sm text-muted-foreground">{{ t('cantine.no_transactions') }}</div>
                    <div v-for="tx in transactions" :key="tx.id" class="flex items-center justify-between border-b border-border pb-2 text-sm last:border-0">
                        <div>
                            <span>{{ typeLabel(tx.type) }}</span>
                            <span v-if="tx.note" class="block text-xs text-muted-foreground">{{ tx.note }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span :class="tx.amount < 0 ? 'text-destructive' : 'text-green-600'">{{ tx.amount > 0 ? '+' : '' }}{{ tx.amount.toFixed(2) }} €</span>
                            <button v-if="can_write && tx.type === 'manual_credit'" class="text-xs text-muted-foreground underline" @click="voidCredit(tx.id)">
                                {{ t('action.cancel') }}
                            </button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
