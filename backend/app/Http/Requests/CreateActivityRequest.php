<?php

namespace App\Http\Requests;

use App\Enums\JenisKegiatan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $teamId = $this->integer('team_id');

        return $teamId > 0 && $user->isKetuaOf($teamId);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:200'],
            'jenis' => ['required', 'string', Rule::in(JenisKegiatan::values())],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after_or_equal:mulai'],
            'bobot' => ['nullable', 'numeric', 'min:0.01', 'max:99.99'],
            'kegiatan_besar' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kegiatan wajib diisi.',
            'nama.max' => 'Nama kegiatan maksimal 200 karakter.',
            'jenis.required' => 'Jenis kegiatan wajib dipilih.',
            'jenis.in' => 'Jenis kegiatan tidak valid.',
            'team_id.required' => 'Tim penanggung jawab wajib dipilih.',
            'team_id.exists' => 'Tim penanggung jawab tidak ditemukan.',
            'mulai.required' => 'Tanggal mulai kegiatan wajib diisi.',
            'mulai.date' => 'Format tanggal mulai tidak valid.',
            'selesai.required' => 'Tanggal selesai kegiatan wajib diisi.',
            'selesai.date' => 'Format tanggal selesai tidak valid.',
            'selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai kegiatan.',
            'bobot.min' => 'Bobot kegiatan minimal 0.01.',
            'bobot.max' => 'Bobot kegiatan maksimal 99.99.',
        ];
    }
}
