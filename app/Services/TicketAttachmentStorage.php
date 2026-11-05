<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Aturan dan penyimpanan lampiran, dipakai bersama oleh unggahan di halaman
 * tiket dan unggahan saat tiket dibuat.
 */
class TicketAttachmentStorage
{
    public const MAX_FILES = 5;

    /** Batas awal PRD: 5 MB per file (dalam kilobyte untuk aturan validasi). */
    public const MAX_KILOBYTES = 5120;

    /**
     * Aturan validasi untuk input `files`.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(bool $required): array
    {
        $files = $required
            ? ['required', 'array', 'min:1', 'max:'.self::MAX_FILES]
            : ['nullable', 'array', 'max:'.self::MAX_FILES];

        return [
            'files' => $files,
            'files.*' => ['file', 'mimes:png,jpg,jpeg,pdf', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * Simpan file di disk privat dengan nama acak dan catat di database.
     * Ekstensi diambil dari isi file, bukan dari nama kiriman.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function store(Ticket $ticket, User $uploader, array $files, ?TicketComment $comment = null): void
    {
        foreach ($files as $file) {
            $path = $file->storeAs(
                'ticket-attachments/'.$ticket->id,
                Str::uuid().'.'.$file->extension(),
                TicketAttachment::DISK,
            );

            $attachment = new TicketAttachment;
            $attachment->ticket_id = $ticket->id;
            $attachment->comment_id = $comment?->id;
            $attachment->uploaded_by = $uploader->id;
            $attachment->original_name = $file->getClientOriginalName();
            $attachment->stored_path = $path;
            $attachment->mime_type = (string) $file->getMimeType();
            $attachment->size = (int) $file->getSize();
            $attachment->save();
        }
    }
}
