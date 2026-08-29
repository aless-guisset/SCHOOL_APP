<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', Icon: Sun, label: 'Clair' },
    { value: 'dark', Icon: Moon, label: 'Sombre' },
    { value: 'system', Icon: Monitor, label: 'Système' },
] as const;

const CurrentIcon = computed(() => options.find((o) => o.value === appearance.value)?.Icon ?? Monitor);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon">
                <component :is="CurrentIcon" class="size-5" />
                <span class="sr-only">Changer le thème</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuItem
                v-for="{ value, Icon, label } in options" :key="value"
                @click="updateAppearance(value)"
                :class="appearance === value ? 'bg-accent text-accent-foreground' : ''"
            >
                <Icon class="mr-2 size-4" />
                {{ label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
