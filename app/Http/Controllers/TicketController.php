<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAttachmentStorage;
use App\Services\TicketWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TicketController extends Controller
{
    private const PER_PAGE = 10;

    private const SORTABLE = ['ticket_number', 'title', 'priority', 'status', 'created_at'];

    private const IT_SCOPES = ['assigned', 'queue', 'resolved'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ticket::class);

        $user = $request->user();
        $filters = $this->normalizeFilters($request, $user);

        $query = Ticket::query()->with(['category:id,name', 'requester:id,name', 'assignee:id,name']);

        $this->restrictToViewer($query, $user, $filters['scope']);
        $this->applyFilters($query, $filters, $user);
        $this->applySort($query, $filters['sort'], $filters['dir']);

        $tickets = $query->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'priority' => $ticket->priority->value,
                'status' => $ticket->status->value,
                'category' => $ticket->category?->name,
                'requester' => $ticket->requester?->name,
                'assignee' => $ticket->assignee?->name,
                'created_at' => $ticket->created_at?->toISOString(),
            ]);

        [$heading, $description] = $this->headings($user, $filters['scope']);

        return Inertia::render('tickets/Index', [
            'heading' => $heading,
            'description' => $description,
            'tickets' => $tickets,
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'technicians' => $user->isAdmin()
                ? User::query()->active()->withRole(Role::ItSupport)->orderBy('name')->get(['id', 'name'])
                : [],
            'canCreate' => $user->can('create', Ticket::class),
            'showRequester' => ! $user->isEmployee(),
            'showAssignee' => $user->isAdmin(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Ticket::class);

        return Inertia::render('tickets/Create', [
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'priorities' => StoreTicketRequest::allowedPriorities(),
        ]);
    }

    public function store(StoreTicketRequest $request, TicketWorkflow $workflow, TicketAttachmentStorage $attachments): RedirectResponse // <- 1: tambah parameter
{
    $user = $request->user();

    // Nomor tiket unik di database. Jika dua tiket dibuat bersamaan dan
    // nomornya bentrok, ulangi dengan nomor berikutnya.
    $ticket = retry(
        3,
        fn () => DB::transaction(function () use ($request, $user, $workflow) {
            $ticket = new Ticket($request->safe()->except('files')); // <- 2: ganti validated()
            $ticket->user_id = $user->id;
            $ticket->save();

            $workflow->recordCreated($ticket, $user);

            return $ticket;
        }),
        50,
        fn (Throwable $e) => $e instanceof UniqueConstraintViolationException,
    );

    $attachments->store($ticket, $user, $request->file('files', [])); // <- 3: baris baru

    Inertia::flash('toast', [
        'type' => 'success',
        'message' => __('Ticket :number created.', ['number' => $ticket->ticket_number]),
    ]);

    return to_route('tickets.index');
}

    /**
     * Validasi dan bersihkan parameter daftar. Nilai yang tidak dikenal diabaikan.
     *
     * @return array{search: string, status: string, priority: string, category_id: string, assigned_to: string, scope: string, sort: string, dir: string}
     */
    private function normalizeFilters(Request $request, User $user): array
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'dir' => ['nullable', 'string'],
        ]);

        $scope = '';
        if ($user->isItSupport()) {
            $scope = in_array($input['scope'] ?? null, self::IT_SCOPES, true) ? $input['scope'] : 'assigned';
        }

        $assignedTo = (string) ($input['assigned_to'] ?? '');
        if (! $user->isAdmin() || ($assignedTo !== 'unassigned' && ! ctype_digit($assignedTo))) {
            $assignedTo = '';
        }

        // Antrean diurutkan dari yang paling mendesak, daftar lain dari yang terbaru.
        $defaultSort = $scope === 'queue' ? 'priority' : 'created_at';
        $sort = in_array($input['sort'] ?? null, self::SORTABLE, true) ? $input['sort'] : $defaultSort;
        $dir = in_array($input['dir'] ?? null, ['asc', 'desc'], true) ? $input['dir'] : 'desc';

        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'status' => in_array($input['status'] ?? null, TicketStatus::values(), true) ? $input['status'] : '',
            'priority' => in_array($input['priority'] ?? null, TicketPriority::values(), true) ? $input['priority'] : '',
            'category_id' => isset($input['category_id']) ? (string) $input['category_id'] : '',
            'assigned_to' => $assignedTo,
            'scope' => $scope,
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /**
     * Batasi tiket sesuai peran. Ini harus sejalan dengan TicketPolicy::view.
     */
    private function restrictToViewer(Builder $query, User $user, string $scope): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (! $user->isItSupport()) {
            $query->where('user_id', $user->id);

            return;
        }

        $finished = [TicketStatus::Resolved->value, TicketStatus::Closed->value];

        if ($scope === 'queue') {
            $query->whereNull('assigned_to');
        } elseif ($scope === 'resolved') {
            $query->where('assigned_to', $user->id)->whereIn('status', $finished);
        } else {
            $query->where('assigned_to', $user->id)->whereNotIn('status', $finished);
        }
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $filters, User $user): void
    {
        if ($filters['search'] !== '') {
            // Karakter khusus LIKE dianggap teks biasa.
            $like = '%'.addcslashes($filters['search'], '\\%_').'%';

            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('ticket_number', 'like', $like));
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['priority'] !== '') {
            $query->where('priority', $filters['priority']);
        }

        if ($filters['category_id'] !== '') {
            $query->where('category_id', $filters['category_id']);
        }

        if ($user->isAdmin() && $filters['assigned_to'] !== '') {
            $filters['assigned_to'] === 'unassigned'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $filters['assigned_to']);
        }
    }

    private function applySort(Builder $query, string $sort, string $dir): void
    {
        if ($sort === 'priority') {
            $cases = collect(TicketPriority::cases())
                ->map(fn (TicketPriority $p) => "WHEN '{$p->value}' THEN {$p->weight()}")
                ->implode(' ');

            // $dir sudah dibatasi ke asc atau desc oleh normalizeFilters().
            $query->orderByRaw("CASE priority {$cases} END {$dir}");
        } else {
            $query->orderBy($sort, $dir);
        }

        $query->orderByDesc('id');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function headings(User $user, string $scope): array
    {
        if ($user->isAdmin()) {
            return ['All Tickets', 'Seluruh tiket di sistem.'];
        }

        if (! $user->isItSupport()) {
            return ['My Tickets', 'Tiket yang Anda laporkan.'];
        }

        return match ($scope) {
            'queue' => ['Ticket Queue', 'Tiket yang belum ditugaskan. Ambil tiket untuk menanganinya.'],
            'resolved' => ['Resolved Tickets', 'Tiket Anda yang sudah selesai.'],
            default => ['Assigned Tickets', 'Tiket yang ditugaskan kepada Anda dan masih berjalan.'],
        };
    }
}
