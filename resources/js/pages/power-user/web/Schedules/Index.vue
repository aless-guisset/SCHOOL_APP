<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Copy, List, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription,
    DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();

type Schedule = {
    id: number;
    name: string;
    day_of_week: number;   // 1=Lun … 7=Dim
    start_time: string;    // "HH:MM:SS"
    end_time: string;
    is_active: boolean;
    section_course: { name: string; course: { name: string } } | null;
};

const props = defineProps<{ schedules: Schedule[] }>();

// ── Toggle vue ───────────────────────────────────────────────────────────────
const viewMode = ref<'calendar' | 'list'>('calendar');

// ── Calendrier : constantes ───────────────────────────────────────────────
const DAY_LABELS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
const HOUR_START = 7;
const HOUR_END   = 20;
const TOTAL_HOURS = HOUR_END - HOUR_START;
const HOUR_PX    = 64;

const hours = Array.from({ length: TOTAL_HOURS + 1 }, (_, i) => HOUR_START + i);

// Affiche lun-ven + les jours utilisés au-delà
const activeDays = computed(() => {
    const used = new Set(props.schedules.map(s => s.day_of_week));
    return [1, 2, 3, 4, 5, 6, 7].filter(d => d <= 5 || used.has(d));
});

const byDay = computed(() => {
    const map: Record<number, Schedule[]> = {};
    for (const s of props.schedules) {
        (map[s.day_of_week] ??= []).push(s);
    }
    return map;
});

function toMin(time: string): number {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
}

function slotStyle(s: Schedule) {
    const startMin = toMin(s.start_time) - HOUR_START * 60;
    const duration = toMin(s.end_time)   - toMin(s.start_time);
    return {
        top:    `${(startMin / 60) * HOUR_PX}px`,
        height: `${Math.max((duration / 60) * HOUR_PX - 2, 20)}px`,
    };
}

function fmt(t: string) { return t.substring(0, 5); }

const SLOT_COLORS: Record<number, string> = {
    1: 'bg-blue-100 border-blue-300 text-blue-900 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-200',
    2: 'bg-green-100 border-green-300 text-green-900 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200',
    3: 'bg-purple-100 border-purple-300 text-purple-900 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-200',
    4: 'bg-orange-100 border-orange-300 text-orange-900 dark:bg-orange-900/30 dark:border-orange-700 dark:text-orange-200',
    5: 'bg-rose-100 border-rose-300 text-rose-900 dark:bg-rose-900/30 dark:border-rose-700 dark:text-rose-200',
    6: 'bg-teal-100 border-teal-300 text-teal-900 dark:bg-teal-900/30 dark:border-teal-700 dark:text-teal-200',
    7: 'bg-gray-100 border-gray-300 text-gray-900 dark:bg-gray-800/40 dark:border-gray-600 dark:text-gray-200',
};

// ── Duplication ──────────────────────────────────────────────────────────────
const dupOpen    = ref(false);
const dupLoading = ref(false);
const dupError   = ref('');
const dupSuccess = ref('');
const dupForm    = ref({ source_week_start: '', year_end: '' });

function openDup() {
    dupError.value = dupSuccess.value = '';
    dupOpen.value  = true;
}

function submitDup() {
    dupLoading.value = true;
    dupError.value   = dupSuccess.value = '';

    router.post('/planning/duplicate', dupForm.value, {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = (page.props as { flash?: { message?: string } }).flash;
            dupSuccess.value = flash?.message ?? 'Duplication effectuée.';
            dupLoading.value = false;
        },
        onError: (errors) => {
            dupError.value   = Object.values(errors).flat().join(' ');
            dupLoading.value = false;
        },
    });
}
</script>

