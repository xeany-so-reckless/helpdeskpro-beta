<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, Inbox, LoaderCircle, Ticket } from '@lucide/vue';
import BreakdownCard from '@/components/BreakdownCard.vue';
import DashboardTicketTable from '@/components/DashboardTicketTable.vue';
import type { DashboardTicket } from '@/components/DashboardTicketTable.vue';
import StatCard from '@/components/StatCard.vue';
import TeamPerformanceTable from '@/components/TeamPerformanceTable.vue';
import type { TeamMember } from '@/components/TeamPerformanceTable.vue';
import VolumeChart from '@/components/VolumeChart.vue';
import type { VolumePoint } from '@/components/VolumeChart.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index } from '@/routes/tickets';

type Row = { key: string; label: string; count: number };

defineProps<{
    stats: {
        total: number;
        unassigned: number;
        in_progress: number;
        finished: number;
    };
    byStatus: Row[];
    byPriority: Row[];
    unassigned: DashboardTicket[];
    period: { days: number; options: number[] };
    volume: VolumePoint[];
    team: TeamMember[];
}>();

// Ganti periode tanpa memuat ulang seluruh halaman.
function setPeriod(days: number) {
    router.get(
        dashboard.url({ query: { days } }),
        {},
        {
            only: ['period', 'volume', 'team'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Dashboard</h1>
            <p class="text-sm text-muted-foreground">
                Ringkasan seluruh tiket dan aktivitas tim IT.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard title="Total tiket" :value="stats.total" :icon="Ticket" />
            <StatCard
                title="Belum ditugaskan"
                :value="stats.unassigned"
                :icon="Inbox"
            />
            <StatCard
                title="Sedang diproses"
                :value="stats.in_progress"
                :icon="LoaderCircle"
            />
            <StatCard
                title="Selesai"
                :value="stats.finished"
                description="Resolved dan Closed"
                :icon="CircleCheck"
            />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Volume tiket</CardTitle>
                <CardDescription>
                    Tiket yang dibuat dan diselesaikan per hari.
                </CardDescription>
                <CardAction>
                    <div class="flex gap-1" role="group" aria-label="Periode">
                        <Button
                            v-for="days in period.options"
                            :key="days"
                            size="sm"
                            :variant="days === period.days ? 'default' : 'outline'"
                            :aria-pressed="days === period.days"
                            @click="setPeriod(days)"
                        >
                            {{ days }} hari
                        </Button>
                    </div>
                </CardAction>
            </CardHeader>
            <CardContent>
                <VolumeChart :data="volume" />
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <BreakdownCard
                title="Tiket per status"
                description="Sebaran seluruh tiket berdasarkan status."
                :rows="byStatus"
            />
            <BreakdownCard
                title="Tiket per prioritas"
                description="Sebaran seluruh tiket berdasarkan prioritas."
                :rows="byPriority"
            />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Performa tim IT</CardTitle>
                <CardDescription>
                    Beban saat ini dan tiket yang diselesaikan dalam
                    {{ period.days }} hari terakhir.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <TeamPerformanceTable :team="team" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Tiket belum ditugaskan</CardTitle>
                <CardDescription>
                    Diurutkan dari prioritas tertinggi dan yang paling lama
                    menunggu.
                </CardDescription>
                <CardAction>
                    <Button variant="ghost" size="sm" as-child>
                        <Link
                            :href="
                                index.url({ query: { assigned_to: 'unassigned' } })
                            "
                        >
                            Lihat semua
                        </Link>
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                <DashboardTicketTable
                    :tickets="unassigned"
                    show-requester
                    empty-text="Semua tiket sudah ditugaskan."
                />
            </CardContent>
        </Card>
    </div>
</template>
