<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Dipakai untuk membuat dan mengedit pengguna.
 *
 * Status aktif tidak ikut di sini: akun baru selalu aktif, dan perubahan
 * status lewat endpoint tersendiri. Saat mengedit, password boleh kosong
 * (artinya tidak diubah).
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $editing = $target instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($editing ? $target->id : null),
            ],
            'role' => ['required', Rule::in(Role::values())],
            'password' => [
                $editing ? 'nullable' : 'required',
                'string',
                Password::defaults(),
                'confirmed',
            ],
        ];
    }
}
