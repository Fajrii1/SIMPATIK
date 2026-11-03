<?php

namespace App\Http\Requests;

use App\Enums\StatusKendala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeeklyIssueStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Dicek di Controller/Policy
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StatusKendala::class)],
            'solusi' => ['nullable', 'string', 'max:2000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:2000'],
            'catatan_penutupan' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => $this->isClosingStatus()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'catatan_penutupan.required' => 'Catatan penutupan wajib diisi saat status kendala selesai.',
        ];
    }

    /**
     * Periksa status selesai baik berupa string maupun enum.
     */
    private function isClosingStatus(): bool
    {
        $status = $this->input('status');

        if ($status instanceof StatusKendala) {
            return $status === StatusKendala::SELESAI;
        }

        return $status === StatusKendala::SELESAI->value;
    }
}
