<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
            'jam_kerja_per_hari' => ['sometimes', 'numeric', 'min:1', 'max:24'],
            'hari_libur' => ['sometimes', 'array'],
            'hari_libur.*' => ['date'],
            'maks_penugasan_bersamaan' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'maks_kegiatan_besar_bersamaan' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'pegawai_boleh_isi_kendala' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.*.kunci' => ['required_with:settings', 'string', 'in:jam_kerja_per_hari,hari_libur,maks_penugasan_bersamaan,maks_kegiatan_besar_bersamaan,pegawai_boleh_isi_kendala'],
            'settings.*.nilai' => ['required_with:settings'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'jam_kerja_per_hari.min' => 'Jam kerja per hari minimal 1 jam.',
            'jam_kerja_per_hari.max' => 'Jam kerja per hari maksimal 24 jam.',
            'hari_libur.*.date' => 'Tanggal hari libur tidak valid.',
            'maks_penugasan_bersamaan.min' => 'Maksimal penugasan bersamaan minimal 1.',
            'maks_kegiatan_besar_bersamaan.min' => 'Maksimal kegiatan besar bersamaan minimal 1.',
            'settings.*.kunci.in' => 'Kunci pengaturan tidak valid.',
        ];
    }
}
