<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type TeamMember = {
    id: number;
    name: string;
    active: number;
    resolved: number;
    avg_hours: number | null;
};

defineProps<{ team: TeamMember[] }>();

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
    <p v-if="team.length === 0" class="text-sm text-muted-foreground">
        Belum ada teknisi IT Support yang aktif.
    </p>

    <Table v-else>
        <TableHeader>
            <TableRow>
                <TableHead>Teknisi</TableHead>
                <TableHead class="text-right">Sedang ditangani</TableHead>
                <TableHead class="text-right">Diselesaikan</TableHead>
                <TableHead class="text-right">Rata-rata waktu</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="member in team" :key="member.id">
                <TableCell class="font-medium">{{ member.name }}</TableCell>
                <TableCell class="text-right tabular-nums">
                    {{ member.active }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                    {{ member.resolved }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                    {{ duration(member.avg_hours) }}
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
