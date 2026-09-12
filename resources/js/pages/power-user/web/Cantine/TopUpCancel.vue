<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
</script>

<template>
    <Head :title="t('cantine.topup_cancelled_title')" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-md">
            <Card>
                <CardHeader><CardTitle class="text-base">{{ t('cantine.topup_cancelled_title') }}</CardTitle></CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        {{ t('cantine.topup_cancelled_desc') }}
                    </p>
                    <Link href="/cantine"><Button>{{ t('cantine.back_to_cantine') }}</Button></Link>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
