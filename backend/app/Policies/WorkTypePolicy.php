<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkType;

class WorkTypePolicy
{
    /**
     * Admin dan ketua tim boleh membaca daftar jenis pekerjaan untuk form penugasan.
     */
    public function viewAny(User $user): bool
    {
        return $user->aktif && ($user->isAdmin() || $user->ledTeams()->exists());
    }

    /**
     * Admin dan ketua tim boleh melihat satu jenis pekerjaan.
     */
    public function view(User $user, WorkType $workType): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Hanya admin yang boleh menambah jenis pekerjaan.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hanya admin yang boleh mengubah jenis pekerjaan.
     */
    public function update(User $user, WorkType $workType): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hanya admin yang boleh menghapus jenis pekerjaan.
     */
    public function delete(User $user, WorkType $workType): bool
    {
        return $user->isAdmin();
    }
}
