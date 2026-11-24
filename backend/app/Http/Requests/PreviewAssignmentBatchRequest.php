<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Models\User;
use App\Services\WorkloadService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.peran' => ['required', 'string', 'max:60'],
            'assignments.*.work_type_id' => ['nullable', 'integer', 'exists:work_types,id', 'required_without:assignments.*.jam_per_hari'],
            'assignments.*.jam_per_hari' => ['nullable', 'numeric', 'min:0.25', 'max:'.(float) Setting::getVal('jam_kerja_per_hari', 7), 'required_without:assignments.*.work_type_id'],
            'assignments.*.volume' => ['required', 'numeric', 'min:0.01'],
            // Satuan mengikuti master jenis pekerjaan; payload lama tetap diterima.
            'assignments.*.satuan' => ['nullable', 'string', 'max:30'],
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
            $dailyWorkHours = app(WorkloadService::class)->getDailyWorkHours();

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

                if (isset($assignment['jam_per_hari'])) {
                    $value = (float) $assignment['jam_per_hari'];
                    $steps = $value / 0.25;

                    if (abs($steps - round($steps)) > 0.00001) {
                        $validator->errors()->add(
                            "assignments.{$index}.jam_per_hari",
                            'Jam per hari harus berupa kelipatan 0,25.'
                        );
                    }

                    if ($value > $dailyWorkHours + 0.0001) {
                        $validator->errors()->add(
                            "assignments.{$index}.jam_per_hari",
                            'Jam per hari tidak boleh melebihi jam kerja per hari pada pengaturan.'
                        );
                    }
                }

                $start = ! empty($assignment['mulai'])
                    ? Carbon::parse($assignment['mulai'])->startOfDay()
                    : ($this->filled('mulai') ? Carbon::parse($this->input('mulai'))->startOfDay() : null);
                $end = ! empty($assignment['selesai'])
                    ? Carbon::parse($assignment['selesai'])->startOfDay()
                    : ($this->filled('selesai') ? Carbon::parse($this->input('selesai'))->startOfDay() : $start);

                if ($start && $end && app(WorkloadService::class)->countWorkdays($start, $end) === 0) {
                    $validator->errors()->add(
                        "assignments.{$index}.mulai",
                        'Periode penugasan tidak memuat satu pun hari kerja (Senin-Jumat, di luar hari libur).'
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
            'assignments.required' => 'Minimal satu baris penugasan wajib disertakan.',
            'assignments.min' => 'Minimal satu baris penugasan wajib disertakan.',
            'assignments.*.jam_per_hari.required' => 'Jam per hari wajib diisi.',
            'assignments.*.jam_per_hari.numeric' => 'Jam per hari harus berupa angka.',
            'assignments.*.jam_per_hari.min' => 'Jam per hari minimal 0,25 jam.',
            'assignments.*.jam_per_hari.max' => 'Jam per hari tidak boleh melebihi jam kerja per hari pada pengaturan.',
            'assignments.*.work_type_id.required_without' => 'Jenis pekerjaan wajib dipilih.',
            'assignments.*.work_type_id.exists' => 'Jenis pekerjaan tidak ditemukan.',
            'assignments.*.volume.required' => 'Volume pekerjaan wajib diisi.',
            'assignments.*.volume.numeric' => 'Volume penugasan harus berupa angka.',
        ];
    }
}
