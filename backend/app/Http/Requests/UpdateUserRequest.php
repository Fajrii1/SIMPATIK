<?php

namespace App\Http\Requests;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetUserId = $this->route('user') instanceof User
            ? $this->route('user')->id
            : (int) $this->route('user');

        return [
            'nama' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($targetUserId)],
            'peran' => ['sometimes', 'required', Rule::enum(PeranPengguna::class)],
            'nip' => ['nullable', 'string', 'max:30'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8'],
            'aktif' => ['sometimes', 'boolean'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama pengguna wajib diisi.',
            'nama.max' => 'Nama pengguna maksimal 120 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'email.max' => 'Email maksimal 150 karakter.',
            'peran.required' => 'Peran pengguna wajib dipilih.',
            'peran.Illuminate\Validation\Rules\Enum' => 'Peran pengguna tidak valid.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'team_ids.*.exists' => 'Tim yang dipilih tidak valid.',
        ];
    }
}
