<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Semua pengguna terautentikasi dapat melihat daftar tim.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Semua pengguna terautentikasi dapat melihat detail tim dan anggotanya.
     */
    public function view(User $user, Team $team): bool
    {
        return true;
    }

    /**
     * Hanya admin yang dapat membuat tim baru.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hanya admin yang dapat memperbarui tim.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hanya admin yang dapat menghapus tim.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hanya admin yang dapat mengelola keanggotaan tim.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $user->isAdmin();
    }
}
