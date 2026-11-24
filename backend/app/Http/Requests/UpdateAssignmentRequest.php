<?php

namespace App\Http\Requests;

use App\Enums\SatuanPenugasan;
use App\Models\Assignment;
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

class UpdateAssignmentRequest extends FormRequest
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

        /** @var Assignment|null $assignment */
        $assignment = $this->route('assignment');
        if (! $assignment || ! $assignment->activity) {
            return false;
        }

        return $user->isKetuaOf($assignment->activity->team_id);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'peran' => ['sometimes', 'required', 'string', 'max:60'],
            'work_type_id' => ['sometimes', 'nullable', 'integer', 'exists:work_types,id'],
            'jam_per_hari' => ['sometimes', 'nullable', 'numeric', 'min:0.25', 'max:'.(float) Setting::getVal('jam_kerja_per_hari', 7)],
            'volume' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'satuan' => ['nullable', 'string', Rule::in(SatuanPenugasan::values())],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date'],
        ];
    }

    /**
     * Validasi lanjutan untuk tanggal penugasan dan status keaktifan pegawai.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Assignment|null $assignment */
            $assignment = $this->route('assignment');
            if (! $assignment || ! $assignment->activity) {
                return;
            }

            $activity = $assignment->activity;

            $newUserId = $this->has('user_id') ? $this->integer('user_id') : (int) $assignment->user_id;
            $newPeran = $this->has('peran') ? (string) $this->input('peran') : (string) $assignment->peran;

            // 1. Validasi pegawai aktif
            if ($this->filled('user_id')) {
                $targetUser = User::find($this->integer('user_id'));
                if (! $targetUser || ! $targetUser->aktif) {
                    $validator->errors()->add('user_id', 'Pegawai yang ditugaskan harus berstatus aktif.');
                }
            }

            // 2. Pegawai yang sama tidak boleh memiliki dua penugasan pada kegiatan yang sama.
            $duplikat = $activity->assignments()
                ->where('user_id', $newUserId)
                ->where('id', '!=', $assignment->id)
                ->exists();

            if ($duplikat) {
                $validator->errors()->add('user_id', 'Pegawai ini sudah ditugaskan pada kegiatan tersebut.');
            }

            // 3. Jaga invariant tepat satu Penanggung Jawab per kegiatan.
            $pjLain = $activity->assignments()
                ->where('peran', PeranPenugasan::PENANGGUNG_JAWAB)
                ->where('id', '!=', $assignment->id)
                ->exists();

            if (PeranPenugasan::isPenanggungJawab($newPeran) && $pjLain) {
                $validator->errors()->add('peran', 'Kegiatan hanya boleh memiliki satu Penanggung Jawab.');
            }

            if (PeranPenugasan::isPenanggungJawab($assignment->peran) && ! PeranPenugasan::isPenanggungJawab($newPeran) && ! $pjLain) {
                $validator->errors()->add('peran', 'Kegiatan wajib memiliki satu Penanggung Jawab.');
            }

            // 4. jam per hari harus kelipatan 0,25 (mode lama, opsional).
            $newJam = $this->has('jam_per_hari') ? (float) $this->input('jam_per_hari') : (float) $assignment->jam_per_hari;
            $this->validateJamStep($validator, $newJam);

            // 4b. Jenis pekerjaan harus aktif dan volume wajib tersedia untuk perhitungan ABK.
            $newWorkTypeId = $this->has('work_type_id') ? $this->input('work_type_id') : $assignment->work_type_id;

            if ($newWorkTypeId !== null && $newWorkTypeId !== '') {
                $workType = WorkType::find((int) $newWorkTypeId);

                if ($workType && ! $workType->aktif) {
                    $validator->errors()->add('work_type_id', 'Jenis pekerjaan yang dipilih sudah tidak aktif.');
                }

                $hasVolume = $this->has('volume') ? $this->filled('volume') : $assignment->volume !== null;

                if (! $hasVolume) {
                    $validator->errors()->add('volume', 'Volume pekerjaan wajib diisi untuk perhitungan beban kerja.');
                }
            }

            // 5. Validasi rentang penugasan terhadap periode kegiatan
            $actMulai = Carbon::parse($activity->mulai)->startOfDay();
            $actSelesai = Carbon::parse($activity->selesai)->startOfDay();

            $mulai = $this->has('mulai')
                ? ($this->filled('mulai') ? Carbon::parse($this->input('mulai'))->startOfDay() : null)
                : ($assignment->mulai ? Carbon::parse($assignment->mulai)->startOfDay() : null);

            $selesai = $this->has('selesai')
                ? ($this->filled('selesai') ? Carbon::parse($this->input('selesai'))->startOfDay() : null)
                : ($assignment->selesai ? Carbon::parse($assignment->selesai)->startOfDay() : null);

            if ($mulai && $selesai && $selesai->lt($mulai)) {
                $validator->errors()->add('selesai', 'Tanggal selesai penugasan tidak boleh sebelum tanggal mulai.');
            }

            if ($mulai && ($mulai->lt($actMulai) || $mulai->gt($actSelesai))) {
                $validator->errors()->add('mulai', 'Tanggal mulai penugasan harus berada dalam periode kegiatan ('.$actMulai->format('d/m/Y').' - '.$actSelesai->format('d/m/Y').').');
            }

            if ($selesai && ($selesai->lt($actMulai) || $selesai->gt($actSelesai))) {
                $validator->errors()->add('selesai', 'Tanggal selesai penugasan harus berada dalam periode kegiatan ('.$actMulai->format('d/m/Y').' - '.$actSelesai->format('d/m/Y').').');
            }

            // 6. Penugasan wajib memuat minimal satu hari kerja.
            $effectiveStart = $mulai ?? $actMulai;
            $effectiveEnd = $selesai ?? $actSelesai;

            if (app(WorkloadService::class)->countWorkdays($effectiveStart, $effectiveEnd) === 0) {
                $validator->errors()->add('mulai', 'Periode penugasan tidak memuat satu pun hari kerja (Senin-Jumat, di luar hari libur).');
            }
        });
    }

    private function validateJamStep(Validator $validator, float $value): void
    {
        $steps = $value / 0.25;

        if (abs($steps - round($steps)) > 0.00001) {
            $validator->errors()->add('jam_per_hari', 'Jam per hari harus berupa kelipatan 0,25.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Pegawai yang ditugaskan wajib dipilih.',
            'user_id.exists' => 'Pegawai tidak ditemukan.',
            'peran.required' => 'Peran penugasan wajib diisi.',
            'peran.max' => 'Peran penugasan maksimal 60 karakter.',
            'jam_per_hari.required' => 'Jam per hari wajib diisi.',
            'jam_per_hari.numeric' => 'Jam per hari harus berupa angka.',
            'jam_per_hari.min' => 'Jam per hari minimal 0,25 jam.',
            'jam_per_hari.max' => 'Jam per hari tidak boleh melebihi jam kerja per hari pada pengaturan.',
            'work_type_id.exists' => 'Jenis pekerjaan tidak ditemukan.',
            'volume.required' => 'Volume pekerjaan wajib diisi.',
            'volume.numeric' => 'Volume penugasan harus berupa angka.',
            'volume.min' => 'Volume penugasan minimal 0.01.',
            'satuan.in' => 'Satuan penugasan tidak valid.',
        ];
    }
}
