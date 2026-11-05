<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { priorityOptions } from '@/lib/ticket';
import type { TicketPriority, TicketStatus } from '@/lib/ticket';
import {
    assign as assignRoute,
    claim as claimRoute,
    priority as priorityRoute,
    status as statusRoute,
} from '@/routes/tickets';

const props = defineProps<{
    ticket: {
        id: number;
        status: TicketStatus;
        priority: TicketPriority;
        assignee: string | null;
    };
    can: { assign: boolean; claim: boolean; changePriority: boolean };
    transitions: string[];
    technicians: { id: number; name: string }[];
}>();

// Props hanya mengatur tombol yang tampil; keamanannya dijaga policy di backend.
const page = usePage();
const shared = computed(() => page.props as unknown as Record<string, unknown>);

const pageError = computed(() => {
    const errors = (shared.value.errors ?? {}) as Record<string, string>;

    return (
        errors.action ??
        errors.status ??
        errors.priority ??
        errors.technician_id ??
        null
    );
});

const hasActions = computed(
    () =>
        props.can.assign ||
        props.can.claim ||
        props.can.changePriority ||
        props.transitions.length > 0,
);

const busy = ref(false);

function send(
    method: 'post' | 'patch',
    url: string,
    data: Record<string, string> = {},
) {
    router[method](url, data, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

const claim = () => send('post', claimRoute.url(props.ticket.id));

const changeStatus = (status: string) =>
    send('patch', statusRoute.url(props.ticket.id), { status });

const setPriority = (value: unknown) =>
    send('patch', priorityRoute.url(props.ticket.id), {
        priority: String(value),
    });

// Dialog penugasan.
const assignOpen = ref(false);
const assignForm = useForm({ technician_id: '' });

function openAssign() {
    assignForm.reset();
    assignForm.clearErrors();
    assignOpen.value = true;
}

function submitAssign() {
    assignForm.patch(assignRoute.url(props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => (assignOpen.value = false),
    });
}

// Dialog penyelesaian (catatan solusi wajib).
const resolveOpen = ref(false);
const resolveForm = useForm({ status: 'resolved', resolution_note: '' });

function openResolve() {
    resolveForm.reset();
    resolveForm.clearErrors();
    resolveOpen.value = true;
}

function submitResolve() {
    resolveForm.patch(statusRoute.url(props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => (resolveOpen.value = false),
    });
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Aksi</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <Alert v-if="pageError" variant="destructive">
                <AlertDescription>{{ pageError }}</AlertDescription>
            </Alert>

            <div v-if="can.changePriority" class="grid gap-2">
                <Label for="ticket-priority-select">Prioritas</Label>
                <Select
                    :model-value="ticket.priority"
                    :disabled="busy"
                    @update:model-value="setPriority"
                >
                    <SelectTrigger id="ticket-priority-select" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in priorityOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex flex-col gap-2">
                <Button
                    v-if="can.claim"
                    :disabled="busy"
                    data-test="claim-ticket-button"
                    @click="claim"
                >
                    Ambil tiket ini
                </Button>

                <Button
                    v-if="can.assign"
                    variant="outline"
                    data-test="assign-ticket-button"
                    @click="openAssign"
                >
                    {{ ticket.assignee ? 'Tugaskan ulang' : 'Tugaskan teknisi' }}
                </Button>

                <Button
                    v-if="transitions.includes('in_progress')"
                    :disabled="busy"
                    data-test="start-ticket-button"
                    @click="changeStatus('in_progress')"
                >
                    Mulai kerjakan
                </Button>

                <Button
                    v-if="transitions.includes('resolved')"
                    data-test="resolve-ticket-button"
                    @click="openResolve"
                >
                    Selesaikan tiket
                </Button>

                <AlertDialog v-if="transitions.includes('closed')">
                    <AlertDialogTrigger as-child>
                        <Button :disabled="busy" data-test="close-ticket-button">
                            Konfirmasi selesai
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Tutup tiket ini?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Dengan menutup tiket, Anda menyatakan masalah
                                sudah teratasi. Tiket yang sudah ditutup tidak
                                bisa dibuka kembali.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Batal</AlertDialogCancel>
                            <AlertDialogAction @click="changeStatus('closed')">
                                Ya, tutup tiket
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <AlertDialog v-if="transitions.includes('reopened')">
                    <AlertDialogTrigger as-child>
                        <Button
                            variant="outline"
                            :disabled="busy"
                            data-test="reopen-ticket-button"
                        >
                            Buka kembali
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Buka kembali tiket ini?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Pilih ini jika masalah belum teratasi. Tiket
                                akan kembali ke teknisi untuk ditangani lagi.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Batal</AlertDialogCancel>
                            <AlertDialogAction
                                @click="changeStatus('reopened')"
                            >
                                Ya, buka kembali
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>

            <p v-if="!hasActions" class="text-sm text-muted-foreground">
                Tidak ada aksi yang tersedia untuk Anda pada status ini.
            </p>
        </CardContent>
    </Card>

    <Dialog v-model:open="assignOpen">
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submitAssign">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Tugaskan teknisi</DialogTitle>
                    <DialogDescription>
                        Pilih teknisi IT Support yang akan menangani tiket ini.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="assign-technician">Teknisi</Label>
                    <Select v-model="assignForm.technician_id">
                        <SelectTrigger id="assign-technician" class="w-full">
                            <SelectValue placeholder="Pilih teknisi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="technician in technicians"
                                :key="technician.id"
                                :value="String(technician.id)"
                            >
                                {{ technician.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="
                            assignForm.errors.technician_id ??
                            assignForm.errors.assigned_to ??
                            assignForm.errors.action
                        "
                    />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="assignForm.processing || !assignForm.technician_id"
                        data-test="confirm-assign-button"
                    >
                        Tugaskan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="resolveOpen">
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submitResolve">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Selesaikan tiket</DialogTitle>
                    <DialogDescription>
                        Tuliskan apa yang sudah dilakukan. Catatan ini dibaca
                        oleh pelapor sebelum ia mengonfirmasi.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="resolution-note">Catatan solusi</Label>
                    <Textarea
                        id="resolution-note"
                        v-model="resolveForm.resolution_note"
                        required
                        rows="5"
                        maxlength="2000"
                        placeholder="Contoh: Driver printer dipasang ulang dan tes cetak berhasil."
                    />
                    <InputError
                        :message="
                            resolveForm.errors.resolution_note ??
                            resolveForm.errors.action
                        "
                    />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="resolveForm.processing"
                        data-test="confirm-resolve-button"
                    >
                        Selesaikan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
