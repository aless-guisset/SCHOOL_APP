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
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <!-- Actions slot (boutons Create, etc.) -->
            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
