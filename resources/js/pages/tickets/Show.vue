<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TicketPriorityBadge from '@/components/TicketPriorityBadge.vue';
import TicketStatusBadge from '@/components/TicketStatusBadge.vue';
import TicketTimeline from '@/components/TicketTimeline.vue';
import type { TimelineEntry } from '@/components/TicketTimeline.vue';
import { Button } from '@/components/ui/button';
import TicketActions from '@/components/TicketActions.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/datetime';
import type { TicketPriority, TicketStatus } from '@/lib/ticket';
import { index, update } from '@/routes/tickets';
import TicketComments from '@/components/TicketComments.vue';
import type { TicketComment } from '@/components/TicketComments.vue';
import TicketAttachments from '@/components/TicketAttachments.vue';
import type { TicketAttachment } from '@/components/TicketAttachments.vue';

type Ticket = {
    id: number;
    ticket_number: string;
    title: string;
    description: string;
    category_id: number;
    category: string | null;
    priority: TicketPriority;
    status: TicketStatus;
    requester: string | null;
    assignee: string | null;
    resolution_note: string | null;
    created_at: string | null;
    updated_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
};

const props = defineProps<{
    attachments: TicketAttachment[];
    comments: TicketComment[];
    ticket: Ticket;
    timeline: TimelineEntry[];
    categories: { id: number; name: string }[];
    can: {
    update: boolean;
    assign: boolean;
    claim: boolean;
    changePriority: boolean;
    comment: boolean;
    addInternalNote: boolean;
    attach: boolean;
    };
    transitions: string[];
    technicians: { id: number; name: string }[];
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

const editOpen = ref(false);

const form = useForm({
    title: '',
    category_id: '',
    description: '',
});

function openEdit() {
    form.title = props.ticket.title;
    form.category_id = String(props.ticket.category_id);
    form.description = props.ticket.description;
    form.clearErrors();
    editOpen.value = true;
}

function submitEdit() {
    form.patch(update.url(props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}
</script>

<template>
    <Head :title="`${ticket.ticket_number} · ${ticket.title}`" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div>
            <Button variant="ghost" size="sm" as-child class="-ml-2">
                <Link :href="index.url()">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Kembali ke daftar
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-2">
                <p class="font-mono text-sm text-muted-foreground">
                    {{ ticket.ticket_number }}
                </p>
                <h1 class="text-xl font-semibold break-words">
                    {{ ticket.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <TicketStatusBadge :status="ticket.status" />
                    <TicketPriorityBadge :priority="ticket.priority" />
                </div>
            </div>

            <Button
                v-if="can.update"
                variant="outline"
                data-test="edit-ticket-button"
                @click="openEdit"
            >
                <Pencil class="size-4" aria-hidden="true" />
                Edit tiket
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Deskripsi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm break-words whitespace-pre-line">
                            {{ ticket.description }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="ticket.resolution_note">
                    <CardHeader>
                        <CardTitle>Catatan solusi</CardTitle>
                        <CardDescription>
                            Ditulis teknisi saat menyelesaikan tiket.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm break-words whitespace-pre-line">
                            {{ ticket.resolution_note }}
                        </p>
                    </CardContent>
                </Card>
                <TicketComments
                    :ticket-id="ticket.id"
                    :comments="comments"
                    :can="can"
                />

                <TicketAttachments
                    :ticket-id="ticket.id"
                    :attachments="attachments"
                    :can-attach="can.attach"
                />


                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat</CardTitle>
                        <CardDescription>
                            Perubahan status, penugasan, dan prioritas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TicketTimeline :entries="timeline" />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-4">
                <TicketActions
                    :ticket="ticket"
                    :can="can"
                    :transitions="transitions"
                    :technicians="technicians"
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Detail</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Kategori</dt>
                                <dd>{{ ticket.category ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Pelapor</dt>
                                <dd>{{ ticket.requester ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Teknisi</dt>
                                <dd>{{ ticket.assignee ?? 'Belum ditugaskan' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Dibuat</dt>
                                <dd>{{ formatDateTime(ticket.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Diperbarui</dt>
                                <dd>{{ formatDateTime(ticket.updated_at) }}</dd>
                            </div>
                            <div v-if="ticket.resolved_at">
                                <dt class="text-muted-foreground">Diselesaikan</dt>
                                <dd>{{ formatDateTime(ticket.resolved_at) }}</dd>
                            </div>
                            <div v-if="ticket.closed_at">
                                <dt class="text-muted-foreground">Ditutup</dt>
                                <dd>{{ formatDateTime(ticket.closed_at) }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <Dialog v-model:open="editOpen">
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submitEdit">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Edit tiket</DialogTitle>
                    <DialogDescription>
                        Ubah judul, kategori, atau deskripsi tiket.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="edit-title">Judul</Label>
                    <Input
                        id="edit-title"
                        v-model="form.title"
                        required
                        maxlength="150"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-category">Kategori</Label>
                    <Select v-model="form.category_id">
                        <SelectTrigger id="edit-category" class="w-full">
                            <SelectValue placeholder="Pilih kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="String(category.id)"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-description">Deskripsi</Label>
                    <Textarea
                        id="edit-description"
                        v-model="form.description"
                        required
                        rows="6"
                        maxlength="5000"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-ticket-button"
                    >
                        Simpan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
