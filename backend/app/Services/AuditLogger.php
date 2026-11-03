<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Catat satu mutasi ke audit_logs.
     *
     * @param  array<string, mixed>|null  $sebelum
     * @param  array<string, mixed>|null  $sesudah
     */
    public function log(
        string $aksi,
        string $objek,
        ?int $objekId,
        ?array $sebelum = null,
        ?array $sesudah = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'aksi' => $aksi,
            'objek' => $objek,
            'objek_id' => $objekId,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
        ]);
    }

    /**
     * Snapshot data pengguna tanpa kredensial.
     *
     * @return array<string, mixed>
     */
    public function userSnapshot(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'peran' => $user->peran instanceof \BackedEnum ? $user->peran->value : $user->peran,
            'nip' => $user->nip,
            'jabatan' => $user->jabatan,
            'aktif' => (bool) $user->aktif,
            'must_change_password' => (bool) $user->must_change_password,
            'team_ids' => $user->teams()
                ->pluck('teams.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    /**
     * Snapshot data tim beserta daftar anggota.
     *
     * @return array<string, mixed>
     */
    public function teamSnapshot(Team $team): array
    {
        return [
            'id' => $team->id,
            'nama' => $team->nama,
            'ketua_id' => $team->ketua_id !== null ? (int) $team->ketua_id : null,
            'aktif' => (bool) $team->aktif,
            'member_ids' => $team->members()
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * Snapshot umum tanpa atribut sensitif.
     *
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    public function snapshot(Model $model, array $except = []): array
    {
        return Arr::except(
            $model->toArray(),
            array_merge(['password', 'remember_token'], $except)
        );
    }
}
