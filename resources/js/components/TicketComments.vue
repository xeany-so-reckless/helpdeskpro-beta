<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/datetime';
import { store } from '@/routes/tickets/comments';

export type TicketComment = {
    id: number;
    author: string | null;
    author_role: string | null;
    body: string;
    is_internal: boolean;
    created_at: string | null;
};

const props = defineProps<{
    ticketId: number;
    comments: TicketComment[];
    can: { comment: boolean; addInternalNote: boolean };
}>();

const roleLabels: Record<string, string> = {
    employee: 'Karyawan',
    it_support: 'IT Support',
    admin: 'Admin',
};

const form = useForm({ body: '', is_internal: false });

function submit() {
    form.post(store.url(props.ticketId), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Percakapan</CardTitle>
            <CardDescription>
                Komunikasi antara pelapor dan teknisi.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <p
                v-if="comments.length === 0"
                class="text-sm text-muted-foreground"
            >
                Belum ada komentar.
            </p>

            <ul v-else class="space-y-3">
                <li
                    v-for="comment in comments"
                    :key="comment.id"
                    :class="[
                        'rounded-lg border p-3',
                        comment.is_internal
                            ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
                            : 'bg-card',
                    ]"
                    data-test="comment-item"
                >
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-medium">
                            {{ comment.author ?? 'Pengguna terhapus' }}
                        </span>
                        <Badge
                            v-if="comment.author_role"
                            variant="secondary"
                        >
                            {{
                                roleLabels[comment.author_role] ??
                                comment.author_role
                            }}
                        </Badge>
                        <Badge v-if="comment.is_internal" variant="outline">
                            <Lock class="size-3" aria-hidden="true" />
                            Catatan internal
                        </Badge>
                        <span class="ml-auto text-xs text-muted-foreground">
                            {{ formatDateTime(comment.created_at) }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm break-words whitespace-pre-line">
                        {{ comment.body }}
                    </p>
                </li>
            </ul>

            <form
                v-if="can.comment"
                class="space-y-3"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="comment-body">Tulis komentar</Label>
                    <Textarea
                        id="comment-body"
                        v-model="form.body"
                        required
                        rows="3"
                        maxlength="5000"
                        :placeholder="
                            form.is_internal
                                ? 'Catatan ini hanya terlihat oleh IT Support dan Admin.'
                                : 'Tulis pesan untuk pelapor atau teknisi...'
                        "
                    />
                    <InputError :message="form.errors.body" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div
                        v-if="can.addInternalNote"
                        class="flex items-center gap-2"
                    >
                        <Switch
                            id="comment-internal"
                            v-model="form.is_internal"
                        />
                        <Label for="comment-internal" class="text-sm">
                            Catatan internal (tidak terlihat pelapor)
                        </Label>
                    </div>
                    <span v-else />

                    <Button
                        type="submit"
                        :disabled="form.processing || !form.body.trim()"
                        data-test="submit-comment-button"
                    >
                        Kirim
                    </Button>
                </div>
            </form>

            <p v-else class="text-sm text-muted-foreground">
                Komentar tidak dapat ditambahkan pada tiket ini.
            </p>
        </CardContent>
    </Card>
</template>
