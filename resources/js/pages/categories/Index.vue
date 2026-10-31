<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/categories';

type Category = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    tickets_count: number;
};

defineProps<{
    categories: Category[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Categories',
                href: index(),
            },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Category | null>(null);

const formAction = computed(() =>
    editing.value
        ? CategoryController.update.form(editing.value.id)
        : CategoryController.store.form(),
);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(category: Category) {
    editing.value = category;
    dialogOpen.value = true;
}

function setActive(category: Category, value: boolean) {
    router.patch(
        CategoryController.updateStatus.url(category.id),
        { is_active: value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Categories" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Categories</h1>
                <p class="text-sm text-muted-foreground">
                    Kelola kategori masalah. Kategori nonaktif tidak muncul di
                    form tiket baru.
                </p>
            </div>
            <Button data-test="new-category-button" @click="openCreate">
                <Plus class="size-4" aria-hidden="true" />
                Tambah kategori
            </Button>
        </div>

        <Card>
            <CardContent class="px-2 sm:px-6">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Deskripsi</TableHead>
                            <TableHead class="text-right">Tiket</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="categories.length === 0">
                            <TableCell
                                colspan="5"
                                class="py-8 text-center text-muted-foreground"
                            >
                                Belum ada kategori. Klik "Tambah kategori" untuk
                                membuat yang pertama.
                            </TableCell>
                        </TableRow>

                        <TableRow
                            v-for="category in categories"
                            :key="category.id"
                        >
                            <TableCell class="font-medium">
                                {{ category.name }}
                            </TableCell>
                            <TableCell
                                class="max-w-xs truncate text-muted-foreground"
                            >
                                {{ category.description ?? '—' }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ category.tickets_count }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Switch
                                        :model-value="category.is_active"
                                        :aria-label="`Aktifkan kategori ${category.name}`"
                                        @update:model-value="
                                            (value: boolean) =>
                                                setActive(category, value)
                                        "
                                    />
                                    <Badge
                                        :variant="
                                            category.is_active
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            category.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Edit kategori ${category.name}`"
                                    @click="openEdit(category)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                    Edit
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <Form
                :key="editing?.id ?? 'new'"
                v-bind="formAction"
                reset-on-success
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="dialogOpen = false"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{ editing ? 'Edit kategori' : 'Tambah kategori' }}
                    </DialogTitle>
                    <DialogDescription>
                        Nama kategori harus unik dan akan tampil di form tiket.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="category-name">Nama</Label>
                    <Input
                        id="category-name"
                        name="name"
                        :default-value="editing?.name ?? ''"
                        required
                        maxlength="100"
                        placeholder="Contoh: Network"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-description">Deskripsi</Label>
                    <Textarea
                        id="category-description"
                        name="description"
                        :default-value="editing?.description ?? ''"
                        maxlength="500"
                        placeholder="Opsional"
                    />
                    <InputError :message="errors.description" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="save-category-button"
                    >
                        Simpan
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
