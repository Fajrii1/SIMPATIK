<?php

namespace App\Http\Requests;

use App\Enums\JenisKegiatan;
use App\Enums\SatuanPenugasan;
use App\Models\User;
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
        return [
            'nama' => ['required', 'string', 'max:200'],
            'jenis' => ['required', 'string', Rule::in(JenisKegiatan::values())],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'mulai' => ['required', 'date'],
            'selesai' => ['sometimes', 'date', 'after_or_equal:mulai'],
            'bobot' => ['nullable', 'numeric', 'min:0.5', 'max:3.0'],
            'kegiatan_besar' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
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
            'bobot.min' => 'Bobot kegiatan minimal 0,50.',
            'bobot.max' => 'Bobot kegiatan maksimal 3,00.',
            'assignments.required' => 'Minimal satu penugasan wajib disertakan, dengan satu Penanggung Jawab.',
            'assignments.min' => 'Minimal satu penugasan wajib disertakan, dengan satu Penanggung Jawab.',
            'assignments.array' => 'Format daftar penugasan tidak valid.',
            'assignments.*.user_id.required' => 'Pegawai yang ditugaskan wajib dipilih.',
            'assignments.*.user_id.exists' => 'Pegawai tidak ditemukan.',
            'assignments.*.peran.required' => 'Peran penugasan wajib diisi.',
            'assignments.*.peran.max' => 'Peran penugasan maksimal 60 karakter.',
            'assignments.*.volume.required' => 'Volume penugasan wajib diisi.',
            'assignments.*.volume.min' => 'Volume penugasan minimal 0.01.',
            'assignments.*.satuan.required' => 'Satuan penugasan wajib dipilih.',
            'assignments.*.satuan.in' => 'Satuan penugasan tidak valid.',
        ];
    }
}
