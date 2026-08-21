<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

interface Breadcrumb {
    label: string;
    href?: string;
}

withDefaults(defineProps<{
    title: string;
    description?: string;
    breadcrumbs?: Breadcrumb[];
}>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="mb-6">
        <!-- Breadcrumb -->
        <nav v-if="breadcrumbs.length" class="mb-2 flex items-center gap-1 text-xs text-muted-foreground">
            <template v-for="(crumb, i) in breadcrumbs" :key="i">
                <ChevronRight v-if="i > 0" class="size-3 shrink-0" />
                <Link
                    v-if="crumb.href"
                    :href="crumb.href"
                    class="hover:text-foreground transition-colors"
                >
                    {{ crumb.label }}
                </Link>
                <span v-else class="text-foreground font-medium">{{ crumb.label }}</span>
            </template>
        </nav>

        <!-- Title + description -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <!-- Actions slot (boutons Create, etc.) -->
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 sm:shrink-0">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
