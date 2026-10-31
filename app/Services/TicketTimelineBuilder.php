<?php

namespace App\Services;

use App\Enums\TicketEvent;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menyusun riwayat tiket menjadi daftar kalimat yang siap ditampilkan.
 * Di Phase 3 komentar akan digabungkan ke daftar yang sama.
 */
class TicketTimelineBuilder
{
    private const FIELD_LABELS = [
        'title' => 'judul',
        'description' => 'deskripsi',
        'category_id' => 'kategori',
    ];

    /**
     * @return array<int, array{id: int, actor: string, at: string|null, text: string}>
     */
    public function build(Ticket $ticket): array
    {
        $histories = $ticket->histories()->with('actor:id,name')->get();
        $names = $this->technicianNames($histories);

        return $histories
            ->map(fn (TicketHistory $history) => [
                'id' => $history->id,
                'actor' => $history->actor?->name ?? 'Sistem',
                'at' => $history->created_at?->toISOString(),
                'text' => $this->describe($history, $names),
            ])
            ->values()
            ->all();
    }

    /**
     * Nama pengguna yang disebut pada riwayat penugasan (disimpan sebagai id).
     *
     * @param  Collection<int, TicketHistory>  $histories
     * @return Collection<int|string, string>
     */
    private function technicianNames(Collection $histories): Collection
    {
        $ids = $histories
            ->filter(fn (TicketHistory $h) => $h->event_type === TicketEvent::Assigned)
            ->flatMap(fn (TicketHistory $h) => [$h->old_value, $h->new_value])
            ->filter()
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $ids)->pluck('name', 'id');
    }

    /**
     * @param  Collection<int|string, string>  $names
     */
    private function describe(TicketHistory $history, Collection $names): string
    {
        $name = fn (?string $id) => $id === null ? '-' : ($names[$id] ?? "#{$id}");

        return match ($history->event_type) {
            TicketEvent::Created => 'Tiket dibuat',
            TicketEvent::Updated => 'Tiket diperbarui ('.$this->fieldLabels($history->new_value).')',
            TicketEvent::StatusChanged => sprintf(
                'Status diubah dari %s ke %s',
                TicketStatus::tryFrom((string) $history->old_value)?->label() ?? '-',
                TicketStatus::tryFrom((string) $history->new_value)?->label() ?? '-',
            ),
            TicketEvent::PriorityChanged => sprintf(
                'Prioritas diubah dari %s ke %s',
                TicketPriority::tryFrom((string) $history->old_value)?->label() ?? '-',
                TicketPriority::tryFrom((string) $history->new_value)?->label() ?? '-',
            ),
            TicketEvent::Assigned => $history->old_value === null
                ? 'Ditugaskan kepada '.$name($history->new_value)
                : sprintf(
                    'Penugasan dialihkan dari %s ke %s',
                    $name($history->old_value),
                    $name($history->new_value),
                ),
        };
    }

    private function fieldLabels(?string $fields): string
    {
        return collect(explode(',', (string) $fields))
            ->filter()
            ->map(fn (string $field) => self::FIELD_LABELS[$field] ?? $field)
            ->implode(', ');
    }
}
