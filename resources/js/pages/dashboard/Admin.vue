<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, Inbox, LoaderCircle, Ticket } from '@lucide/vue';
import StatCard from '@/components/StatCard.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

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
            <StatCard title="Total tiket" :icon="Ticket" />
            <StatCard title="Belum ditugaskan" :icon="Inbox" />
            <StatCard title="Sedang diproses" :icon="LoaderCircle" />
            <StatCard title="Selesai" :icon="CircleCheck" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Volume tiket</CardTitle>
                    <CardDescription>
                        Jumlah tiket berdasarkan periode.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        Grafik akan tampil setelah modul tiket dan laporan
                        selesai.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Performa tim IT</CardTitle>
                    <CardDescription>
                        Ringkasan penanganan tiket per teknisi.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        Belum ada data. Ringkasan akan tampil setelah ada tiket
                        yang ditangani.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
