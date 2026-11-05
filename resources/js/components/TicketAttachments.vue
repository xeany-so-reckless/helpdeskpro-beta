<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Download, FileText, Image as ImageIcon, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/datetime';
import { destroy, download, store } from '@/routes/tickets/attachments';

export type TicketAttachment = {
    id: number;
    name: string;
    mime_type: string;
    size: number;
    uploader: string | null;
    created_at: string | null;
    can_delete: boolean;
};

const props = defineProps<{
    ticketId: number;
    attachments: TicketAttachment[];
    canAttach: boolean;
}>();

const form = useForm<{ files: File[] }>({ files: [] });

function pick(event: Event) {
    form.files = Array.from((event.target as HTMLInputElement).files ?? []);
}

function submit() {
    form.post(store.url(props.ticketId), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            const el = document.getElementById('attachment-files') as HTMLInputElement | null;
            if (el) {
                el.value = '';
            }
        },
    });
}

function remove(attachment: TicketAttachment) {
    router.delete(
        destroy.url({ ticket: props.ticketId, attachment: attachment.id }),
        { preserveScroll: true },
    );
}

const fileErrors = () =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'files' || key.startsWith('files.'))
        .map(([, message]) => message);

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Lampiran</CardTitle>
            <CardDescription>
                Screenshot atau dokumen pendukung (PNG, JPG, PDF, maksimal 5 MB).
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <p
                v-if="attachments.length === 0"
                class="text-sm text-muted-foreground"
            >
                Belum ada lampiran.
            </p>

            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="attachment in attachments"
                    :key="attachment.id"
                    class="flex items-center gap-3 p-3"
                    data-test="attachment-item"
                >
                    <component
                        :is="attachment.mime_type.startsWith('image/') ? ImageIcon : FileText"
                        class="size-5 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ attachment.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatSize(attachment.size) }} ·
                            {{ attachment.uploader ?? 'Pengguna terhapus' }} ·
                            {{ formatDateTime(attachment.created_at) }}
                        </p>
                    </div>

                    <Button variant="ghost" size="icon" as-child>
                        <a
                            :href="download.url({ ticket: ticketId, attachment: attachment.id })"
                            :aria-label="`Unduh ${attachment.name}`"
                        >
                            <Download class="size-4" aria-hidden="true" />
                        </a>
                    </Button>

                    <AlertDialog v-if="attachment.can_delete">
                        <AlertDialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Hapus ${attachment.name}`"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Hapus lampiran?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    {{ attachment.name }} akan dihapus permanen.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction @click="remove(attachment)">
                                    Ya, hapus
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </li>
            </ul>

            <form v-if="canAttach" class="space-y-2" @submit.prevent="submit">
                <Label for="attachment-files">Tambah lampiran</Label>
                <div class="flex flex-wrap items-center gap-2">
                    <Input
                        id="attachment-files"
                        type="file"
                        multiple
                        accept=".png,.jpg,.jpeg,.pdf"
                        class="max-w-sm"
                        @change="pick"
                    />
                    <Button
                        type="submit"
                        :disabled="form.processing || form.files.length === 0"
                        data-test="upload-attachment-button"
                    >
                        Unggah
                    </Button>
                </div>
                <InputError
                    v-for="message in fileErrors()"
                    :key="message"
                    :message="message"
                />
            </form>
        </CardContent>
    </Card>
</template>
