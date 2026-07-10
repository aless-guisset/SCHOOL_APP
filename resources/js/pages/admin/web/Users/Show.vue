<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

const props = defineProps<{
    user: {
        id: number;
        firstname: string | null;
        lastname: string | null;
        email: string;
        phone_number: string | null;
        is_active: boolean;
        school_roles: Array<{ id: number; school: { name: string }; role: { name: string }; is_active: boolean }>;
    };
}>();

const breadcrumbs = [
    { label: t('nav.users'), href: '/users' },
    { label: `${props.user.lastname ?? ''} ${props.user.firstname ?? ''}`.trim() || props.user.email },
];

function destroy() {
    if (confirm('Supprimer cet utilisateur ?')) {
        router.delete(`/users/${props.user.id}`);
    }
}
</script>

<template>
    <Head :title="`${user.lastname ?? ''} ${user.firstname ?? ''}`.trim() || user.email" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader
                :title="`${user.lastname ?? ''} ${user.firstname ?? ''}`.trim() || user.email"
                :breadcrumbs="breadcrumbs"
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/users/${user.id}/edit`">
                            <Edit class="size-4" />{{ t('action.edit') }}
                        </Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="size-4" />{{ t('action.delete') }}
                    </Button>
                </template>
            </PageHeader>

            <Card>
                <CardHeader><CardTitle class="text-base">Informations</CardTitle></CardHeader>
                <CardContent>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.status') }}</dt>
                            <dd>
                                <Badge :variant="user.is_active ? 'default' : 'secondary'">
                                    {{ user.is_active ? t('label.active') : t('label.inactive') }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.email') }}</dt>
                            <dd>{{ user.email }}</dd>
                        </div>
                        <div v-if="user.phone_number" class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.phone') }}</dt>
                            <dd>{{ user.phone_number }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="user.school_roles?.length" class="mt-4">
                <CardHeader><CardTitle class="text-base">Rôles & Écoles</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-2 text-sm">
                        <li v-for="sr in user.school_roles" :key="sr.id" class="flex items-center justify-between">
                            <span>{{ sr.school?.name }}</span>
                            <Badge variant="outline">{{ sr.role?.name }}</Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
