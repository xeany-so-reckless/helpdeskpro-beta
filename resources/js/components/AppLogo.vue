<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Headset } from '@lucide/vue';

// Nama aplikasi berasal dari APP_NAME di file .env.
const name = usePage().props.name || 'HelpdeskPro';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <Headset class="size-5" aria-hidden="true" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
