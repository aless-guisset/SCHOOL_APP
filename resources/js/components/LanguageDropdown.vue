<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const LABELS: Record<string, string> = { fr: 'Français', en: 'English', nl: 'Nederlands', es: 'Español' };

const page = usePage<{ locale: string; availableLocales: string[] }>();

const current = computed(() => page.props.locale);
const available = computed(() => page.props.availableLocales);

function select(code: string) {
    router.post('/locale', { locale: code });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon">
                <Languages class="size-5" />
                <span class="sr-only">Changer de langue</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuItem
                v-for="code in available" :key="code"
                @click="select(code)"
                :class="current === code ? 'bg-accent text-accent-foreground' : ''"
            >
                {{ LABELS[code] ?? code }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
