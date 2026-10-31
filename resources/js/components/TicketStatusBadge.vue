<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { statusMeta } from '@/lib/ticket';
import type { TicketStatus } from '@/lib/ticket';

const props = defineProps<{
    status: TicketStatus;
}>();

const meta = computed(() => statusMeta[props.status]);
</script>

<template>
    <Badge variant="outline" :class="['border-transparent', meta.class]">
        {{ meta.label }}
    </Badge>
</template>
