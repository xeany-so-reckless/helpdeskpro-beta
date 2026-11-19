<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
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
} from '@/components/ui/alert-dialog';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/ticket';
import { index, status, store, update } from '@/routes/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    is_self: boolean;
    active_tickets: number;
    created_at: string | null;
};

type Paginated = {
    data: UserRow[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    users: Paginated;
    filters: { q: string; role: string; status: string };
    roles: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: index(),
            },
        ],
    },
});

const roleLabel = (value: string) =>
    props.roles.find((role) => role.value === value)?.label ?? value;

// ---------- Pencarian dan filter ----------

const state = reactive({
    q: props.filters.q,
    role: props.filters.role || 'all',
    status: props.filters.status || 'all',
});

const loading = ref(false);

const hasActiveFilters = computed(
    () => state.q !== '' || state.role !== 'all' || state.status !== 'all',
);

function visit(overrides: Record<string, string> = {}) {
    const merged: Record<string, string> = { ...state, ...overrides };

    // Halaman tidak ikut dikirim, jadi setiap perubahan kembali ke halaman 1.
    const query = Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== 'all'),
    );

    router.get(index.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

watchDebounced(
    () => state.q,
    () => visit(),
    { debounce: 300 },
);

function setFilter(key: 'role' | 'status', value: unknown) {
    state[key] = String(value);
    visit();
}

function resetFilters() {
    state.q = '';
    state.role = 'all';
    state.status = 'all';
    visit({ q: '', role: '', status: '' });
}

// ---------- Tambah dan edit ----------

const dialogOpen = ref(false);
const editing = ref<UserRow | null>(null);

const form = useForm({
    name: '',
    email: '',
    role: 'employee',
    password: '',
    password_confirmation: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(user: UserRow) {
    editing.value = user;
    form.reset();
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.patch(update.url(editing.value.id), options);
    } else {
        form.post(store.url(), options);
    }
}

// ---------- Aktif / nonaktif ----------

const deactivating = ref<UserRow | null>(null);

function setActive(user: UserRow, value: boolean) {
    router.patch(
        status.url(user.id),
        { is_active: value },
        { preserveScroll: true },
    );
}

function onToggle(user: UserRow, value: boolean) {
    // Menonaktifkan perlu konfirmasi; mengaktifkan langsung.
    if (value) {
        setActive(user, true);
    } else {
        deactivating.value = user;
    }
}

function confirmDeactivate() {
    if (deactivating.value) {
        setActive(deactivating.value, false);
    }

    deactivating.value = null;
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Users</h1>
                <p class="text-sm text-muted-foreground">
                    Kelola akun pengguna dan role. Akun tidak dihapus, hanya
                    dinonaktifkan agar riwayat tiket tetap utuh.
                </p>
            </div>
            <Button data-test="new-user-button" @click="openCreate">
                <Plus class="size-4" aria-hidden="true" />
                Tambah pengguna
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="state.q"
                    type="search"
                    class="pl-9"
                    placeholder="Cari nama atau email..."
                    aria-label="Cari pengguna"
                />
            </div>

            <Select
                :model-value="state.role"
                @update:model-value="(v) => setFilter('role', v)"
            >
                <SelectTrigger class="w-full sm:w-44" aria-label="Filter role">
                    <SelectValue placeholder="Role" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua role</SelectItem>
                    <SelectItem
                        v-for="role in roles"
                        :key="role.value"
                        :value="role.value"
                    >
                        {{ role.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="state.status"
                @update:model-value="(v) => setFilter('status', v)"
            >
                <SelectTrigger class="w-full sm:w-40" aria-label="Filter status">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua status</SelectItem>
                    <SelectItem value="active">Aktif</SelectItem>
                    <SelectItem value="inactive">Nonaktif</SelectItem>
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

        <Card :class="{ 'opacity-60': loading }">
            <CardContent class="px-2 sm:px-6">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead class="text-right">Tiket aktif</TableHead>
                            <TableHead>Dibuat</TableHead>
                            <TableHead>Aktif</TableHead>
                            <TableHead class="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="users.data.length === 0">
                            <TableCell
                                colspan="6"
                                class="py-8 text-center text-muted-foreground"
                            >
                                {{
                                    hasActiveFilters
                                        ? 'Tidak ada pengguna yang cocok dengan filter.'
                                        : 'Belum ada pengguna.'
                                }}
                            </TableCell>
                        </TableRow>

                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell>
                                <div class="font-medium">
                                    {{ user.name }}
                                    <span
                                        v-if="user.is_self"
                                        class="ml-1 text-xs font-normal text-muted-foreground"
                                    >
                                        (Anda)
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ user.email }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">
                                    {{ roleLabel(user.role) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ user.role === 'it_support' ? user.active_tickets : '—' }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap text-muted-foreground">
                                {{ formatDate(user.created_at) }}
                            </TableCell>
                            <TableCell>
                                <Switch
                                    :model-value="user.is_active"
                                    :disabled="user.is_self"
                                    :aria-label="`${user.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${user.name}`"
                                    @update:model-value="(v) => onToggle(user, Boolean(v))"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Edit ${user.name}`"
                                    @click="openEdit(user)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div
            v-if="users.total > 0"
            class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
        >
            <p>
                Menampilkan {{ users.from }}–{{ users.to }} dari
                {{ users.total }} pengguna
            </p>

            <div class="flex items-center gap-2">
                <span>
                    Halaman {{ users.current_page }} dari {{ users.last_page }}
                </span>
                <Button
                    v-if="users.prev_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="users.prev_page_url" preserve-scroll preserve-state>
                        Sebelumnya
                    </Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled>
                    Sebelumnya
                </Button>
                <Button
                    v-if="users.next_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="users.next_page_url" preserve-scroll preserve-state>
                        Berikutnya
                    </Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled>
                    Berikutnya
                </Button>
            </div>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{ editing ? 'Edit pengguna' : 'Tambah pengguna' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            editing
                                ? 'Ubah data akun. Kosongkan password jika tidak ingin menggantinya.'
                                : 'Akun baru langsung aktif dan bisa login dengan password ini.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="user-name">Nama</Label>
                    <Input
                        id="user-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-email">Email</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        required
                        maxlength="255"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-role">Role</Label>
                    <Select
                        v-model="form.role"
                        :disabled="editing?.is_self"
                    >
                        <SelectTrigger id="user-role" class="w-full">
                            <SelectValue placeholder="Pilih role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="role in roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="editing?.is_self"
                        class="text-xs text-muted-foreground"
                    >
                        Anda tidak dapat mengubah role akun sendiri.
                    </p>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-password">
                        {{ editing ? 'Password baru (opsional)' : 'Password' }}
                    </Label>
                    <Input
                        id="user-password"
                        v-model="form.password"
                        type="password"
                        :required="!editing"
                        autocomplete="new-password"
                    />
                    <p class="text-xs text-muted-foreground">
                        Minimal 8 karakter.
                    </p>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-password-confirmation">
                        Konfirmasi password
                    </Label>
                    <Input
                        id="user-password-confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        :required="!editing || form.password !== ''"
                        autocomplete="new-password"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-user-button"
                    >
                        {{ editing ? 'Simpan' : 'Buat akun' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog
        :open="deactivating !== null"
        @update:open="(open) => !open && (deactivating = null)"
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Nonaktifkan akun ini?</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ deactivating?.name }} tidak akan bisa login dan akan
                    langsung keluar dari sesi yang sedang berjalan.
                    <template v-if="deactivating && deactivating.active_tickets > 0">
                        Perhatian: masih ada
                        <strong>{{ deactivating.active_tickets }} tiket aktif</strong>
                        yang ditugaskan kepadanya. Tugaskan ulang tiket tersebut
                        dari halaman detail tiket.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Batal</AlertDialogCancel>
                <AlertDialogAction @click="confirmDeactivate">
                    Ya, nonaktifkan
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
