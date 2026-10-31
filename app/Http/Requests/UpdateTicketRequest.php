<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit informasi tiket: judul, deskripsi, kategori. Status, prioritas, dan
 * penugasan tidak bisa diubah lewat sini.
 */
class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket && $this->user()->can('update', $ticket);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentCategoryId = $this->route('ticket')->category_id;

        return [
            // Kategori nonaktif tetap sah jika memang kategori tiket saat ini.
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $currentCategoryId)
                ),
            ],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
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
        ];
    }
}
