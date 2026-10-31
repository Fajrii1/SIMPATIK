<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    /**
     * Semua pengguna terautentikasi dapat melihat daftar kegiatan (PRD Bagian 14).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Semua pengguna terautentikasi dapat melihat detail kegiatan.
     */
    public function view(User $user, Activity $activity): bool
    {
        return true;
    }

    /**
     * Admin dan ketua tim dapat membuat kegiatan baru (PRD Bagian 5 & 14).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->ledTeams()->exists();
    }

    /**
     * Admin dapat mengubah semua kegiatan; Ketua tim hanya dapat mengubah kegiatan timnya (PRD Bagian 5 & 14).
     */
    public function update(User $user, Activity $activity): bool
    {
        return $user->isAdmin() || $user->isKetuaOf($activity->team_id);
    }

    /**
     * Admin dapat menghapus semua kegiatan; Ketua tim hanya dapat menghapus kegiatan timnya.
     */
    public function delete(User $user, Activity $activity): bool
    {
        return $user->isAdmin() || $user->isKetuaOf($activity->team_id);
    }

    /**
     * Menandai kegiatan selesai (Admin atau ketua tim bersangkutan).
     */
    public function complete(User $user, Activity $activity): bool
    {
        return $user->isAdmin() || $user->isKetuaOf($activity->team_id);
    }

    /**
     * Membuka kembali kegiatan (Admin atau ketua tim bersangkutan).
     */
    public function reopen(User $user, Activity $activity): bool
    {
        return $user->isAdmin() || $user->isKetuaOf($activity->team_id);
    }
}
