<?php

namespace App\Models;

use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tidak ada field yang mass-assignable: semuanya diisi oleh kode setelah file
 * lolos validasi (nama asli hanya untuk ditampilkan, path dibuat acak).
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $comment_id
 * @property int $uploaded_by
 * @property string $original_name
 * @property string $stored_path
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 */
class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use HasFactory;

    /** Disk privat: file tidak bisa dibuka lewat URL langsung. */
    public const DISK = 'local';

    /**
     * Lampiran pada catatan internal disembunyikan dari karyawan.
     *
     * @param  Builder<TicketAttachment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin() && ! $user->isItSupport()) {
            $query->whereDoesntHave('comment', fn (Builder $q) => $q->where('is_internal', true));
        }
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'comment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
