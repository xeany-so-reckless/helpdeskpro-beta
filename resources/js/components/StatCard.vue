<script setup lang="ts">
import type { Component } from 'vue';
import {
    Card,
    CardAction,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    title: string;
    value?: string | number;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardDescription>{{ title }}</CardDescription>
            <CardTitle class="text-2xl font-semibold tabular-nums">
                {{ value ?? '—' }}
            </CardTitle>
            <CardAction v-if="icon">
                <component
                    :is="icon"
                    class="size-5 text-muted-foreground"
                    aria-hidden="true"
                />
            </CardAction>
        </CardHeader>
        <CardFooter v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </CardFooter>
    </Card>
</template>
