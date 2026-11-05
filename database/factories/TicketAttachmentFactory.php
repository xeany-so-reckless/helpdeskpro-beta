<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'comment_id' => null,
            'uploaded_by' => User::factory(),
            'original_name' => 'screenshot.png',
            'stored_path' => 'ticket-attachments/0/'.Str::uuid().'.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ];
    }
}
