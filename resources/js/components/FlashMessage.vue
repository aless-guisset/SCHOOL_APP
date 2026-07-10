<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Info, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage<{ flash?: { type: 'success' | 'error' | 'info' | 'warning'; message: string } | null }>();

const visible = ref(false);
const flash = computed(() => page.props.flash);

watch(flash, (val) => {
    if (val?.message) {
        visible.value = true;
        setTimeout(() => { visible.value = false; }, 4000);
    }
}, { immediate: true });

const icons = {
    success: CheckCircle2,
    error: XCircle,
    warning: AlertCircle,
    info: Info,
};

const styles = {
    success: 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-400',
    error:   'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400',
    warning: 'border-yellow-500/30 bg-yellow-500/10 text-yellow-700 dark:text-yellow-400',
    info:    'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-400',
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="visible && flash"
            class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
            :class="styles[flash.type ?? 'info']"
        >
            <component :is="icons[flash.type ?? 'info']" class="mt-0.5 size-4 shrink-0" />
            <span>{{ flash.message }}</span>
            <button class="ml-auto shrink-0 opacity-60 hover:opacity-100" @click="visible = false">✕</button>
        </div>
    </Transition>
</template>
