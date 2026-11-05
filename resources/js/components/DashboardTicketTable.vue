<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import TicketPriorityBadge from '@/components/TicketPriorityBadge.vue';
import TicketStatusBadge from '@/components/TicketStatusBadge.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/ticket';
import type { TicketPriority, TicketStatus } from '@/lib/ticket';
import { show } from '@/routes/tickets';

export type DashboardTicket = {
    id: number;
    ticket_number: string;
    title: string;
    priority: TicketPriority;
    status: TicketStatus;
    category: string | null;
    requester: string | null;
    created_at: string | null;
};

defineProps<{
    tickets: DashboardTicket[];
    emptyText: string;
    showRequester?: boolean;
}>();
</script>

<template>
    <p v-if="tickets.length === 0" class="text-sm text-muted-foreground">
        {{ emptyText }}
    </p>

    <Table v-else>
        <TableHeader>
            <TableRow>
                <TableHead>No. tiket</TableHead>
                <TableHead>Judul</TableHead>
                <TableHead v-if="showRequester">Pelapor</TableHead>
                <TableHead>Prioritas</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Dibuat</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="ticket in tickets" :key="ticket.id">
                <TableCell class="font-mono text-xs">
                    {{ ticket.ticket_number }}
                </TableCell>
                <TableCell class="max-w-64 truncate">
                    <Link
                        :href="show.url(ticket.id)"
                        class="font-medium hover:underline"
                    >
                        {{ ticket.title }}
                    </Link>
                </TableCell>
                <TableCell v-if="showRequester">
                    {{ ticket.requester ?? '—' }}
                </TableCell>
                <TableCell>
                    <TicketPriorityBadge :priority="ticket.priority" />
                </TableCell>
                <TableCell>
                    <TicketStatusBadge :status="ticket.status" />
                </TableCell>
                <TableCell class="text-muted-foreground">
                    {{ formatDate(ticket.created_at) }}
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
