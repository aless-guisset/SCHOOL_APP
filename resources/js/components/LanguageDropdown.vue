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

const LANGS: Record<string, { flag: string; name: string }> = {
    fr: { flag: '🇫🇷', name: 'Français' },
    en: { flag: '🇬🇧', name: 'English' },
    nl: { flag: '🇳🇱', name: 'Nederlands' },
    es: { flag: '🇪🇸', name: 'Español' },
};

const page = usePage<{ locale: string; availableLocales: string[] }>();

const current = computed(() => page.props.locale);
const available = computed(() => page.props.availableLocales);
const currentFlag = computed(() => LANGS[current.value]?.flag);

function select(code: string) {
    router.post('/locale', { locale: code });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon">
                <span v-if="currentFlag" class="text-base leading-none">{{ currentFlag }}</span>
                <Languages v-else class="size-5" />
                <span class="sr-only">Changer de langue</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuItem
                v-for="code in available" :key="code"
                @click="select(code)"
                :class="current === code ? 'bg-accent text-accent-foreground' : ''"
            >
                <span class="mr-2">{{ LANGS[code]?.flag ?? '' }}</span>
                {{ LANGS[code]?.name ?? code }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
