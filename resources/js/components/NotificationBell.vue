<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type NotificationItem = {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    created_at: string;
};

const page = usePage<{
    unreadNotifications: { count: number; items: NotificationItem[] };
}>();

const count = computed(() => page.props.unreadNotifications?.count ?? 0);
const items = computed(() => page.props.unreadNotifications?.items ?? []);

function openItem(item: NotificationItem) {
    router.patch(`/notifications/${item.id}`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (item.url) router.visit(item.url);
        },
    });
}

function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
                <Bell class="size-5" />
                <span
                    v-if="count > 0"
                    class="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-medium text-destructive-foreground"
                >{{ count > 9 ? '9+' : count }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <div class="flex items-center justify-between px-2 py-1.5">
                <DropdownMenuLabel class="p-0">Notifications</DropdownMenuLabel>
                <button
                    v-if="count > 0"
                    class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    @click="markAllRead"
                >Tout marquer comme lu</button>
            </div>
            <DropdownMenuSeparator />
            <div v-if="items.length === 0" class="px-2 py-6 text-center text-sm text-muted-foreground">
                Aucune notification.
            </div>
            <DropdownMenuItem
                v-for="item in items" :key="item.id"
                class="flex flex-col items-start gap-0.5 whitespace-normal py-2"
                :class="!item.read ? 'bg-accent/50' : ''"
                @click="openItem(item)"
            >
                <p class="text-sm font-medium leading-tight">{{ item.title }}</p>
                <p class="text-xs text-muted-foreground line-clamp-2">{{ item.body }}</p>
                <p class="text-[10px] text-muted-foreground">{{ item.created_at }}</p>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
