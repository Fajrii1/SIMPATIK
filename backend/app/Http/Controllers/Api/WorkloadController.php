<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkloadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkloadController extends Controller
{
    public function __construct(
        protected WorkloadService $workloadService
    ) {}

    /**
     * Ringkasan beban kerja bulanan untuk semua atau filtered pegawai.
     * GET /api/workload?month=YYYY-MM&team_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Validasi parameter bulan (format YYYY-MM), default bulan saat ini
        $month = $request->query('month', Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::now()->format('Y-m');
        }

        $teamId = $request->query('team_id');

        // Pengaturan hak akses PRD:
        // Admin & Pimpinan dapat melihat seluruh pegawai atau filter berdasarkan tim.
        // Ketua tim: default melihat tim yang dipimpinnya, atau jika admin melihat semua.
        // Pegawai biasa: hanya melihat bebannya sendiri atau anggota timnya.
        $query = User::where('aktif', true);

        $userRole = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($userRole === 'pegawai') {
            $ledTeams = $currentUser->ledTeams->pluck('id')->toArray();
            if (! empty($ledTeams)) {
                // User adalah ketua tim
                if ($teamId && in_array($teamId, $ledTeams)) {
                    $query->whereHas('teams', function ($q) use ($teamId) {
                        $q->where('teams.id', $teamId);
                    });
                } else {
                    $query->whereHas('teams', function ($q) use ($ledTeams) {
                        $q->whereIn('teams.id', $ledTeams);
                    });
                }
            } else {
                // Pegawai biasa hanya melihat bebannya sendiri
                $query->where('id', $currentUser->id);
            }
        } elseif ($teamId) {
            $query->whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            });
        }

        $users = $query->with('teams')->orderBy('nama')->get();

        $workloads = [];
        $totalPoints = 0;
        $overloadCount = 0;
        $warningCount = 0;

        foreach ($users as $user) {
            $workload = $this->workloadService->calculateMonthlyWorkloadForUser($user->id, $month);
            $points = $workload['beban'] ?? $workload['poin'] ?? 0.0;
            $workloads[] = [
                'user' => [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'nip' => $user->nip,
                    'email' => $user->email,
                    'peran' => $user->peran instanceof \BackedEnum ? $user->peran->value : (string) $user->peran,
                    'teams' => $user->teams->pluck('nama')->toArray(),
                ],
                'bulan' => $month,
                'poin' => $points,
                'persentase' => $workload['persentase'],
                'indikator' => $workload['indikator'],
                'jumlah_penugasan' => $workload['jumlah_penugasan'],
                'penugasan_bersamaan_maks' => $workload['penugasan_bersamaan_maks'],
            ];

            $totalPoints += $points;
            if ($workload['indikator'] === 'melebihi') {
                $overloadCount++;
            } elseif ($workload['indikator'] === 'hampir_penuh') {
                $warningCount++;
            }
        }

        return response()->json([
            'bulan' => $month,
            'ambang_batas' => $this->workloadService->getMonthlyThreshold(),
            'ringkasan' => [
                'total_pegawai' => count($workloads),
                'total_poin' => round($totalPoints, 2),
                'rata_rata_poin' => count($workloads) > 0 ? round($totalPoints / count($workloads), 2) : 0,
                'pegawai_melebihi_beban' => $overloadCount,
                'pegawai_hampir_penuh' => $warningCount,
            ],
            'data' => $workloads,
        ]);
    }

    /**
     * Detail beban kerja pegawai spesifik dengan rincian penugasan dan tren bulan.
     * GET /api/workload/users/{id}?from=YYYY-MM&to=YYYY-MM
     */
    public function userDetail(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $targetUser = User::with('teams')->findOrFail($id);

        // Hak akses: admin & pimpinan bebas; ketua tim hanya boleh akses anggota tim yang dipimpin; pegawai hanya boleh akses dirinya sendiri
        $userRole = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($userRole === 'pegawai') {
            $ledTeams = $currentUser->ledTeams->pluck('id')->toArray();
            $targetUserTeams = $targetUser->teams->pluck('id')->toArray();
            $isTeamMember = ! empty(array_intersect($ledTeams, $targetUserTeams));

            if ($currentUser->id !== $targetUser->id && ! $isTeamMember) {
                return response()->json(['message' => 'Anda tidak memiliki hak akses untuk melihat rincian beban kerja pegawai ini.'], 403);
            }
        }

        $now = Carbon::now();
        $fromMonth = $request->query('from', $now->copy()->subMonths(2)->format('Y-m'));
        $toMonth = $request->query('to', $now->copy()->addMonths(2)->format('Y-m'));

        $start = Carbon::createFromFormat('Y-m', $fromMonth)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $toMonth)->endOfMonth();

        $history = [];
        $currentCursor = $start->copy();

        while ($currentCursor->lessThanOrEqualTo($end)) {
            $mStr = $currentCursor->format('Y-m');
            $workload = $this->workloadService->calculateMonthlyWorkloadForUser($targetUser->id, $mStr);
            $points = $workload['beban'] ?? $workload['poin'] ?? 0.0;
            $history[] = [
                'bulan' => $mStr,
                'poin' => $points,
                'persentase' => $workload['persentase'],
                'indikator' => $workload['indikator'],
                'jumlah_penugasan' => $workload['jumlah_penugasan'],
                'penugasan_bersamaan_maks' => $workload['penugasan_bersamaan_maks'],
            ];
            $currentCursor->addMonth();
        }

        // Ambil penugasan aktif atau penugasan di rentang periode
        $assignments = $targetUser->assignments()
            ->with(['activity.team'])
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($start, $end) {
                    $sub->whereNotNull('assignments.mulai')
                        ->whereBetween('assignments.mulai', [$start->toDateString(), $end->toDateString()]);
                })
                    ->orWhere(function ($sub) use ($start, $end) {
                        $sub->whereNotNull('assignments.selesai')
                            ->whereBetween('assignments.selesai', [$start->toDateString(), $end->toDateString()]);
                    })
                    ->orWhere(function ($sub) use ($start, $end) {
                        $sub->whereNull('assignments.mulai')
                            ->whereHas('activity', fn ($act) => $act->whereBetween('mulai', [$start->toDateString(), $end->toDateString()]));
                    })
                    ->orWhere(function ($sub) use ($start, $end) {
                        $sub->whereNull('assignments.selesai')
                            ->whereHas('activity', fn ($act) => $act->whereBetween('selesai', [$start->toDateString(), $end->toDateString()]));
                    });
            })
            ->get();

        $assignmentDetails = $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'activity_id' => $assignment->activity_id,
                'kegiatan' => $assignment->activity->nama,
                'jenis_kegiatan' => $assignment->activity->jenis->value,
                'tim' => $assignment->activity->team->nama,
                'peran' => $assignment->peran,
                'volume' => $assignment->volume,
                'satuan' => $assignment->satuan->value,
                'bobot' => (float) $assignment->activity->bobot,
                'poin_total' => (float) $assignment->poin_beban,
                'tanggal_mulai' => $assignment->getEffectiveMulai()->toDateString(),
                'tanggal_selesai' => $assignment->getEffectiveSelesai()->toDateString(),
                'status_kegiatan' => $assignment->activity->status,
            ];
        });

        return response()->json([
            'user' => [
                'id' => $targetUser->id,
                'nama' => $targetUser->nama,
                'nip' => $targetUser->nip,
                'email' => $targetUser->email,
                'peran' => $targetUser->peran instanceof \BackedEnum ? $targetUser->peran->value : (string) $targetUser->peran,
                'teams' => $targetUser->teams->pluck('nama')->toArray(),
            ],
            'ambang_batas' => $this->workloadService->getMonthlyThreshold(),
            'tren_bulanan' => $history,
            'penugasan' => $assignmentDetails,
        ]);
    }

    /**
     * Daftar peringatan beban kerja bulanan (beban lebih, penugasan bersamaan tinggi, kegiatan besar ganda).
     * GET /api/workload/warnings?month=YYYY-MM&team_id=X
     */
    public function warnings(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::now()->format('Y-m');
        }

        $teamId = $request->query('team_id');
        $allWarnings = $this->workloadService->getMonthlyWarnings($month);

        // Filter berdasarkan hak akses
        $userRole = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($userRole === 'pegawai') {
            $ledTeams = $currentUser->ledTeams->pluck('id')->toArray();
            if (! empty($ledTeams)) {
                // Filter hanya peringatan anggota tim yang dipimpin atau peringatan kantor umum
                $allowedUserIds = User::whereHas('teams', function ($q) use ($ledTeams) {
                    $q->whereIn('teams.id', $ledTeams);
                })->pluck('id')->toArray();

                $allWarnings = array_values(array_filter($allWarnings, function ($w) use ($allowedUserIds) {
                    return $w['pegawai_id'] === null || in_array($w['pegawai_id'], $allowedUserIds);
                }));
            } else {
                // Pegawai biasa hanya melihat peringatan untuk dirinya
                $allWarnings = array_values(array_filter($allWarnings, function ($w) use ($currentUser) {
                    return $w['pegawai_id'] === $currentUser->id;
                }));
            }
        } elseif ($teamId) {
            $allowedUserIds = User::whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            })->pluck('id')->toArray();

            $allWarnings = array_values(array_filter($allWarnings, function ($w) use ($allowedUserIds) {
                return $w['pegawai_id'] === null || in_array($w['pegawai_id'], $allowedUserIds);
            }));
        }

        return response()->json([
            'bulan' => $month,
            'total_peringatan' => count($allWarnings),
            'data' => $allWarnings,
        ]);
    }
}
