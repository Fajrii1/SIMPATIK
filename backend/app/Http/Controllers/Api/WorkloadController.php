<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\User;
use App\Services\WorkloadService;
use App\Support\AssignmentVisibility;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkloadController extends Controller
{
    public function __construct(
        protected WorkloadService $workloadService
    ) {}

    /**
     * Ringkasan beban kerja bulanan dengan pagination dan filter server-side.
     * GET /api/workload?month=YYYY-MM&team_id=X&search=...&page=1&per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::now()->format('Y-m');
        }

        $teamId = $request->query('team_id');
        $teamId = ($teamId === null || $teamId === '') ? null : (int) $teamId;

        $query = User::where('aktif', true);

        $userRole = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($userRole === 'pegawai') {
            $ledTeams = $currentUser->ledTeams->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! empty($ledTeams)) {
                if ($teamId !== null && in_array($teamId, $ledTeams, true)) {
                    $query->whereHas('teams', function ($q) use ($teamId) {
                        $q->where('teams.id', $teamId);
                    });
                } else {
                    $query->whereHas('teams', function ($q) use ($ledTeams) {
                        $q->whereIn('teams.id', $ledTeams);
                    });
                }
            } else {
                $query->where('id', $currentUser->id);
            }
        } elseif ($teamId !== null) {
            $query->whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $paginator = $query->with('teams')->orderBy('nama')->orderBy('id')->paginate($perPage);

        $workloads = [];

        foreach ($paginator->items() as $user) {
            $workload = $this->workloadService->calculateMonthlyWorkloadForUser($user, $month);

            $workloads[] = [
                'user' => [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'nip' => $user->nip,
                    'email' => $user->email,
                    'peran' => $user->peran instanceof \BackedEnum ? $user->peran->value : (string) $user->peran,
                    'teams' => $user->teams->map(fn ($team) => [
                        'id' => $team->id,
                        'nama' => $team->nama,
                    ])->values()->all(),
                ],
                'bulan' => $month,
                'jam_terpakai' => $workload['jam_terpakai'],
                'kapasitas_jam' => $workload['kapasitas_jam'],
                'persentase' => $workload['persentase'],
                'indikator' => $workload['indikator'],
                'jumlah_penugasan' => $workload['jumlah_penugasan'],
                'penugasan_bersamaan_maks' => $workload['penugasan_bersamaan_maks'],
            ];
        }

        return response()->json([
            'bulan' => $month,
            'jam_kerja_per_hari' => $this->workloadService->getDailyWorkHours(),
            'data' => $workloads,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Detail beban kerja pegawai untuk satu bulan, termasuk rincian kegiatan.
     * GET /api/workload/users/{id}?month=YYYY-MM&page=1&per_page=10
     */
    public function userDetail(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $targetUser = User::with('teams')->findOrFail($id);

        $userRole = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($userRole === 'pegawai') {
            $ledTeams = $currentUser->ledTeams->pluck('id')->map(fn ($id) => (int) $id)->all();
            $targetUserTeams = $targetUser->teams->pluck('id')->map(fn ($id) => (int) $id)->all();
            $isTeamMember = ! empty(array_intersect($ledTeams, $targetUserTeams));

            if ($currentUser->id !== $targetUser->id && ! $isTeamMember) {
                return response()->json(['message' => 'Anda tidak memiliki hak akses untuk melihat rincian beban kerja pegawai ini.'], 403);
            }
        }

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::now()->format('Y-m');
        }

        $workload = $this->workloadService->calculateMonthlyWorkloadForUser($targetUser, $month);
        $viewer = $currentUser;

        // Peta penugasan untuk melengkapi nama tim pada setiap rincian.
        $assignments = Assignment::query()
            ->with('activity.team')
            ->whereIn('id', collect($workload['rincian'])->pluck('assignment_id')->all())
            ->get()
            ->keyBy('id');

        $rows = collect($workload['rincian'])->map(function ($row) use ($assignments, $viewer) {
            $assignment = $assignments->get($row['assignment_id']);
            $teamName = $assignment?->activity?->team?->nama;
            $metricsVisible = $assignment
                ? AssignmentVisibility::canViewMetrics($viewer, $assignment)
                : false;

            return [
                'assignment_id' => $row['assignment_id'],
                'activity_id' => $row['activity_id'],
                'kegiatan' => $row['activity_nama'],
                'tim' => $teamName,
                'peran' => $row['peran'],
                'mulai' => $row['mulai'],
                'selesai' => $row['selesai'],
                'work_type_id' => $row['work_type_id'] ?? null,
                'work_type_nama' => $metricsVisible ? ($row['work_type_nama'] ?? null) : null,
                'volume' => $metricsVisible ? ($row['volume'] ?? null) : null,
                'satuan' => $metricsVisible ? ($row['satuan'] ?? null) : null,
                'norma_menit' => $metricsVisible ? ($row['norma_menit'] ?? null) : null,
                'total_menit' => $metricsVisible ? ($row['total_menit'] ?? null) : null,
                'total_jam' => $metricsVisible ? ($row['total_jam'] ?? null) : null,
                'jam_per_hari' => $metricsVisible ? $row['jam_per_hari'] : null,
                'hari_kerja' => $row['hari_kerja'],
                'jam_terpakai' => $metricsVisible ? $row['jam_terpakai'] : null,
                'metrics_visible' => $metricsVisible,
            ];
        })->values();

        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $page = max($request->integer('page', 1), 1);
        $total = $rows->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min($page, $lastPage);
        $paged = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'user' => [
                'id' => $targetUser->id,
                'nama' => $targetUser->nama,
                'nip' => $targetUser->nip,
                'email' => $targetUser->email,
                'peran' => $targetUser->peran instanceof \BackedEnum ? $targetUser->peran->value : (string) $targetUser->peran,
                'teams' => $targetUser->teams->pluck('nama')->toArray(),
            ],
            'bulan' => $month,
            'jam_terpakai' => $workload['jam_terpakai'],
            'kapasitas_jam' => $workload['kapasitas_jam'],
            'persentase' => $workload['persentase'],
            'indikator' => $workload['indikator'],
            'jam_kerja_per_hari' => $workload['jam_kerja_per_hari'],
            'penugasan' => $paged,
            'penugasan_meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ]);
    }

    /**
     * Daftar peringatan beban kerja bulanan.
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
        $teamId = ($teamId === null || $teamId === '') ? null : (int) $teamId;
        $allWarnings = $this->workloadService->getMonthlyWarnings($month);

        $userRole = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($userRole === 'pegawai') {
            $ledTeams = $currentUser->ledTeams->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! empty($ledTeams)) {
                $allowedUserIds = User::whereHas('teams', function ($q) use ($ledTeams) {
                    $q->whereIn('teams.id', $ledTeams);
                })->pluck('id')->map(fn ($id) => (int) $id)->all();

                $allWarnings = array_values(array_filter($allWarnings, function ($w) use ($allowedUserIds) {
                    return $w['pegawai_id'] === null
                        || in_array((int) $w['pegawai_id'], $allowedUserIds, true);
                }));
            } else {
                $allWarnings = array_values(array_filter($allWarnings, function ($w) use ($currentUser) {
                    return $w['pegawai_id'] !== null
                        && (int) $w['pegawai_id'] === (int) $currentUser->id;
                }));
            }
        } elseif ($teamId !== null) {
            $allowedUserIds = User::whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            })->pluck('id')->map(fn ($id) => (int) $id)->all();

            $allWarnings = array_values(array_filter($allWarnings, function ($w) use ($allowedUserIds) {
                return $w['pegawai_id'] === null
                    || in_array((int) $w['pegawai_id'], $allowedUserIds, true);
            }));
        }

        return response()->json([
            'bulan' => $month,
            'total_peringatan' => count($allWarnings),
            'data' => $allWarnings,
        ]);
    }
}
