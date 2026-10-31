<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { priorityMeta } from '@/lib/ticket';
import type { TicketPriority } from '@/lib/ticket';

const props = defineProps<{
    priority: TicketPriority;
}>();

const meta = computed(() => priorityMeta[props.priority]);
</script>

<template>
    <Badge variant="outline" :class="['border-transparent', meta.class]">
        {{ meta.label }}
    </Badge>
</template>
