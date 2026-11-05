<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, CircleDot, LoaderCircle, Ticket } from '@lucide/vue';
import DashboardTicketTable from '@/components/DashboardTicketTable.vue';
import type { DashboardTicket } from '@/components/DashboardTicketTable.vue';
import StatCard from '@/components/StatCard.vue';
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

defineProps<{
    stats: {
        total: number;
        open: number;
        in_progress: number;
        finished: number;
    };
    recent: DashboardTicket[];
}>();

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
                Ringkasan tiket yang Anda laporkan.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                title="Total tiket saya"
                :value="stats.total"
                :icon="Ticket"
            />
            <StatCard
                title="Tiket terbuka"
                :value="stats.open"
                description="Menunggu atau baru ditugaskan"
                :icon="CircleDot"
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
                <CardTitle>Tiket terbaru</CardTitle>
                <CardDescription>
                    Lima tiket terakhir yang Anda buat.
                </CardDescription>
                <CardAction>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="index.url()">Lihat semua</Link>
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                <DashboardTicketTable
                    :tickets="recent"
                    empty-text="Anda belum membuat tiket."
                />
            </CardContent>
        </Card>
    </div>
</template>
