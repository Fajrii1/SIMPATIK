<?php

namespace App\Services;

class AuditLogger
{
    public function log(...$args): void
    {
        // Fitur audit log telah dihapus; method ini dipertahankan sebagai no-op.
    }

    public function userSnapshot(mixed $user): array
    {
        if (is_object($user)) {
            return ['aktif' => (bool) ($user->aktif ?? true)];
        }

        return ['aktif' => true];
    }

    public function teamSnapshot(mixed $team): array
    {
        if (is_object($team)) {
            return [
                'ketua_id' => $team->ketua_id ?? null,
                'member_ids' => method_exists($team, 'members') ? $team->members()->pluck('user_id')->all() : [],
            ];
        }

        return ['ketua_id' => null, 'member_ids' => []];
    }
}
