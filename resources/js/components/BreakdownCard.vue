<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    title: string;
    description?: string;
    rows: { key: string; label: string; count: number }[];
}>();

const total = computed(() =>
    props.rows.reduce((sum, row) => sum + row.count, 0),
);

const percent = (count: number) =>
    total.value === 0 ? 0 : Math.round((count / total.value) * 100);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">
                {{ description }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <ul class="space-y-3">
                <li v-for="row in rows" :key="row.key" class="space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span>{{ row.label }}</span>
                        <span class="tabular-nums text-muted-foreground">
                            {{ row.count }} ({{ percent(row.count) }}%)
                        </span>
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-muted"
                        role="presentation"
                    >
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${percent(row.count)}%` }"
                        />
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
