<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Plus,
    Search,
    X,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import TicketPriorityBadge from '@/components/TicketPriorityBadge.vue';
import TicketStatusBadge from '@/components/TicketStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatDate,
    priorityOptions,
    statusOptions,
} from '@/lib/ticket';
import type { TicketPriority, TicketStatus } from '@/lib/ticket';
import { index, show } from '@/routes/tickets';

type Option = { id: number; name: string };

type TicketRow = {
    id: number;
    ticket_number: string;
    title: string;
    priority: TicketPriority;
    status: TicketStatus;
    category: string | null;
    requester: string | null;
    assignee: string | null;
    created_at: string | null;
};

type Paginated = {
    data: TicketRow[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    search: string;
    status: string;
    priority: string;
    category_id: string;
    assigned_to: string;
    scope: string;
    sort: string;
    dir: 'asc' | 'desc';
};

const props = defineProps<{
    heading: string;
    description: string;
    tickets: Paginated;
    filters: Filters;
    categories: Option[];
    technicians: Option[];
    canCreate: boolean;
    showRequester: boolean;
    showAssignee: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tickets',
                href: index(),
            },
        ],
    },
});

type FilterKey = 'status' | 'priority' | 'category_id' | 'assigned_to';

const state = reactive({
    search: props.filters.search,
    status: props.filters.status || 'all',
    priority: props.filters.priority || 'all',
    category_id: props.filters.category_id || 'all',
    assigned_to: props.filters.assigned_to || 'all',
});

const loading = ref(false);

const hasActiveFilters = computed(
    () =>
        state.search !== '' ||
        state.status !== 'all' ||
        state.priority !== 'all' ||
        state.category_id !== 'all' ||
        state.assigned_to !== 'all',
);

const columnCount = computed(
    () => 5 + (props.showRequester ? 1 : 0) + (props.showAssignee ? 1 : 0) + 1,
);

