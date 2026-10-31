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

        // Cek izin pegawai mengisi kendala dari pengaturan sistem
        $allowed = Setting::getVal('pegawai_boleh_isi_kendala', false);
        if ($allowed) {
            return true;
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
