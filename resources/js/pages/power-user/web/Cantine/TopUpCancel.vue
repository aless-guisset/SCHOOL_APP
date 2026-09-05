<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <Head title="Recharge annulée" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-md">
            <Card>
                <CardHeader><CardTitle class="text-base">Recharge annulée</CardTitle></CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        Aucun paiement n'a été effectué. Vous pouvez réessayer à tout moment.
                    </p>
                    <Link href="/cantine"><Button>Retour à la cantine</Button></Link>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
