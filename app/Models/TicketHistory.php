<?php

namespace App\Models;

use App\Enums\TicketEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Riwayat bersifat catat-saja (append only), sehingga hanya punya created_at.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $actor_id
 * @property TicketEvent $event_type
 * @property string|null $old_value
 * @property string|null $new_value
 * @property Carbon|null $created_at
 */
#[Fillable(['ticket_id', 'actor_id', 'event_type', 'old_value', 'new_value'])]
class TicketHistory extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (TicketHistory $history): void {
            $history->created_at ??= now();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => TicketEvent::class,
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
