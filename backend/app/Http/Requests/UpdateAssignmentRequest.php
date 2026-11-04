<?php

namespace App\Http\Requests;

use App\Enums\SatuanPenugasan;
use App\Models\Assignment;
use App\Models\User;
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
            'volume' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'satuan' => ['sometimes', 'required', 'string', Rule::in(SatuanPenugasan::values())],
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

            // 4. Validasi rentang penugasan terhadap periode kegiatan
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
        });
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
            'volume.required' => 'Volume penugasan wajib diisi.',
            'volume.min' => 'Volume penugasan minimal 0.01.',
            'satuan.required' => 'Satuan penugasan wajib dipilih.',
            'satuan.in' => 'Satuan penugasan tidak valid.',
        ];
    }
}