<template>
    <Head title="Horaires" />
    <AppLayout>
        <div class="p-4 md:p-6">
            <FlashMessage />

            <PageHeader :title="t('nav.schedules')" :description="`${schedules.length} créneau(x)`">
                <template #actions>
                    <!-- Toggle calendrier / liste -->
                    <div class="flex items-center overflow-hidden rounded-md border border-border">
                        <button
                            class="px-2.5 py-1.5 transition-colors"
                            :class="viewMode === 'calendar' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                            @click="viewMode = 'calendar'"
                            title="Vue calendrier"
                        ><CalendarDays class="size-4" /></button>
                        <button
                            class="px-2.5 py-1.5 transition-colors"
                            :class="viewMode === 'list' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                            @click="viewMode = 'list'"
                            title="Vue liste"
                        ><List class="size-4" /></button>
                    </div>
                    <Button variant="outline" size="sm" @click="openDup">
                        <Copy class="size-4" />Dupliquer
                    </Button>
                    <Button as-child size="sm">
                        <Link href="/schedules/create"><Plus class="size-4" />{{ t('action.create') }}</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- ─── Calendrier hebdomadaire ─────────────────────────────── -->
            <div v-if="viewMode === 'calendar'" class="mt-4 overflow-x-auto rounded-md border border-border">
                <div :style="`min-width: ${48 + activeDays.length * 120}px`">

                    <!-- En-têtes -->
                    <div
                        class="grid border-b border-border bg-muted/40"
                        :style="`grid-template-columns: 48px repeat(${activeDays.length}, 1fr)`"
                    >
                        <div />
                        <div
                            v-for="day in activeDays" :key="day"
                            class="flex h-10 items-center justify-center border-l border-border text-xs font-semibold text-muted-foreground"
                        >{{ DAY_LABELS[day - 1] }}</div>
                    </div>

                    <!-- Corps grille -->
                    <div
                        class="grid"
                        :style="`grid-template-columns: 48px repeat(${activeDays.length}, 1fr); height: ${TOTAL_HOURS * HOUR_PX}px`"
                    >
                        <!-- Labels heures -->
                        <div class="relative border-r border-border bg-muted/20">
                            <div
                                v-for="h in hours.slice(0, -1)" :key="h"
                                class="absolute right-0 left-0 flex items-start justify-end pr-1.5"
                                :style="`top: ${(h - HOUR_START) * HOUR_PX}px`"
                            >
                                <span class="mt-0.5 text-[10px] leading-none text-muted-foreground">{{ h }}h</span>
                            </div>
                        </div>

                        <!-- Colonnes jours -->
                        <div
                            v-for="day in activeDays" :key="day"
                            class="relative border-l border-border"
                        >
                            <!-- Séparations heures -->
                            <div
                                v-for="h in hours.slice(0, -1)" :key="h"
                                class="pointer-events-none absolute inset-x-0 border-t border-border/30"
                                :style="`top: ${(h - HOUR_START) * HOUR_PX}px; height: ${HOUR_PX}px`"
                            />

                            <!-- Créneaux -->
                            <Link
                                v-for="s in (byDay[day] ?? [])"
                                :key="s.id"
                                :href="`/schedules/${s.id}`"
                                class="absolute inset-x-1 overflow-hidden rounded border px-1.5 py-1 text-xs transition-opacity hover:opacity-80"
                                :class="SLOT_COLORS[day]"
                                :style="slotStyle(s)"
                            >
                                <p class="truncate font-semibold leading-tight">{{ s.name }}</p>
                                <p class="truncate text-[10px] opacity-75">{{ fmt(s.start_time) }}–{{ fmt(s.end_time) }}</p>
                                <p v-if="s.section_course" class="truncate text-[10px] opacity-60">{{ s.section_course.course?.name }}</p>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Vue liste ──────────────────────────────────────────────── -->
            <div v-else class="mt-4 overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Jour</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Horaire</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Nom</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Cours</th>
                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!schedules.length">
                            <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">Aucun créneau.</td>
                        </tr>
                        <tr
                            v-for="s in schedules" :key="s.id"
                            class="cursor-pointer border-b hover:bg-muted/30"
                            @click="router.visit(`/schedules/${s.id}`)"
                        >
                            <td class="px-3 py-2">{{ DAY_LABELS[s.day_of_week - 1] }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ fmt(s.start_time) }} – {{ fmt(s.end_time) }}</td>
                            <td class="px-3 py-2 font-medium">{{ s.name }}</td>
                            <td class="px-3 py-2 text-muted-foreground">{{ s.section_course?.course?.name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <Badge :variant="s.is_active ? 'default' : 'secondary'">
                                    {{ s.is_active ? t('label.active') : t('label.inactive') }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─── Dialog duplication ─────────────────────────────────────── -->
        <Dialog v-model:open="dupOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Dupliquer la semaine type</DialogTitle>
                    <DialogDescription>
                        Sélectionnez une semaine de référence (feuilles de temps déjà saisies).
                        Chaque entrée sera copiée semaine par semaine jusqu'à la date de fin.
                        Les doublons existants sont ignorés.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label>Lundi de la semaine source *</Label>
                        <Input v-model="dupForm.source_week_start" type="date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Date de fin (fin d'année scolaire) *</Label>
                        <Input v-model="dupForm.year_end" type="date" />
                    </div>
                    <p v-if="dupError"   class="text-xs text-destructive">{{ dupError }}</p>
                    <p v-if="dupSuccess" class="text-xs text-green-600 dark:text-green-400">{{ dupSuccess }}</p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dupOpen = false">Annuler</Button>
                    <Button
                        :disabled="dupLoading || !dupForm.source_week_start || !dupForm.year_end"
                        @click="submitDup"
                    >
                        <Copy class="size-4" />
                        {{ dupLoading ? 'Génération…' : 'Dupliquer pour l\'année' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
