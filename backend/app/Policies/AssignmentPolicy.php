<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    /**
     * Semua pengguna terautentikasi dan aktif dapat melihat daftar penugasan kegiatan.
     */
    public function viewAny(User $user): bool
    {
        return $user->aktif;
    }

    /**
     * Hak akses melihat detail penugasan:
     * - Admin dan Pimpinan dapat melihat seluruh penugasan
     * - Ketua Tim dapat melihat penugasan anggota tim yang menjadi kewenangannya
     * - Pegawai hanya dapat melihat penugasan miliknya sendiri
     * - Pengguna nonaktif ditolak
     */
    public function view(User $user, Assignment $assignment): bool
    {
        if (! $user->aktif) {
            return false;
        }

        if ($user->isAdmin() || $user->peran->value === 'pimpinan') {
            return true;
        }

        if ($user->id === $assignment->user_id) {
            return true;
        }

        $teamId = $assignment->activity?->team_id;
        if ($teamId && $user->isKetuaOf($teamId)) {
            return true;
        }

        return false;
    }

    /**
     * Admin dapat membuat penugasan pada semua kegiatan;
     * Ketua tim hanya pada kegiatan di tim yang dipimpinnya (PRD Bagian 5 & 14).
     */
    public function create(User $user, Activity $activity): bool
    {
        return $user->isAdmin() || $user->isKetuaOf($activity->team_id);
    }

    /**
     * Admin dapat memperbarui penugasan pada semua kegiatan;
     * Ketua tim hanya pada kegiatan di tim yang dipimpinnya.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        return $user->isAdmin() || $user->isKetuaOf($assignment->activity->team_id);
    }

    /**
     * Admin dapat menghapus penugasan pada semua kegiatan;
     * Ketua tim hanya pada kegiatan di tim yang dipimpinnya.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        return $user->isAdmin() || $user->isKetuaOf($assignment->activity->team_id);
    }

    /**
     * Pratinjau penugasan dapat diakses oleh admin dan ketua tim untuk tim yang dipimpinnya.
     */
    public function preview(User $user, ?Activity $activity = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($activity) {
            return $user->isKetuaOf($activity->team_id);
        }

        return $user->ledTeams()->exists();
    }
}
