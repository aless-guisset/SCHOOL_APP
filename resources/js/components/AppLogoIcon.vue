<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 100 100"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <path d="M10 55c15-7 30-7 40 0" stroke="currentColor" stroke-width="7" stroke-linecap="round" fill="none" />
        <path d="M90 55c-15-7-30-7-40 0" stroke="currentColor" stroke-width="7" stroke-linecap="round" fill="none" />
        <path d="M10 55v25c15-7 30-7 40 0v-25" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        <path d="M90 55v25c-15-7-30-7-40 0v-25" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        <circle cx="50" cy="20" r="7" fill="currentColor" />
        <path d="M50 30v22M50 30 34 16M50 30 66 16" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </svg>
</template>
