<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkTypeRequest extends FormRequest
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
        $workTypeId = $this->route('workType')?->id;

        return [
            'nama' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('work_types', 'nama')->ignore($workTypeId)],
            'satuan' => ['sometimes', 'required', 'string', 'max:30'],
            'norma_menit' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
            'jenis_norma' => ['sometimes', 'required', 'string', 'in:estimasi,standar'],
            'sumber' => ['nullable', 'string', 'max:255'],
            'aktif' => ['sometimes', 'boolean'],
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
            'jenis_norma.in' => 'Jenis norma harus estimasi atau standar.',
        ];
    }
}
