<?php

namespace App\Http\Requests;

use App\Enums\SatuanPenugasan;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewAssignmentBatchRequest extends FormRequest
{
    /**
     * Hanya admin atau ketua tim dari team_id yang boleh memanggil pratinjau batch.
     */
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
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'mulai' => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'bobot' => ['required', 'numeric', 'min:0.5', 'max:3.0'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.peran' => ['required', 'string', 'max:60'],
            'assignments.*.volume' => ['required', 'numeric', 'min:0.01'],
            'assignments.*.satuan' => ['required', 'string', Rule::in(SatuanPenugasan::values())],
            'assignments.*.mulai' => ['nullable', 'date'],
            'assignments.*.selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
        ];
    }

    /**
     * Pegawai yang ditugaskan harus aktif dan tidak boleh duplikat dalam satu batch.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $assignments = $this->input('assignments');

            if (! is_array($assignments)) {
                return;
            }

            $seenUserIds = [];

            foreach ($assignments as $index => $assignment) {
                if (! is_array($assignment)) {
                    continue;
                }

                $userId = isset($assignment['user_id']) ? (int) $assignment['user_id'] : 0;

                if (in_array($userId, $seenUserIds, true)) {
                    $validator->errors()->add(
                        "assignments.{$index}.user_id",
                        'Pegawai yang sama tidak boleh muncul dua kali dalam satu pratinjau.'
                    );
                }

                $seenUserIds[] = $userId;

                $user = $userId > 0 ? User::find($userId) : null;

                if ($user && ! $user->aktif) {
                    $validator->errors()->add(
                        "assignments.{$index}.user_id",
                        'Pegawai yang ditugaskan harus berstatus aktif.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_id.required' => 'Tim penanggung jawab wajib dipilih.',
            'team_id.exists' => 'Tim penanggung jawab tidak ditemukan.',
            'mulai.required' => 'Tanggal mulai kegiatan wajib diisi.',
            'selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai kegiatan.',
            'bobot.required' => 'Bobot kegiatan wajib diisi.',
            'bobot.min' => 'Bobot kegiatan minimal 0,50.',
            'bobot.max' => 'Bobot kegiatan maksimal 3,00.',
            'assignments.required' => 'Minimal satu baris penugasan wajib disertakan.',
            'assignments.min' => 'Minimal satu baris penugasan wajib disertakan.',
            'assignments.*.volume.required' => 'Volume penugasan wajib diisi.',
            'assignments.*.satuan.required' => 'Satuan penugasan wajib dipilih.',
        ];
    }
}
