<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Setting;
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
        $allowed = Setting::getVal('pegawai_boleh_isi_kendala', false);
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
}
