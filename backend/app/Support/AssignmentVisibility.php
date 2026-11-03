<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\User;

/**
 * Aturan terpusat visibilitas metrik penugasan (volume, satuan, poin).
 *
 * Sesuai keputusan 6: admin dan pimpinan melihat semua; ketua tim melihat
 * penugasan di kegiatan timnya; pegawai hanya melihat metrik miliknya. Orang
 * lain hanya melihat nama dan peran.
 */
final class AssignmentVisibility
{
    /**
     * Tentukan apakah viewer berhak melihat metrik penugasan.
     *
     * @param  Assignment|array{user_id: int}  $assignment
     */
    public static function canViewMetrics(?User $viewer, mixed $assignment, ?int $teamId = null): bool
    {
        if (! $viewer) {
            return false;
        }

        $role = $viewer->peran instanceof \BackedEnum ? $viewer->peran->value : (string) $viewer->peran;

        if ($role === 'admin' || $role === 'pimpinan') {
            return true;
        }

        $assignmentUserId = $assignment instanceof Assignment
            ? (int) $assignment->user_id
            : (int) ($assignment['user_id'] ?? 0);

        if ($assignmentUserId > 0 && $assignmentUserId === (int) $viewer->id) {
            return true;
        }

        $teamId ??= self::teamIdFromAssignment($assignment);

        if ($teamId !== null && $teamId > 0) {
            // Akses relasi sekali; hasil di-cache oleh Eloquent sampai instance disimpan.
            $ledTeamIds = $viewer->ledTeams->pluck('id')->map(fn ($id) => (int) $id)->all();

            return in_array($teamId, $ledTeamIds, true);
        }

        return false;
    }

    private static function teamIdFromAssignment(mixed $assignment): ?int
    {
        if (! $assignment instanceof Assignment) {
            return null;
        }

        $teamId = $assignment->activity?->team_id;

        if ($teamId === null) {
            $teamId = $assignment->activity()->value('team_id');
        }

        return $teamId !== null ? (int) $teamId : null;
    }
}
