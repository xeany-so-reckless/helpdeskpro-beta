<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CircleCheck,
    Flame,
    Inbox,
    LoaderCircle,
    Ticket,
} from '@lucide/vue';
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
                Ringkasan tiket yang perlu Anda tangani.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <StatCard title="Ditugaskan ke saya" :icon="Ticket" />
            <StatCard title="Belum ditangani" :icon="Inbox" />
            <StatCard title="Sedang diproses" :icon="LoaderCircle" />
            <StatCard title="Prioritas tinggi" :icon="Flame" />
            <StatCard title="Selesai" :icon="CircleCheck" />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Antrean tiket</CardTitle>
                <CardDescription>
                    Tiket yang menunggu penanganan, diurutkan berdasarkan
                    prioritas.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    Belum ada data. Antrean akan tampil setelah modul tiket
                    selesai.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
