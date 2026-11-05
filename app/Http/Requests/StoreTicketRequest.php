<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\TicketAttachmentStorage;

class StoreTicketRequest extends FormRequest
{
    /**
     * Prioritas yang boleh dipilih pelapor. Critical hanya ditetapkan staf
     * (admin atau teknisi) setelah tiket dibuat.
     *
     * @return array<int, string>
     */
    public static function allowedPriorities(): array
    {
        return [
            TicketPriority::Low->value,
            TicketPriority::Medium->value,
            TicketPriority::High->value,
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(self::allowedPriorities())],
            ...TicketAttachmentStorage::rules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'kategori',
            'title' => 'judul',
            'description' => 'deskripsi',
            'priority' => 'prioritas',
            'files' => 'lampiran',
        ];
    }
}
