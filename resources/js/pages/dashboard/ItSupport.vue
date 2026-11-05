<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheck,
    Flame,
    Inbox,
    LoaderCircle,
    Ticket,
} from '@lucide/vue';
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
        assigned: number;
        queue: number;
        in_progress: number;
        high_priority: number;
        finished: number;
    };
    queue: DashboardTicket[];
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
                Ringkasan tiket yang perlu Anda tangani.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <StatCard
                title="Ditugaskan ke saya"
                :value="stats.assigned"
                description="Yang masih aktif"
                :icon="Ticket"
            />
            <StatCard
                title="Belum ditangani"
                :value="stats.queue"
                description="Antrean tanpa teknisi"
                :icon="Inbox"
            />
            <StatCard
                title="Sedang diproses"
                :value="stats.in_progress"
                :icon="LoaderCircle"
            />
            <StatCard
                title="Prioritas tinggi"
                :value="stats.high_priority"
                description="High dan Critical milik saya"
                :icon="Flame"
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
                <CardTitle>Antrean tiket</CardTitle>
                <CardDescription>
                    Tiket yang menunggu penanganan, diurutkan berdasarkan
                    prioritas.
                </CardDescription>
                <CardAction>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="index.url({ query: { scope: 'queue' } })">
                            Lihat antrean
                        </Link>
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                <DashboardTicketTable
                    :tickets="queue"
                    show-requester
                    empty-text="Antrean kosong. Tidak ada tiket yang menunggu."
                />
            </CardContent>
        </Card>
    </div>
</template>
