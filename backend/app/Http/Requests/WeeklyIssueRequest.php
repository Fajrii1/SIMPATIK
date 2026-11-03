<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class WeeklyIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $role = $user->peran instanceof \BackedEnum ? $user->peran->value : (string) $user->peran;
        if ($role === 'admin') {
            return true;
        }

        // Cek izin pegawai mengisi kendala dari pengaturan sistem.
        // Bila diizinkan, pegawai tetap hanya boleh melaporkan kendala untuk tim
        // yang diikutinya (atau kegiatan milik tim tersebut) sesuai PRD Bagian 14.
        $allowed = filter_var(
            Setting::getVal('pegawai_boleh_isi_kendala', true),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($allowed) {
            $teamId = (int) $this->input('team_id');
            $userTeamIds = $user->teams()->pluck('teams.id')->all();

            if ($teamId > 0 && (in_array($teamId, $userTeamIds, true) || $user->isKetuaOf($teamId))) {
                return true;
            }

            $activity = $this->has('activity_id') ? Activity::find($this->input('activity_id')) : null;

            return $activity !== null
                && (in_array($activity->team_id, $userTeamIds, true) || $user->isKetuaOf($activity->team_id));
        }

        // Ketua tim selalu boleh mengisi kendala untuk timnya
        if ($this->has('team_id')) {
            return $user->isKetuaOf((int) $this->input('team_id'));
        }

        if ($this->has('activity_id')) {
            $activity = Activity::find($this->input('activity_id'));

            return $activity && $user->isKetuaOf($activity->team_id);
        }

        return $user->ledTeams()->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activity_id' => ['nullable', 'exists:activities,id'],
            'team_id' => ['required', 'exists:teams,id'],
            'minggu_mulai' => ['required', 'date'],
            'kendala' => ['required', 'string', 'max:2000'],
            'solusi' => ['nullable', 'string', 'max:2000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:2000'],
            'pic_id' => ['nullable', 'exists:users,id'],
            'tenggat' => ['nullable', 'date'],
        ];
    }

    /**
     * Aturan lintas isian: kegiatan harus milik tim yang dipilih dan PIC harus
     * pengguna aktif yang menjadi anggota tim yang dipilih.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $teamId = (int) $this->input('team_id');

            if ($teamId <= 0) {
                return;
            }

            if ($this->filled('activity_id')) {
                $activity = Activity::find((int) $this->input('activity_id'));

                if ($activity && (int) $activity->team_id !== $teamId) {
                    $validator->errors()->add(
                        'activity_id',
                        'Kegiatan yang dipilih bukan milik tim yang dipilih.'
                    );
                }
            }

            if ($this->filled('pic_id')) {
                $picActiveMember = User::query()
                    ->whereKey((int) $this->input('pic_id'))
                    ->where('aktif', true)
                    ->whereHas('teams', function ($query) use ($teamId) {
                        $query->where('teams.id', $teamId);
                    })
                    ->exists();

                if (! $picActiveMember) {
                    $validator->errors()->add(
                        'pic_id',
                        'PIC harus merupakan pengguna aktif yang menjadi anggota tim yang dipilih.'
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
            'team_id.required' => 'Tim terkait wajib dipilih.',
            'team_id.exists' => 'Tim yang dipilih tidak ditemukan.',
            'minggu_mulai.required' => 'Minggu mulai kendala wajib diisi.',
            'minggu_mulai.date' => 'Minggu mulai kendala harus berupa tanggal yang valid.',
            'kendala.required' => 'Uraian kendala wajib diisi.',
            'kendala.string' => 'Uraian kendala harus berupa teks.',
            'kendala.max' => 'Uraian kendala maksimal 2000 karakter.',
            'solusi.string' => 'Usulan solusi harus berupa teks.',
            'solusi.max' => 'Usulan solusi maksimal 2000 karakter.',
            'tindak_lanjut.string' => 'Tindak lanjut harus berupa teks.',
            'tindak_lanjut.max' => 'Tindak lanjut maksimal 2000 karakter.',
            'activity_id.exists' => 'Kegiatan yang dipilih tidak ditemukan.',
            'pic_id.exists' => 'PIC yang dipilih tidak ditemukan.',
            'tenggat.date' => 'Tenggat harus berupa tanggal yang valid.',
        ];
    }
}
