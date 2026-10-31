<script setup lang="ts">
import { formatDateTime } from '@/lib/datetime';

export type TimelineEntry = {
    id: number;
    actor: string;
    at: string | null;
    text: string;
};

defineProps<{
    entries: TimelineEntry[];
}>();
</script>

<template>
    <p v-if="entries.length === 0" class="text-sm text-muted-foreground">
        Belum ada aktivitas.
    </p>

    <ol v-else class="space-y-4 border-l border-border pl-5">
        <li v-for="entry in entries" :key="entry.id" class="relative">
            <span
                class="absolute top-1.5 -left-[25px] size-2.5 rounded-full border-2 border-background bg-primary"
                aria-hidden="true"
            />
            <p class="text-sm">{{ entry.text }}</p>
            <p class="text-xs text-muted-foreground">
                {{ entry.actor }} · {{ formatDateTime(entry.at) }}
            </p>
        </li>
    </ol>
</template>
