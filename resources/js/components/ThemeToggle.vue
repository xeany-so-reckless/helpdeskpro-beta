<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';

const { updateAppearance } = useAppearance();

// Dibaca dari class <html> agar selalu sama dengan tema yang sedang terlihat,
// baik pilihan manual maupun mengikuti sistem operasi.
function toggle() {
    const isDark = document.documentElement.classList.contains('dark');

    updateAppearance(isDark ? 'light' : 'dark');
}
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        aria-label="Ganti tema terang atau gelap"
        title="Ganti tema"
        @click="toggle"
    >
        <Sun class="hidden size-5 dark:block" aria-hidden="true" />
        <Moon class="size-5 dark:hidden" aria-hidden="true" />
    </Button>
</template>
