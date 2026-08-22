<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

type RosterEntry = {
    cantine_registration_id: number;
    name: string;
    section: string | null;
    is_present: boolean;
    note: string | null;
};

const props = defineProps<{
    date: string;
    roster: RosterEntry[];
}>();

const breadcrumbs = [
    { label: 'Cantine', href: '/cantine' },
    { label: `Présences du ${props.date}` },
];

const presenceForm = useForm({
    date: props.date,
    presences: props.roster.map(r => ({
        cantine_registration_id: r.cantine_registration_id,
        is_present: r.is_present,
        note: r.note ?? '',
    })),
});

function savePresences() {
    presenceForm.post('/cantine/roster', { preserveScroll: true });
}

function changeDate(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    if (value) router.get('/cantine/roster', { date: value });
}
</script>

<template>
    <Head :title="`Cantine — ${date}`" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-xl">
            <FlashMessage />
            <PageHeader :title="`Présences cantine — ${date}`" :breadcrumbs="breadcrumbs">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/cantine">Retour aux inscriptions</Link>
                    </Button>
                </template>
            </PageHeader>

            <div class="mb-4 max-w-xs space-y-1.5">
                <label class="text-sm font-medium" for="roster-date">Date</label>
                <Input id="roster-date" type="date" :value="date" @change="changeDate" />
            </div>

            <Card>
                <CardHeader><CardTitle class="text-base">Élèves inscrits ce jour</CardTitle></CardHeader>
                <CardContent>
                    <div v-if="roster.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Aucun élève inscrit à la cantine ce jour.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="(entry, i) in presenceForm.presences" :key="entry.cantine_registration_id"
                            class="flex items-center justify-between gap-3 border-b border-border pb-3 last:border-0"
                        >
                            <div class="flex flex-col">
                                <span class="text-sm font-medium">{{ roster[i].name }}</span>
                                <span class="text-xs text-muted-foreground">{{ roster[i].section }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Input
                                    v-if="!entry.is_present"
                                    v-model="entry.note"
                                    placeholder="Note (optionnel)"
                                    class="h-8 w-40 text-xs"
                                />
                                <Button
                                    :variant="entry.is_present ? 'outline' : 'destructive'"
                                    size="sm"
                                    @click="entry.is_present = !entry.is_present"
                                >{{ entry.is_present ? 'Présent' : 'Absent' }}</Button>
                            </div>
                        </div>
                        <Button class="mt-2" :disabled="presenceForm.processing" @click="savePresences">
                            Enregistrer les présences
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
