<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSchool } from '@/composables/useSchool';
import AppLayout from '@/layouts/AppLayout.vue';

const { canManage } = useSchool();

type Menu = { id: number; label: string; description: string | null };
type RosterEntry = {
    id: number; name: string; section: string | null; menu_label: string | null;
    is_present: boolean; note: string | null;
};

const props = defineProps<{
    date: string;
    is_past: boolean;
    menus: Menu[];
    roster?: RosterEntry[];
    can_order?: boolean;
    my_order?: { id: number; cantine_menu_id: number } | null;
}>();

// ── Navigation par date ──────────────────────────────────────────────────────
function shiftDate(days: number) {
    const d = new Date(`${props.date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    router.get('/cantine', { date: d.toISOString().slice(0, 10) }, { preserveScroll: true });
}
function goToday() {
    router.get('/cantine', {}, { preserveScroll: true });
}
const formattedDate = computed(() => {
    const d = new Date(`${props.date}T00:00:00Z`);
    return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
});
// Même règle que le backend (storePresences) : les présences ne peuvent être
// prises que pour aujourd'hui ou une date passée, jamais pour le futur.
const isFutureDate = computed(() => props.date > new Date().toISOString().slice(0, 10));

// ── Staff : ajout d'une option de menu ──────────────────────────────────────
const menuForm = useForm({ date: props.date, label: '', description: '' });
function addMenu() {
    menuForm.date = props.date;
    menuForm.post('/cantine/menus', {
        preserveScroll: true,
        onSuccess: () => { menuForm.reset('label', 'description'); },
    });
}
function removeMenu(id: number) {
    if (confirm('Retirer cette option de menu ?')) router.delete(`/cantine/menus/${id}`, { preserveScroll: true });
}

// ── Staff : présences ────────────────────────────────────────────────────────
const presenceForm = useForm({
    date: props.date,
    presences: (props.roster ?? []).map(r => ({ cantine_order_id: r.id, is_present: r.is_present, note: r.note ?? '' })),
});
watch(() => props.roster, (newRoster) => {
    presenceForm.presences = (newRoster ?? []).map(r => ({ cantine_order_id: r.id, is_present: r.is_present, note: r.note ?? '' }));
});
function savePresences() {
    presenceForm.date = props.date;
    presenceForm.post('/cantine/presence', { preserveScroll: true });
}

// ── Élève : commander ────────────────────────────────────────────────────────
const ordering = ref(false);
function order(menuId: number) {
    ordering.value = true;
    router.post('/cantine/orders', { cantine_menu_id: menuId, date: props.date }, {
        preserveScroll: true,
        onFinish: () => { ordering.value = false; },
    });
}
function cancelOrder() {
    if (!props.my_order) return;
    if (confirm('Annuler cette commande ?')) router.delete(`/cantine/orders/${props.my_order.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Cantine" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />

            <PageHeader title="Cantine" :description="formattedDate">
                <template #actions>
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" @click="shiftDate(-1)"><ChevronLeft class="size-4" /></Button>
                        <Button variant="outline" size="sm" @click="goToday">Aujourd'hui</Button>
                        <Button variant="outline" size="icon" @click="shiftDate(1)"><ChevronRight class="size-4" /></Button>
                    </div>
                </template>
            </PageHeader>

            <!-- Menu du jour -->
            <Card class="mb-4">
                <CardHeader><CardTitle class="text-base">Menu du jour</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <div v-if="!menus.length" class="text-sm text-muted-foreground">Aucun menu publié pour ce jour.</div>

                    <!-- Élève : commander -->
                    <template v-if="!canManage && can_order">
                        <button
                            v-for="m in menus" :key="m.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-md border p-3 text-left text-sm transition-colors"
                            :class="my_order?.cantine_menu_id === m.id ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted/40'"
                            :disabled="is_past || ordering"
                            @click="order(m.id)"
                        >
                            <span>
                                <span class="font-medium">{{ m.label }}</span>
                                <span v-if="m.description" class="block text-xs text-muted-foreground">{{ m.description }}</span>
                            </span>
                            <Badge v-if="my_order?.cantine_menu_id === m.id" variant="default">Commandé</Badge>
                        </button>
                        <Button v-if="my_order && !is_past" variant="outline" size="sm" @click="cancelOrder">
                            <Trash2 class="size-4" />Annuler ma commande
                        </Button>
                    </template>

                    <!-- Lecture seule (Directeur, ou élève sans droit de commande) -->
                    <template v-else-if="!canManage">
                        <div v-for="m in menus" :key="m.id" class="rounded-md border border-border p-3 text-sm">
                            <span class="font-medium">{{ m.label }}</span>
                            <span v-if="m.description" class="block text-xs text-muted-foreground">{{ m.description }}</span>
                        </div>
                    </template>

                    <!-- Staff : liste + ajout -->
                    <template v-else>
                        <div v-for="m in menus" :key="m.id" class="flex items-center justify-between rounded-md border border-border p-3 text-sm">
                            <span>
                                <span class="font-medium">{{ m.label }}</span>
                                <span v-if="m.description" class="block text-xs text-muted-foreground">{{ m.description }}</span>
                            </span>
                            <Button variant="ghost" size="icon" class="size-8" @click="removeMenu(m.id)">
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </div>
                        <form class="flex flex-wrap items-end gap-2 pt-2" @submit.prevent="addMenu">
                            <div class="space-y-1.5">
                                <Label class="text-xs">Option</Label>
                                <Input v-model="menuForm.label" placeholder="ex: Plat A" class="h-8 w-32" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">Description</Label>
                                <Input v-model="menuForm.description" placeholder="ex: Pâtes bolognaise" class="h-8 w-48" />
                            </div>
                            <Button type="submit" size="sm" :disabled="menuForm.processing || !menuForm.label">
                                <Plus class="size-4" />Ajouter
                            </Button>
                        </form>
                        <p v-if="menuForm.errors.label" class="text-xs text-destructive">{{ menuForm.errors.label }}</p>
                    </template>
                </CardContent>
            </Card>

            <!-- Staff : roster + présences -->
            <Card v-if="canManage">
                <CardHeader><CardTitle class="text-base">Élèves ayant commandé</CardTitle></CardHeader>
                <CardContent>
                    <div v-if="!roster || roster.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Aucune commande pour ce jour.
                    </div>
                    <div v-else-if="isFutureDate" class="py-6 text-center text-sm text-muted-foreground">
                        Les présences ne peuvent être prises qu'après le jour concerné.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="(entry, i) in presenceForm.presences" :key="entry.cantine_order_id"
                            class="flex items-center justify-between gap-3 border-b border-border pb-3 last:border-0"
                        >
                            <div class="flex flex-col">
                                <span class="text-sm font-medium">{{ roster[i].name }}</span>
                                <span class="text-xs text-muted-foreground">{{ roster[i].section }} — {{ roster[i].menu_label }}</span>
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
