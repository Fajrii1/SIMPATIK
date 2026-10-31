<?php

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    /**
     * Semua pengguna terautentikasi dapat melihat pengaturan aplikasi.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Hanya admin yang dapat mengubah pengaturan aplikasi.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
