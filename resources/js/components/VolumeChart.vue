<script setup lang="ts">
import { VisAxis, VisCrosshair, VisLine, VisTooltip, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';

export type VolumePoint = {
    date: string;
    created: number;
    resolved: number;
};

const props = defineProps<{ data: VolumePoint[] }>();

// Warna mengikuti token tema (--chart-1/2), jadi otomatis berganti di dark mode.
const colors = ['var(--chart-1)', 'var(--chart-2)'];
const names = ['Dibuat', 'Diselesaikan'];

const label = (index: number) =>
    new Date(`${props.data[index]?.date}T00:00:00`).toLocaleDateString(
        'id-ID',
        { day: 'numeric', month: 'short' },
    );

const x = (_: VolumePoint, index: number) => index;
const y = [(d: VolumePoint) => d.created, (d: VolumePoint) => d.resolved];
const color = (_: VolumePoint, index: number) => colors[index];

const tickFormat = (tick: number | Date) =>
    Number.isInteger(tick) ? label(tick as number) : '';

const yTickFormat = (tick: number | Date) =>
    Number.isInteger(tick) ? String(tick) : '';

const template = (d: VolumePoint, index: number | Date) =>
    `<div class="text-xs"><div class="font-medium">${label(Number(index))}</div>` +
    `<div>${names[0]}: ${d.created}</div><div>${names[1]}: ${d.resolved}</div></div>`;

const totals = computed(() => ({
    created: props.data.reduce((sum, d) => sum + d.created, 0),
    resolved: props.data.reduce((sum, d) => sum + d.resolved, 0),
}));
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <span class="flex items-center gap-2">
                <span class="size-3 rounded-sm" :style="{ background: colors[0] }" />
                {{ names[0] }}: <strong class="tabular-nums">{{ totals.created }}</strong>
            </span>
            <span class="flex items-center gap-2">
                <span class="size-3 rounded-sm" :style="{ background: colors[1] }" />
                {{ names[1] }}: <strong class="tabular-nums">{{ totals.resolved }}</strong>
            </span>
        </div>

        <div
            class="volume-chart"
            role="img"
            :aria-label="`Grafik tren tiket: ${totals.created} dibuat dan ${totals.resolved} diselesaikan dalam ${data.length} hari terakhir`"
        >
            <VisXYContainer :data="data" :height="260" :padding="{ top: 10, right: 10 }">
                <VisLine :x="x" :y="y" :color="colors" />
                <VisAxis type="x" :tick-format="tickFormat" :num-ticks="6" :grid-line="false" />
                <VisAxis type="y" :tick-format="yTickFormat" :num-ticks="5" />
                <VisCrosshair :template="template" :color="color" />
                <VisTooltip />
            </VisXYContainer>
        </div>
    </div>
</template>

<style scoped>
.volume-chart {
    --vis-axis-tick-label-color: var(--muted-foreground);
    --vis-axis-grid-color: var(--border);
    --vis-axis-domain-color: var(--border);
    --vis-crosshair-line-stroke-color: var(--muted-foreground);
    --vis-tooltip-background-color: var(--popover);
    --vis-tooltip-border-color: var(--border);
    --vis-tooltip-text-color: var(--popover-foreground);
}
</style>
