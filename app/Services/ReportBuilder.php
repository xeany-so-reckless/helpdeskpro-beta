<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Laporan tiket untuk rentang tanggal (FR-10).
 *
 * Tiket "dalam periode" berarti tiket yang DIBUAT pada rentang itu. Tiket
 * "diselesaikan" dihitung dari waktu penyelesaiannya (resolved_at).
 */
class ReportBuilder
{
    public const DEFAULT_DAYS = 30;

    /** Rentang terpanjang yang diizinkan, agar grafik dan query tetap ringan. */
    public const MAX_DAYS = 366;

    /**
     * Baca filter dari request. Nilai yang tidak valid diganti nilai bawaan,
     * rentang terbalik ditukar, dan rentang terlalu panjang dipangkas.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function range(Request $request): array
    {
        $today = CarbonImmutable::today();
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        if ($from === null && $to === null) {
            return ['from' => $today->subDays(self::DEFAULT_DAYS - 1), 'to' => $today];
        }

        $to ??= $today;
        $from ??= $to->subDays(self::DEFAULT_DAYS - 1);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->startOfDay();
        $end = $to->endOfDay();

        $created = fn (): Builder => Ticket::query()->whereBetween('created_at', [$start, $end]);

        $byStatus = $created()->selectRaw('status, count(*) as total')->groupBy('status')
            ->pluck('total', 'status')->map(fn ($n) => (int) $n);
        $byPriority = $created()->selectRaw('priority, count(*) as total')->groupBy('priority')
            ->pluck('total', 'priority')->map(fn ($n) => (int) $n);
        $byCategory = $created()->selectRaw('category_id, count(*) as total')->groupBy('category_id')
            ->pluck('total', 'category_id')->map(fn ($n) => (int) $n);

        $resolved = Ticket::query()
            ->whereBetween('resolved_at', [$start, $end])
            ->get(['assigned_to', 'created_at', 'resolved_at']);

        return [
            'summary' => [
                'created' => (int) $byStatus->sum(),
                'resolved' => $resolved->count(),
                'avg_hours' => $this->averageHours($resolved),
            ],
            'byStatus' => collect(TicketStatus::cases())
                ->map(fn (TicketStatus $s) => ['key' => $s->value, 'label' => $s->label(), 'count' => $byStatus[$s->value] ?? 0])
                ->all(),
            'byPriority' => collect(TicketPriority::cases())
                ->map(fn (TicketPriority $p) => ['key' => $p->value, 'label' => ucfirst($p->value), 'count' => $byPriority[$p->value] ?? 0])
                ->all(),
            'byCategory' => Category::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Category $c) => ['key' => (string) $c->id, 'label' => $c->name, 'count' => $byCategory[$c->id] ?? 0])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'volume' => $this->volume($start, $end),
            'team' => $this->team($resolved),
        ];
    }

    /**
     * @return array<int, array{date: string, created: int, resolved: int}>
     */
    private function volume(CarbonInterface $start, CarbonInterface $end): array
    {
        $perDay = fn (string $column) => Ticket::query()
            ->whereBetween($column, [$start, $end])
            ->selectRaw("DATE({$column}) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($n) => (int) $n);

        $created = $perDay('created_at');
        $resolved = $perDay('resolved_at');

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn (CarbonInterface $date) => [
                'date' => $date->toDateString(),
                'created' => $created[$date->toDateString()] ?? 0,
                'resolved' => $resolved[$date->toDateString()] ?? 0,
            ])
            ->all();
    }

    /**
     * Tiket dikreditkan ke teknisi yang sedang memegangnya.
     *
     * @param  \Illuminate\Support\Collection<int, Ticket>  $resolved  tiket selesai dalam periode
     * @return array<int, array{id: int, name: string, active: int, resolved: int, avg_hours: float|null}>
     */
    private function team($resolved): array
    {
        $finished = [TicketStatus::Resolved->value, TicketStatus::Closed->value];

        $active = Ticket::query()
            ->whereNotNull('assigned_to')
            ->whereNotIn('status', $finished)
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $byTech = $resolved->whereNotNull('assigned_to')->groupBy('assigned_to');

        return User::query()->active()->withRole(Role::ItSupport)->orderBy('name')->get(['id', 'name'])
            ->map(function (User $tech) use ($active, $byTech) {
                $done = $byTech->get($tech->id, collect());

                return [
                    'id' => $tech->id,
                    'name' => $tech->name,
                    'active' => (int) ($active[$tech->id] ?? 0),
                    'resolved' => $done->count(),
                    'avg_hours' => $this->averageHours($done),
                ];
            })
            ->sortByDesc('resolved')
            ->values()
            ->all();
    }

    /**
     * Rata-rata jam dari tiket dibuat sampai diselesaikan.
     *
     * @param  \Illuminate\Support\Collection<int, Ticket>  $tickets
     */
    private function averageHours($tickets): ?float
    {
        if ($tickets->isEmpty()) {
            return null;
        }

        $seconds = $tickets->sum(fn (Ticket $t) => $t->resolved_at->getTimestamp() - $t->created_at->getTimestamp());

        return round($seconds / $tickets->count() / 3600, 1);
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        // Tanggal mustahil (mis. 2026-02-31) tidak boleh "bergeser" diam-diam.
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
