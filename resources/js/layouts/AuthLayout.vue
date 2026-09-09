<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import LanguagePicker from '@/components/LanguagePicker.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <LanguagePicker />
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>
