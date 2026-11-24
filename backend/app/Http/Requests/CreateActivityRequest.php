<?php

namespace App\Http\Requests;

use App\Enums\JenisKegiatan;
use App\Enums\SatuanPenugasan;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkType;
use App\Services\WorkloadService;
use App\Support\PeranPenugasan;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
     * Tanggal selesai boleh dikosongkan; artinya sama dengan tanggal mulai.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('mulai') && ! $this->filled('selesai')) {
            $this->merge(['selesai' => $this->input('mulai')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxJam = (float) Setting::getVal('jam_kerja_per_hari', 7);

        return [
            'nama' => ['required', 'string', 'max:200'],
            'jenis' => ['required', 'string', Rule::in(JenisKegiatan::values())],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'mulai' => ['required', 'date'],
            'selesai' => ['sometimes', 'date', 'after_or_equal:mulai'],
            'kegiatan_besar' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.peran' => ['required', 'string', 'max:60'],
            'assignments.*.work_type_id' => ['nullable', 'integer', 'exists:work_types,id', 'required_without:assignments.*.jam_per_hari'],
            'assignments.*.jam_per_hari' => ['nullable', 'numeric', 'min:0.25', 'max:'.$maxJam, 'required_without:assignments.*.work_type_id'],
            'assignments.*.volume' => ['required', 'numeric', 'min:0.01'],
            'assignments.*.satuan' => ['nullable', 'string', Rule::in(SatuanPenugasan::values())],
            'assignments.*.mulai' => ['nullable', 'date'],
            'assignments.*.selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
        ];
    }

    /**
     * Validasi lanjutan untuk penugasan awal yang dikirim bersama kegiatan.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $assignments = $this->input('assignments');
            if (! is_array($assignments) || $assignments === []) {
                return;
            }

            $activityStart = $this->filled('mulai') ? Carbon::parse($this->input('mulai'))->startOfDay() : null;
            $activityEnd = $this->filled('selesai') ? Carbon::parse($this->input('selesai'))->startOfDay() : null;
            $workloadService = app(WorkloadService::class);

            $seenUserIds = [];
            $penanggungJawabCount = 0;

            foreach ($assignments as $index => $assignment) {
                if (! is_array($assignment)) {
                    continue;
                }

                $peran = isset($assignment['peran']) ? (string) $assignment['peran'] : '';

                if (PeranPenugasan::isPenanggungJawab($peran)) {
                    $penanggungJawabCount++;
                }

                $userId = isset($assignment['user_id']) ? (int) $assignment['user_id'] : 0;

                if (in_array($userId, $seenUserIds, true)) {
                    $validator->errors()->add(
                        "assignments.{$index}.user_id",
                        'Pegawai yang sama tidak boleh ditugaskan dua kali pada kegiatan ini.'
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
                }

                if (! empty($assignment['work_type_id'])) {
                    $workType = WorkType::find((int) $assignment['work_type_id']);

                    if ($workType && ! $workType->aktif) {
                        $validator->errors()->add(
                            "assignments.{$index}.work_type_id",
                            'Jenis pekerjaan yang dipilih sudah tidak aktif.'
                        );
                    }
                }

                $assignStart = ! empty($assignment['mulai']) ? Carbon::parse($assignment['mulai'])->startOfDay() : null;
                $assignEnd = ! empty($assignment['selesai']) ? Carbon::parse($assignment['selesai'])->startOfDay() : null;

                if ($assignStart && $assignEnd && $assignEnd->lt($assignStart)) {
                    $validator->errors()->add(
                        "assignments.{$index}.selesai",
                        'Tanggal selesai penugasan tidak boleh sebelum tanggal mulai.'
                    );
                }

                if ($assignStart && $activityStart && $assignStart->lt($activityStart)) {
                    $validator->errors()->add(
                        "assignments.{$index}.mulai",
                        'Tanggal mulai penugasan harus berada dalam periode kegiatan.'
                    );
                }

                if ($assignEnd && $activityEnd && $assignEnd->gt($activityEnd)) {
                    $validator->errors()->add(
                        "assignments.{$index}.selesai",
                        'Tanggal selesai penugasan harus berada dalam periode kegiatan.'
                    );
                }

                $effectiveStart = $assignStart ?? $activityStart;
                $effectiveEnd = $assignEnd ?? $activityEnd;

                if ($effectiveStart && $effectiveEnd && $workloadService->countWorkdays($effectiveStart, $effectiveEnd) === 0) {
                    $validator->errors()->add(
                        "assignments.{$index}.mulai",
                        'Periode penugasan tidak memuat satu pun hari kerja (Senin-Jumat, di luar hari libur).'
                    );
                }
            }

            if ($penanggungJawabCount === 0) {
                $validator->errors()->add(
                    'assignments',
                    'Kegiatan wajib memiliki satu Penanggung Jawab.'
                );
            } elseif ($penanggungJawabCount > 1) {
                $validator->errors()->add(
                    'assignments',
                    'Kegiatan hanya boleh memiliki satu Penanggung Jawab.'
                );
            }
        });
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
            'selesai.date' => 'Format tanggal selesai tidak valid.',
            'selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai kegiatan.',
            'assignments.required' => 'Minimal satu penugasan wajib disertakan, dengan satu Penanggung Jawab.',
            'assignments.min' => 'Minimal satu penugasan wajib disertakan, dengan satu Penanggung Jawab.',
            'assignments.array' => 'Format daftar penugasan tidak valid.',
            'assignments.*.user_id.required' => 'Pegawai yang ditugaskan wajib dipilih.',
            'assignments.*.user_id.exists' => 'Pegawai tidak ditemukan.',
            'assignments.*.peran.required' => 'Peran penugasan wajib diisi.',
            'assignments.*.peran.max' => 'Peran penugasan maksimal 60 karakter.',
            'assignments.*.jam_per_hari.required' => 'Jam per hari wajib diisi.',
            'assignments.*.jam_per_hari.numeric' => 'Jam per hari harus berupa angka.',
            'assignments.*.jam_per_hari.min' => 'Jam per hari minimal 0,25 jam.',
            'assignments.*.jam_per_hari.max' => 'Jam per hari tidak boleh melebihi jam kerja per hari pada pengaturan.',
            'assignments.*.work_type_id.required_without' => 'Jenis pekerjaan wajib dipilih.',
            'assignments.*.work_type_id.exists' => 'Jenis pekerjaan tidak ditemukan.',
            'assignments.*.volume.required' => 'Volume pekerjaan wajib diisi.',
            'assignments.*.volume.numeric' => 'Volume penugasan harus berupa angka.',
            'assignments.*.volume.min' => 'Volume penugasan minimal 0.01.',
            'assignments.*.satuan.in' => 'Satuan penugasan tidak valid.',
        ];
    }
}
