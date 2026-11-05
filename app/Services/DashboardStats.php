<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

/**
 * Angka dan daftar untuk dashboard. Setiap role hanya dihitung dari tiket
 * yang memang boleh dilihatnya (sejalan dengan TicketPolicy::view).
 *
 * Pengelompokan status:
 * - terbuka  : Open, Assigned, Reopened (belum mulai dikerjakan)
 * - diproses : In Progress
 * - selesai  : Resolved, Closed
 */
class DashboardStats
{
    private const RECENT_LIMIT = 5;

    /** Pilihan periode (hari) untuk grafik dan performa tim. */
    public const PERIODS = [7, 30, 90];

    public const DEFAULT_PERIOD = 30;

    /**
     * @return array<string, mixed>
     */
    public function forEmployee(User $user): array
    {
        $mine = fn (): Builder => Ticket::query()->where('user_id', $user->id);
        $byStatus = $this->countByStatus($mine());

        return [
            'stats' => [
                'total' => array_sum($byStatus),
                'open' => $this->sum($byStatus, [TicketStatus::Open, TicketStatus::Assigned, TicketStatus::Reopened]),
                'in_progress' => $this->sum($byStatus, [TicketStatus::InProgress]),
                'finished' => $this->sum($byStatus, [TicketStatus::Resolved, TicketStatus::Closed]),
            ],
            'recent' => $this->rows($mine()->orderByDesc('created_at')->orderByDesc('id')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forItSupport(User $user): array
    {
        $mine = fn (): Builder => Ticket::query()->where('assigned_to', $user->id);
        $finished = [TicketStatus::Resolved->value, TicketStatus::Closed->value];
        $queue = fn (): Builder => Ticket::query()->whereNull('assigned_to');

        return [
            'stats' => [
                'assigned' => $mine()->whereNotIn('status', $finished)->count(),
                'queue' => $queue()->count(),
                'in_progress' => $mine()->where('status', TicketStatus::InProgress)->count(),
                'high_priority' => $mine()->whereNotIn('status', $finished)
                    ->whereIn('priority', [TicketPriority::High->value, TicketPriority::Critical->value])
                    ->count(),
                'finished' => $mine()->whereIn('status', $finished)->count(),
            ],
            'queue' => $this->rows($this->mostUrgentFirst($queue())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forAdmin(int $days = self::DEFAULT_PERIOD): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;
        $from = today()->subDays($days - 1);

        $byStatus = $this->countByStatus(Ticket::query());
        $byPriority = Ticket::query()
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'stats' => [
                'total' => array_sum($byStatus),
                'unassigned' => Ticket::query()->whereNull('assigned_to')->count(),
                'in_progress' => $this->sum($byStatus, [TicketStatus::InProgress]),
                'finished' => $this->sum($byStatus, [TicketStatus::Resolved, TicketStatus::Closed]),
            ],
            // Semua nilai ditampilkan, termasuk yang jumlahnya nol.
            'byStatus' => collect(TicketStatus::cases())
                ->map(fn (TicketStatus $s) => ['key' => $s->value, 'label' => $s->label(), 'count' => $byStatus[$s->value] ?? 0])
                ->all(),
            'byPriority' => collect(TicketPriority::cases())
                ->map(fn (TicketPriority $p) => ['key' => $p->value, 'label' => ucfirst($p->value), 'count' => $byPriority[$p->value] ?? 0])
                ->all(),
            'unassigned' => $this->rows($this->mostUrgentFirst(Ticket::query()->whereNull('assigned_to'))),
            'period' => ['days' => $days, 'options' => self::PERIODS],
            'volume' => $this->volume($from),
            'team' => $this->team($from),
        ];
    }

    /**
     * Tiket dibuat dan diselesaikan per hari sejak $from sampai hari ini.
     * Hari tanpa tiket tetap muncul dengan nilai nol agar garisnya utuh.
     *
     * @return array<int, array{date: string, created: int, resolved: int}>
     */
    private function volume(CarbonInterface $from): array
    {
        $perDay = fn (string $column) => Ticket::query()
            ->where($column, '>=', $from)
            ->selectRaw("DATE({$column}) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($count) => (int) $count);

        $created = $perDay('created_at');
        $resolved = $perDay('resolved_at');

        return collect(CarbonPeriod::create($from, today()))
            ->map(fn (CarbonInterface $date) => [
                'date' => $date->toDateString(),
                'created' => $created[$date->toDateString()] ?? 0,
                'resolved' => $resolved[$date->toDateString()] ?? 0,
            ])
            ->all();
    }

    /**
     * Beban dan hasil kerja tiap teknisi aktif. Tiket dikreditkan ke teknisi
     * yang sedang memegangnya. Waktu penyelesaian dihitung dari tiket dibuat
     * sampai diselesaikan, hanya untuk tiket yang selesai dalam periode.
     *
     * @return array<int, array{id: int, name: string, active: int, resolved: int, avg_hours: float|null}>
     */
    private function team(CarbonInterface $from): array
    {
        $finished = [TicketStatus::Resolved->value, TicketStatus::Closed->value];

        $active = Ticket::query()
            ->whereNotNull('assigned_to')
            ->whereNotIn('status', $finished)
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $resolved = Ticket::query()
            ->whereNotNull('assigned_to')
            ->where('resolved_at', '>=', $from)
            ->get(['assigned_to', 'created_at', 'resolved_at'])
            ->groupBy('assigned_to');

        return User::query()->active()->withRole(Role::ItSupport)->orderBy('name')->get(['id', 'name'])
            ->map(function (User $tech) use ($active, $resolved) {
                $done = $resolved->get($tech->id, collect());
                $seconds = $done->sum(fn (Ticket $t) => $t->resolved_at->getTimestamp() - $t->created_at->getTimestamp());

                return [
                    'id' => $tech->id,
                    'name' => $tech->name,
                    'active' => (int) ($active[$tech->id] ?? 0),
                    'resolved' => $done->count(),
                    'avg_hours' => $done->isEmpty() ? null : round($seconds / $done->count() / 3600, 1),
                ];
            })
            ->sortByDesc('resolved')
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return array<string, int>
     */
    private function countByStatus(Builder $query): array
    {
        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, int>  $byStatus
     * @param  array<int, TicketStatus>  $statuses
     */
    private function sum(array $byStatus, array $statuses): int
    {
        return (int) collect($statuses)->sum(fn (TicketStatus $s) => $byStatus[$s->value] ?? 0);
    }

    /**
     * Paling mendesak dulu, lalu yang paling lama menunggu.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function mostUrgentFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return array<int, array<string, mixed>>
     */
    private function rows(Builder $query): array
    {
        return $query->with(['category:id,name', 'requester:id,name'])
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'priority' => $ticket->priority->value,
                'status' => $ticket->status->value,
                'category' => $ticket->category?->name,
                'requester' => $ticket->requester?->name,
                'created_at' => $ticket->created_at?->toISOString(),
            ])
            ->all();
    }
}
