<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type StudentBalance = { id: number; name: string; balance: number };

defineProps<{
    students: StudentBalance[];
    can_write: boolean;
}>();
</script>

<template>
    <Head title="Soldes cantine" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <PageHeader title="Soldes cantine" description="Solde prépayé de chaque élève." />

            <Card>
                <CardContent class="p-0">
                    <div v-if="!students.length" class="py-6 text-center text-sm text-muted-foreground">Aucun élève.</div>
                    <Link
                        v-for="s in students" :key="s.id"
                        :href="`/cantine/wallet/${s.id}`"
                        class="flex items-center justify-between border-b border-border p-3 text-sm last:border-0 hover:bg-muted/40"
                    >
                        <span>{{ s.name }}</span>
                        <span class="font-medium" :class="s.balance < 0 ? 'text-destructive' : ''">{{ s.balance.toFixed(2) }} €</span>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
