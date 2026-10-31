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
            'ambang_beban_bulanan' => ['sometimes', 'numeric', 'min:1', 'max:1000'],
            'maks_penugasan_bersamaan' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'maks_kegiatan_besar_bersamaan' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'pegawai_boleh_isi_kendala' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.*.kunci' => ['required_with:settings', 'string', 'in:ambang_beban_bulanan,maks_penugasan_bersamaan,maks_kegiatan_besar_bersamaan,pegawai_boleh_isi_kendala'],
            'settings.*.nilai' => ['required_with:settings'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ambang_beban_bulanan.min' => 'Ambang beban bulanan minimal 1.',
            'maks_penugasan_bersamaan.min' => 'Maksimal penugasan bersamaan minimal 1.',
            'maks_kegiatan_besar_bersamaan.min' => 'Maksimal kegiatan besar bersamaan minimal 1.',
            'settings.*.kunci.in' => 'Kunci pengaturan tidak valid.',
        ];
    }
}
