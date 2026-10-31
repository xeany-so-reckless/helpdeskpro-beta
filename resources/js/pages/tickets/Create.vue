<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import InputError from '@/components/InputError.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { priorityMeta } from '@/lib/ticket';
import type { TicketPriority } from '@/lib/ticket';
import { create } from '@/routes/tickets';

defineProps<{
    categories: { id: number; name: string }[];
    priorities: TicketPriority[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Create Ticket',
                href: create(),
            },
        ],
    },
});

const priorityHints: Record<string, string> = {
    low: 'Tidak mengganggu pekerjaan',
    medium: 'Pekerjaan agak terhambat',
    high: 'Pekerjaan terhenti',
};

const form = useForm({
    title: '',
    category_id: '',
    priority: 'medium',
    description: '',
});

function submit() {
    form.post(TicketController.store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Create Ticket" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Buat tiket</h1>
            <p class="text-sm text-muted-foreground">
                Ceritakan masalah IT yang Anda alami. Tim IT akan menanganinya.
            </p>
        </div>

        <Card class="max-w-2xl">
            <CardHeader>
                <CardTitle>Detail masalah</CardTitle>
                <CardDescription>
                    Semakin jelas deskripsinya, semakin cepat ditangani.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="ticket-title">Judul</Label>
                        <Input
                            id="ticket-title"
                            v-model="form.title"
                            required
                            maxlength="150"
                            placeholder="Contoh: Printer lantai 2 tidak bisa mencetak"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="ticket-category">Kategori</Label>
                            <Select v-model="form.category_id">
                                <SelectTrigger id="ticket-category" class="w-full">
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
                            <Label for="ticket-priority">Prioritas</Label>
                            <Select v-model="form.priority">
                                <SelectTrigger id="ticket-priority" class="w-full">
                                    <SelectValue placeholder="Pilih prioritas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="priority in priorities"
                                        :key="priority"
                                        :value="priority"
                                    >
                                        {{ priorityMeta[priority].label }}
                                        <span class="text-muted-foreground">
                                            · {{ priorityHints[priority] }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.priority" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ticket-description">Deskripsi</Label>
                        <Textarea
                            id="ticket-description"
                            v-model="form.description"
                            required
                            rows="6"
                            maxlength="5000"
                            placeholder="Apa yang terjadi, sejak kapan, dan apa yang sudah dicoba?"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-test="submit-ticket-button"
                        >
                            Kirim tiket
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link :href="TicketController.index.url()">Batal</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
