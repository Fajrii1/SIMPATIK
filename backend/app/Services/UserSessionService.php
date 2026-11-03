<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UserSessionService
{
    /**
     * Hapus sesi milik pengguna pada driver database.
     *
     * Driver lain dilewati karena sesi tidak disimpan di tabel sessions.
     */
    public function invalidate(int $userId, ?string $exceptSessionId = null): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        $table = config('session.table', 'sessions');

        $query = DB::table($table)->where('user_id', $userId);

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }
}
