<?php

namespace App\Http\Requests;

use App\Enums\SatuanPenugasan;
use App\Models\Activity;
use App\Models\Setting;
use App\Models\User;
use App\Services\WorkloadService;
use App\Support\PeranPenugasan;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateAssignmentRequest extends FormRequest
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

        /** @var Activity|null $activity */
        $activity = $this->route('activity');
        if (! $activity) {
            return false;
        }

        return $user->isKetuaOf($activity->team_id);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'peran' => ['required', 'string', 'max:60'],
            'jam_per_hari' => ['required', 'numeric', 'min:0.25', 'max:'.(float) Setting::getVal('jam_kerja_per_hari', 7)],
            'volume' => ['nullable', 'numeric', 'min:0.01'],
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
            /** @var Activity|null $activity */
            $activity = $this->route('activity');
            if (! $activity) {
                return;
            }

            // 1. Validasi pegawai yang ditugaskan harus aktif (PRD Bagian 8.4)
            if ($this->filled('user_id')) {
                $targetUser = User::find($this->integer('user_id'));
                if (! $targetUser || ! $targetUser->aktif) {
                    $validator->errors()->add('user_id', 'Pegawai yang ditugaskan harus berstatus aktif.');
                }

                $sudahDitugaskan = $activity->assignments()
                    ->where('user_id', $this->integer('user_id'))
                    ->exists();

                if ($sudahDitugaskan) {
                    $validator->errors()->add('user_id', 'Pegawai ini sudah ditugaskan pada kegiatan tersebut.');
                }
            }

            // 2. Satu kegiatan hanya boleh memiliki satu Penanggung Jawab.
            if (PeranPenugasan::isPenanggungJawab($this->input('peran'))) {
                $punyaPj = $activity->assignments()
                    ->where('peran', PeranPenugasan::PENANGGUNG_JAWAB)
                    ->exists();

                if ($punyaPj) {
                    $validator->errors()->add('peran', 'Kegiatan hanya boleh memiliki satu Penanggung Jawab.');
                }
            }

            // 3. jam per hari harus kelipatan 0,25.
            if ($this->filled('jam_per_hari')) {
                $this->validateJamStep($validator, 'jam_per_hari', (float) $this->input('jam_per_hari'));
            }

            // 4. Validasi rentang penugasan harus berada dalam periode kegiatan (PRD Bagian 8.4)
            $actMulai = Carbon::parse($activity->mulai)->startOfDay();
            $actSelesai = Carbon::parse($activity->selesai)->startOfDay();

            $mulai = $this->filled('mulai') ? Carbon::parse($this->input('mulai'))->startOfDay() : null;
            $selesai = $this->filled('selesai') ? Carbon::parse($this->input('selesai'))->startOfDay() : null;

            if ($mulai && $selesai && $selesai->lt($mulai)) {
                $validator->errors()->add('selesai', 'Tanggal selesai penugasan tidak boleh sebelum tanggal mulai.');
            }

            if ($mulai && ($mulai->lt($actMulai) || $mulai->gt($actSelesai))) {
                $validator->errors()->add('mulai', 'Tanggal mulai penugasan harus berada dalam periode kegiatan ('.$actMulai->format('d/m/Y').' - '.$actSelesai->format('d/m/Y').').');
            }

            if ($selesai && ($selesai->lt($actMulai) || $selesai->gt($actSelesai))) {
                $validator->errors()->add('selesai', 'Tanggal selesai penugasan harus berada dalam periode kegiatan ('.$actMulai->format('d/m/Y').' - '.$actSelesai->format('d/m/Y').').');
            }

            // 5. Penugasan wajib memuat minimal satu hari kerja.
            $effectiveStart = $mulai ?? $actMulai;
            $effectiveEnd = $selesai ?? $actSelesai;

            $workdays = app(WorkloadService::class)->countWorkdays($effectiveStart, $effectiveEnd);

            if ($workdays === 0) {
                $validator->errors()->add('mulai', 'Periode penugasan tidak memuat satu pun hari kerja (Senin-Jumat, di luar hari libur).');
            }
        });
    }

    private function validateJamStep(Validator $validator, string $field, float $value): void
    {
        $steps = $value / 0.25;

        if (abs($steps - round($steps)) > 0.00001) {
            $validator->errors()->add($field, 'Jam per hari harus berupa kelipatan 0,25.');
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
            'volume.numeric' => 'Volume penugasan harus berupa angka.',
            'volume.min' => 'Volume penugasan minimal 0.01.',
            'satuan.in' => 'Satuan penugasan tidak valid.',
        ];
    }
}