function visit(overrides: Record<string, string> = {}) {
    const merged: Record<string, string> = {
        ...state,
        scope: props.filters.scope,
        sort: props.filters.sort,
        dir: props.filters.dir,
        ...overrides,
    };

    // Halaman tidak ikut dikirim, jadi setiap perubahan kembali ke halaman 1.
    const query = Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== 'all'),
    );

    router.get(TicketController.index.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

watchDebounced(
    () => state.search,
    () => visit(),
    { debounce: 300 },
);

function setFilter(key: FilterKey, value: unknown) {
    state[key] = String(value);
    visit();
}

function resetFilters() {
    state.search = '';
    state.status = 'all';
    state.priority = 'all';
    state.category_id = 'all';
    state.assigned_to = 'all';
    visit({ search: '', status: '', priority: '', category_id: '', assigned_to: '' });
}

function toggleSort(column: string) {
    const firstDirection = ['priority', 'created_at'].includes(column)
        ? 'desc'
        : 'asc';

    const dir =
        props.filters.sort === column
            ? props.filters.dir === 'asc'
                ? 'desc'
                : 'asc'
            : firstDirection;

    visit({ sort: column, dir });
}

function sortIcon(column: string) {
    if (props.filters.sort !== column) {
        return ArrowUpDown;
    }

    return props.filters.dir === 'asc' ? ArrowUp : ArrowDown;
}

function ariaSort(column: string) {
    if (props.filters.sort !== column) {
        return 'none';
    }

    return props.filters.dir === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <Head :title="heading" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ heading }}</h1>
                <p class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <Button v-if="canCreate" as-child data-test="create-ticket-button">
                <Link :href="TicketController.create.url()">
                    <Plus class="size-4" aria-hidden="true" />
                    Buat tiket
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full sm:w-64">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="state.search"
                    type="search"
                    class="pl-8"
                    placeholder="Cari nomor atau judul"
                    aria-label="Cari tiket"
                />
            </div>

            <Select
                :model-value="state.status"
                @update:model-value="(v) => setFilter('status', v)"
            >
                <SelectTrigger class="w-full sm:w-40" aria-label="Filter status">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua status</SelectItem>
                    <SelectItem
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="state.priority"
                @update:model-value="(v) => setFilter('priority', v)"
            >
                <SelectTrigger class="w-full sm:w-40" aria-label="Filter prioritas">
                    <SelectValue placeholder="Prioritas" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua prioritas</SelectItem>
                    <SelectItem
                        v-for="option in priorityOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="state.category_id"
                @update:model-value="(v) => setFilter('category_id', v)"
            >
                <SelectTrigger class="w-full sm:w-44" aria-label="Filter kategori">
                    <SelectValue placeholder="Kategori" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua kategori</SelectItem>
                    <SelectItem
                        v-for="category in categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ category.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                v-if="showAssignee"
                :model-value="state.assigned_to"
                @update:model-value="(v) => setFilter('assigned_to', v)"
            >
                <SelectTrigger class="w-full sm:w-44" aria-label="Filter teknisi">
                    <SelectValue placeholder="Teknisi" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua teknisi</SelectItem>
                    <SelectItem value="unassigned">Belum ditugaskan</SelectItem>
                    <SelectItem
                        v-for="technician in technicians"
                        :key="technician.id"
                        :value="String(technician.id)"
                    >
                        {{ technician.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="resetFilters"
            >
                <X class="size-4" aria-hidden="true" />
                Reset
            </Button>
        </div>

        <Card>
            <CardContent
                class="px-2 transition-opacity sm:px-6"
                :class="{ 'opacity-60': loading }"
                :aria-busy="loading"
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead :aria-sort="ariaSort('ticket_number')">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="toggleSort('ticket_number')"
                                >
                                    Nomor
                                    <component
                                        :is="sortIcon('ticket_number')"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </TableHead>
                            <TableHead :aria-sort="ariaSort('title')">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="toggleSort('title')"
                                >
                                    Judul
                                    <component
                                        :is="sortIcon('title')"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </TableHead>
                            <TableHead>Kategori</TableHead>
                            <TableHead v-if="showRequester">Pelapor</TableHead>
                            <TableHead v-if="showAssignee">Teknisi</TableHead>
                            <TableHead :aria-sort="ariaSort('priority')">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="toggleSort('priority')"
                                >
                                    Prioritas
                                    <component
                                        :is="sortIcon('priority')"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </TableHead>
                            <TableHead :aria-sort="ariaSort('status')">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="toggleSort('status')"
                                >
                                    Status
                                    <component
                                        :is="sortIcon('status')"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </TableHead>
                            <TableHead :aria-sort="ariaSort('created_at')">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="toggleSort('created_at')"
                                >
                                    Dibuat
                                    <component
                                        :is="sortIcon('created_at')"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="tickets.data.length === 0">
                            <TableCell
                                :colspan="columnCount"
                                class="py-10 text-center text-muted-foreground"
                            >
                                {{
                                    hasActiveFilters
                                        ? 'Tidak ada tiket yang cocok dengan filter. Coba ubah atau reset filter.'
                                        : 'Belum ada tiket.'
                                }}
                            </TableCell>
                        </TableRow>

                        <TableRow
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                        >
                            <TableCell class="font-mono text-xs whitespace-nowrap">
                                {{ ticket.ticket_number }}
                            </TableCell>
                            <TableCell class="max-w-xs truncate font-medium">
                                <Link
                                    :href="show.url(ticket.id)"
                                    class="hover:underline focus-visible:underline"
                                >
                                    {{ ticket.title }}
                                </Link>
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                {{ ticket.category ?? '—' }}
                            </TableCell>
                            <TableCell v-if="showRequester" class="whitespace-nowrap">
                                {{ ticket.requester ?? '—' }}
                            </TableCell>
                            <TableCell v-if="showAssignee" class="whitespace-nowrap">
                                {{ ticket.assignee ?? 'Belum ditugaskan' }}
                            </TableCell>
                            <TableCell>
                                <TicketPriorityBadge :priority="ticket.priority" />
                            </TableCell>
                            <TableCell>
                                <TicketStatusBadge :status="ticket.status" />
                            </TableCell>
                            <TableCell class="whitespace-nowrap text-muted-foreground">
                                {{ formatDate(ticket.created_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div
            v-if="tickets.total > 0"
            class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
        >
            <p>
                Menampilkan {{ tickets.from }}–{{ tickets.to }} dari
                {{ tickets.total }} tiket
            </p>

            <div class="flex items-center gap-2">
                <span>
                    Halaman {{ tickets.current_page }} dari
                    {{ tickets.last_page }}
                </span>
                <Button
                    v-if="tickets.prev_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="tickets.prev_page_url"
                        preserve-scroll
                        preserve-state
                    >
                        Sebelumnya
                    </Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled>
                    Sebelumnya
                </Button>
                <Button
                    v-if="tickets.next_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="tickets.next_page_url"
                        preserve-scroll
                        preserve-state
                    >
                        Berikutnya
                    </Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled>
                    Berikutnya
                </Button>
            </div>
        </div>
    </div>
</template>
