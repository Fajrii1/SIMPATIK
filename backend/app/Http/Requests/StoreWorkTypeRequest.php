<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkTypeRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:120', 'unique:work_types,nama'],
            'satuan' => ['required', 'string', 'max:30'],
            'norma_menit' => ['required', 'integer', 'min:1', 'max:65535'],
            'jenis_norma' => ['required', 'string', 'in:estimasi,standar'],
            'sumber' => ['nullable', 'string', 'max:255'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis pekerjaan wajib diisi.',
            'nama.unique' => 'Nama jenis pekerjaan sudah dipakai.',
            'satuan.required' => 'Satuan wajib diisi.',
            'norma_menit.required' => 'Norma waktu wajib diisi.',
            'norma_menit.integer' => 'Norma waktu harus berupa bilangan bulat (menit).',
            'norma_menit.min' => 'Norma waktu minimal 1 menit.',
            'norma_menit.max' => 'Norma waktu maksimal 65.535 menit sesuai kapasitas penyimpanan.',
            'jenis_norma.required' => 'Jenis norma wajib dipilih.',
            'jenis_norma.in' => 'Jenis norma harus estimasi atau standar.',
        ];
    }
}
