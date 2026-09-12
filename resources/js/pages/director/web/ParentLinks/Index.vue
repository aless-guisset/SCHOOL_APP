<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { UserRound, X } from 'lucide-vue-next';
import { ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslation } from '@/composables/useTranslation';

interface ParentLink {
    id: number;
    parent_name: string;
    parent_email: string;
    student_name: string;
    linked_at: string;
}

const props = defineProps<{ links: ParentLink[] }>();
const { t } = useTranslation();

const revokingId = ref<number | null>(null);

function revoke(link: ParentLink) {
    if (!confirm(t('parent.confirm_revoke', { parent: link.parent_name, student: link.student_name }))) return;
    revokingId.value = link.id;
    router.delete(`/parent-links/${link.id}`, {
        preserveScroll: true,
        onFinish: () => { revokingId.value = null; },
    });
}
</script>

<template>
    <Head :title="t('nav.parent_links')" />
    <AppLayout>
        <div class="p-4 md:p-6 space-y-6">
            <FlashMessage />
            <PageHeader
                :title="t('nav.parent_links')"
                :description="t('parent.links_desc')"
            />
            <Card>
                <CardHeader><CardTitle class="text-base">{{ t('parent.active_links', { count: String(props.links.length) }) }}</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <p v-if="props.links.length === 0" class="text-sm text-muted-foreground">{{ t('parent.no_links') }}</p>
                    <div
                        v-for="link in props.links" :key="link.id"
                        class="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <UserRound class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-medium">{{ link.parent_name }} <span class="font-normal text-muted-foreground">→ {{ link.student_name }}</span></p>
                                <p class="text-xs text-muted-foreground">{{ t('parent.link_meta', { email: link.parent_email, date: link.linked_at }) }}</p>
                            </div>
                        </div>
                        <Button
                            variant="outline" size="icon" class="size-8"
                            :disabled="revokingId === link.id"
                            @click="revoke(link)"
                        >
                            <X class="size-4 text-destructive" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
