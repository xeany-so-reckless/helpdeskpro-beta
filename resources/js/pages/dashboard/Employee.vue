<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, CircleDot, LoaderCircle, Ticket } from '@lucide/vue';
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
                Ringkasan tiket yang Anda laporkan.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard title="Total tiket saya" :icon="Ticket" />
            <StatCard title="Tiket terbuka" :icon="CircleDot" />
            <StatCard title="Sedang diproses" :icon="LoaderCircle" />
            <StatCard title="Selesai" :icon="CircleCheck" />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Tiket terbaru</CardTitle>
                <CardDescription>
                    Lima tiket terakhir yang Anda buat.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    Belum ada data. Daftar tiket akan tampil setelah modul tiket
                    selesai.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
