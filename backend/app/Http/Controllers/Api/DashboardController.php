<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKendala;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyIssue;
use App\Services\WorkloadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected WorkloadService $workloadService
    ) {}

    /**
     * Data dashboard yang adaptif terhadap role pengguna (Admin, Pimpinan, Ketua Tim, Pegawai).
     * GET /api/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->peran instanceof \BackedEnum ? $user->peran->value : (string) $user->peran;
        $isKetua = $user->ledTeams()->exists();
        $currentMonth = Carbon::now()->format('Y-m');
        $today = Carbon::today()->toDateString();

        $data = [
            'user' => [
                'id' => $user->id,
                'nama' => $user->nama,
                'role' => $role,
                'is_ketua' => $isKetua,
                'teams' => $user->teams->pluck('nama')->toArray(),
            ],
            'periode' => [
                'bulan' => $currentMonth,
                'hari_ini' => $today,
            ],
        ];

        // 1. BEBAN KERJA PRIBADI (tersedia untuk semua role)
        $personalWorkload = $this->workloadService->calculateMonthlyWorkloadForUser($user->id, $currentMonth);
        $data['beban_kerja_pribadi'] = [
            'poin' => $personalWorkload['beban'] ?? $personalWorkload['poin'] ?? 0.0,
            'persentase' => $personalWorkload['persentase'],
            'indikator' => $personalWorkload['indikator'],
            'jumlah_penugasan' => $personalWorkload['jumlah_penugasan'],
            'penugasan_bersamaan_maks' => $personalWorkload['penugasan_bersamaan_maks'],
            'ambang_batas' => $this->workloadService->getMonthlyThreshold(),
        ];

        // 2. STATISTIK SESUAI ROLE
        if ($role === 'admin' || $role === 'pimpinan') {
            $totalPegawai = User::where('aktif', true)->count();
            $totalTim = Team::count();
            $totalKegiatan = Activity::count();

            // Status kegiatan dinamis
            $activities = Activity::all();
            $kegiatanBerjalan = $activities->filter(fn ($a) => $a->status === 'Berjalan')->count();
            $kegiatanSelesai = $activities->filter(fn ($a) => $a->status === 'Selesai')->count();
            $kegiatanTerlambat = $activities->filter(fn ($a) => $a->status === 'Terlambat')->count();

            // Peringatan beban kerja
            $warnings = $this->workloadService->getMonthlyWarnings($currentMonth);

            // Kendala aktif
            $kendalaAktif = WeeklyIssue::where('status', '!=', StatusKendala::SELESAI->value)->count();

            $data['statistik'] = [
                'total_pegawai' => $totalPegawai,
                'total_tim' => $totalTim,
                'total_kegiatan' => $totalKegiatan,
                'kegiatan_berjalan' => $kegiatanBerjalan,
                'kegiatan_selesai' => $kegiatanSelesai,
                'kegiatan_terlambat' => $kegiatanTerlambat,
                'total_peringatan_beban' => count($warnings),
                'kendala_belum_selesai' => $kendalaAktif,
            ];

            // Aktivitas audit terbaru
            $data['audit_terbaru'] = AuditLog::with('user')
                ->orderByDesc('id')
                ->take(5)
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'aktor' => $l->user ? $l->user->nama : 'Sistem',
                    'aksi' => $l->aksi,
                    'objek' => $l->objek,
                    'waktu' => $l->created_at?->diffForHumans(),
                ]);

            $data['peringatan_terbaru'] = array_slice($warnings, 0, 5);
        }

        // STATISTIK KETUA TIM (Jika memimpin tim)
        if ($isKetua) {
            $ledTeamIds = $user->ledTeams->pluck('id')->toArray();
            $timKegiatan = Activity::whereIn('team_id', $ledTeamIds)->get();
            $timMembersCount = User::whereHas('teams', fn ($q) => $q->whereIn('teams.id', $ledTeamIds))->count();

            $data['tim_dipimpin'] = [
                'daftar_tim' => $user->ledTeams->pluck('nama')->toArray(),
                'total_anggota' => $timMembersCount,
                'total_kegiatan' => $timKegiatan->count(),
                'kegiatan_berjalan' => $timKegiatan->filter(fn ($a) => $a->status === 'Berjalan')->count(),
                'kendala_tim' => WeeklyIssue::whereIn('team_id', $ledTeamIds)
                    ->where('status', '!=', StatusKendala::SELESAI->value)
                    ->count(),
            ];
        }

        // 3. AGENDA / PENUGASAN AKTIF PENGGUNA
        $myAssignments = Assignment::with(['activity.team'])
            ->where('user_id', $user->id)
            ->where(function ($q) use ($today) {
                $q->where('selesai', '>=', $today)
                    ->orWhere(function ($sub) use ($today) {
                        $sub->whereNull('selesai')
                            ->whereHas('activity', fn ($act) => $act->where('selesai', '>=', $today));
                    });
            })
            ->take(5)
            ->get();

        $data['penugasan_aktif'] = $myAssignments->map(fn ($as) => [
            'id' => $as->id,
            'kegiatan_id' => $as->activity_id,
            'kegiatan' => $as->activity->nama,
            'tim' => $as->activity->team->nama,
            'peran' => $as->peran,
            'mulai' => $as->getEffectiveMulai()->toDateString(),
            'selesai' => $as->getEffectiveSelesai()->toDateString(),
            'poin' => (float) $as->poin_beban,
            'status_kegiatan' => $as->activity->status,
        ]);

        // 4. NOTIFIKASI BELUM DIBACA
        $unreadCount = Notification::where('user_id', $user->id)->unread()->count();
        $recentNotifications = Notification::where('user_id', $user->id)
            ->orderByDesc('id')
            ->take(5)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'pesan' => $n->pesan,
                'tipe' => $n->tipe,
                'tautan' => $n->tautan,
                'is_read' => $n->isRead(),
                'waktu' => $n->created_at?->diffForHumans(),
            ]);

        $data['notifikasi'] = [
            'unread_count' => $unreadCount,
            'terbaru' => $recentNotifications,
        ];

        return response()->json(['data' => $data]);
    }
}
