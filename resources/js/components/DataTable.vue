<script setup lang="ts" generic="T extends Record<string, unknown>">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export interface Column<T> {
    key: keyof T | string;
    label: string;
    badge?: boolean;
    badgeVariant?: (row: T) => 'default' | 'secondary' | 'destructive' | 'outline';
    format?: (value: unknown, row: T) => string;
    class?: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
    next_page_url: string | null;
    prev_page_url: string | null;
}

const props = defineProps<{
    data: PaginatedData<T> | T[];
    columns: Column<T>[];
    rowHref?: (row: T) => string;
    emptyMessage?: string;
}>();

const isPaginated = (d: PaginatedData<T> | T[]): d is PaginatedData<T> => {
    return !Array.isArray(d) && 'data' in d;
};

const rows = () => isPaginated(props.data) ? props.data.data : props.data as T[];
const pagination = () => isPaginated(props.data) ? props.data : null;

function getCellValue(row: T, key: string): unknown {
    return key.split('.').reduce((obj: unknown, k) => {
        return obj && typeof obj === 'object' ? (obj as Record<string, unknown>)[k] : undefined;
    }, row);
}
</script>

<template>
    <div class="rounded-xl border border-border bg-card overflow-hidden">
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border bg-muted/40">
                        <th
                            v-for="col in columns"
                            :key="String(col.key)"
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            :class="col.class"
                        >
                            {{ col.label }}
                        </th>
                        <th v-if="$slots.actions" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, i) in rows()"
                        :key="i"
                        class="border-b border-border/50 last:border-0 transition-colors"
                        :class="rowHref ? 'hover:bg-muted/30 cursor-pointer' : ''"
                        @click="rowHref ? $inertia.visit(rowHref(row)) : undefined"
                    >
                        <td
                            v-for="col in columns"
                            :key="String(col.key)"
                            class="px-4 py-3"
                            :class="col.class"
                        >
                            <Badge
                                v-if="col.badge"
                                :variant="col.badgeVariant ? col.badgeVariant(row) : 'secondary'"
                            >
                                {{ col.format ? col.format(getCellValue(row, String(col.key)), row) : getCellValue(row, String(col.key)) }}
                            </Badge>
                            <span v-else class="text-foreground">
                                {{ col.format ? col.format(getCellValue(row, String(col.key)), row) : (getCellValue(row, String(col.key)) ?? '—') }}
                            </span>
                        </td>
                        <td v-if="$slots.actions" class="px-4 py-3 text-right" @click.stop>
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>

                    <!-- Ligne vide -->
                    <tr v-if="rows().length === 0">
                        <td
                            :colspan="columns.length + ($slots.actions ? 1 : 0)"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            {{ emptyMessage ?? 'Aucun élément trouvé.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="pagination() && pagination()!.last_page > 1"
            class="flex items-center justify-between border-t border-border px-4 py-3"
        >
            <p class="text-xs text-muted-foreground">
                {{ pagination()!.total }} résultat{{ pagination()!.total > 1 ? 's' : '' }}
            </p>
            <div class="flex items-center gap-1">
                <Link
                    v-if="pagination()!.prev_page_url"
                    :href="pagination()!.prev_page_url!"
                    preserve-scroll
                >
                    <Button variant="outline" size="icon" class="size-8">
                        <ChevronLeft class="size-4" />
                    </Button>
                </Link>
                <span class="px-2 text-xs">
                    {{ pagination()!.current_page }} / {{ pagination()!.last_page }}
                </span>
                <Link
                    v-if="pagination()!.next_page_url"
                    :href="pagination()!.next_page_url!"
                    preserve-scroll
                >
                    <Button variant="outline" size="icon" class="size-8">
                        <ChevronRight class="size-4" />
                    </Button>
                </Link>
            </div>
        </div>
    </div>
</template>
