<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleCheck, Clock, Ticket } from '@lucide/vue';
import { reactive } from 'vue';
import BreakdownCard from '@/components/BreakdownCard.vue';
import StatCard from '@/components/StatCard.vue';
import TeamPerformanceTable from '@/components/TeamPerformanceTable.vue';
import type { TeamMember } from '@/components/TeamPerformanceTable.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VolumeChart from '@/components/VolumeChart.vue';
import type { VolumePoint } from '@/components/VolumeChart.vue';
import { index } from '@/routes/reports';

type Row = { key: string; label: string; count: number };

const props = defineProps<{
    filters: { from: string; to: string };
    summary: { created: number; resolved: number; avg_hours: number | null };
    byStatus: Row[];
    byPriority: Row[];
    byCategory: Row[];
    volume: VolumePoint[];
    team: TeamMember[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Reports',
                href: index(),
            },
        ],
    },
});

const form = reactive({ from: props.filters.from, to: props.filters.to });

// Tanggal lokal (bukan toISOString) agar tidak bergeser sehari karena zona waktu.
const iso = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

function apply(from: string, to: string) {
    form.from = from;
    form.to = to;

    router.get(
        index.url({ query: { from, to } }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function lastDays(days: number) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    apply(iso(from), iso(to));
}

function thisMonth() {
    const now = new Date();
    apply(iso(new Date(now.getFullYear(), now.getMonth(), 1)), iso(now));
}

function duration(hours: number | null): string {
    if (hours === null) {
        return '—';
    }

    if (hours < 1) {
        return `${Math.round(hours * 60)} menit`;
    }

    if (hours < 48) {
        return `${hours.toFixed(1)} jam`;
    }

    return `${(hours / 24).toFixed(1)} hari`;
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Laporan</h1>
            <p class="text-sm text-muted-foreground">
                Statistik tiket yang dibuat pada periode terpilih.
            </p>
        </div>

        <Card>
            <CardContent class="flex flex-wrap items-end gap-4">
                <form
                    class="flex flex-wrap items-end gap-3"
                    @submit.prevent="apply(form.from, form.to)"
                >
                    <div class="grid gap-2">
                        <Label for="report-from">Dari</Label>
                        <Input
                            id="report-from"
                            v-model="form.from"
                            type="date"
                            :max="form.to"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="report-to">Sampai</Label>
                        <Input
                            id="report-to"
                            v-model="form.to"
                            type="date"
                            :min="form.from"
                            required
                        />
                    </div>
                    <Button type="submit" data-test="apply-report-filter">
                        Terapkan
                    </Button>
                </form>

                <div class="flex flex-wrap gap-2" role="group" aria-label="Periode cepat">
                    <Button variant="outline" size="sm" @click="lastDays(7)">
                        7 hari
                    </Button>
                    <Button variant="outline" size="sm" @click="lastDays(30)">
                        30 hari
                    </Button>
                    <Button variant="outline" size="sm" @click="lastDays(90)">
                        90 hari
                    </Button>
                    <Button variant="outline" size="sm" @click="thisMonth">
                        Bulan ini
                    </Button>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard
                title="Tiket dibuat"
                :value="summary.created"
                :icon="Ticket"
            />
            <StatCard
                title="Tiket diselesaikan"
                :value="summary.resolved"
                description="Diselesaikan dalam periode"
                :icon="CircleCheck"
            />
            <StatCard
                title="Rata-rata waktu penyelesaian"
                :value="duration(summary.avg_hours)"
                description="Dari dibuat sampai diselesaikan"
                :icon="Clock"
            />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Tren tiket</CardTitle>
                <CardDescription>
                    Tiket yang dibuat dan diselesaikan per hari.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <VolumeChart :data="volume" />
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-3">
            <BreakdownCard
                title="Per status"
                description="Status saat ini dari tiket yang dibuat."
                :rows="byStatus"
            />
            <BreakdownCard
                title="Per prioritas"
                description="Prioritas tiket yang dibuat."
                :rows="byPriority"
            />
            <BreakdownCard
                title="Per kategori"
                description="Kategori tiket yang dibuat."
                :rows="byCategory"
            />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Penanganan per teknisi</CardTitle>
                <CardDescription>
                    Beban saat ini dan tiket yang diselesaikan dalam periode.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <TeamPerformanceTable :team="team" />
            </CardContent>
        </Card>
    </div>
</template>
