<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Http\Requests\UserRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $this->normalizeFilters($request);

        $query = User::query()->orderBy('name')->orderBy('id');

        if ($filters['q'] !== '') {
            // "!" sebagai karakter escape: sama di MySQL dan SQLite, sehingga
            // % dan _ yang diketik pengguna dicari apa adanya.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($q) => $q
                ->whereRaw("name like ? escape '!'", [$like])
                ->orWhereRaw("email like ? escape '!'", [$like]));
        }

        if ($filters['role'] !== '') {
            $query->where('role', $filters['role']);
        }

        if ($filters['status'] !== '') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        $page = $query->paginate(self::PER_PAGE)->withQueryString();

        // Tiket aktif yang sedang dipegang tiap teknisi di halaman ini.
        $activeTickets = $this->activeTicketCounts($page->getCollection()->pluck('id')->all());
        $me = $request->user();

        return Inertia::render('users/Index', [
            'users' => $page->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
                'is_self' => $user->is($me),
                'active_tickets' => $activeTickets[$user->id] ?? 0,
                'created_at' => $user->created_at?->toISOString(),
            ]),
            'filters' => $filters,
            'roles' => collect(Role::cases())->map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()])->all(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        // Role, status, dan verifikasi tidak mass-assignable; diatur di sini.
        $user->role = Role::from($data['role']);
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return back();
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $newRole = Role::from($data['role']);

        if ($newRole !== $user->role) {
            Gate::authorize('changeRole', $user);

            // Teknisi yang masih memegang tiket aktif tidak boleh berganti role,
            // agar tiket tidak menggantung pada orang yang bukan teknisi.
            if ($user->role === Role::ItSupport && ($this->activeTicketCounts([$user->id])[$user->id] ?? 0) > 0) {
                throw ValidationException::withMessages([
                    'role' => __('Reassign this technician\'s active tickets before changing the role.'),
                ]);
            }

            $user->role = $newRole;
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    /**
     * Aktifkan atau nonaktifkan akun. Pengguna nonaktif otomatis dikeluarkan
     * pada request berikutnya oleh EnsureUserIsActive.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('changeStatus', $user);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->is_active = (bool) $data['is_active'];
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? __('User activated.') : __('User deactivated.'),
        ]);

        return back();
    }

    /**
     * @return array{q: string, role: string, status: string}
     */
    private function normalizeFilters(Request $request): array
    {
        $role = $request->query('role');
        $status = $request->query('status');
        $q = $request->query('q');

        return [
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'role' => is_string($role) && in_array($role, Role::values(), true) ? $role : '',
            'status' => is_string($status) && in_array($status, ['active', 'inactive'], true) ? $status : '',
        ];
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, int>  user id => jumlah tiket yang belum Resolved/Closed
     */
    private function activeTicketCounts(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return Ticket::query()
            ->whereIn('assigned_to', $userIds)
            ->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}