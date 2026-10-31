<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTeamRequest extends FormRequest
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
        return [
            'nama' => ['required', 'string', 'max:100'],
            'ketua_id' => ['nullable', 'integer', 'exists:users,id'],
            'aktif' => ['nullable', 'boolean'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama tim wajib diisi.',
            'nama.max' => 'Nama tim maksimal 100 karakter.',
            'ketua_id.exists' => 'Pengguna yang dipilih sebagai ketua tidak valid.',
            'member_ids.*.exists' => 'Pengguna yang dipilih sebagai anggota tidak valid.',
        ];
    }
}
