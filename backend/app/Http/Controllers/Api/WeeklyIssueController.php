<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKendala;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWeeklyIssueStatusRequest;
use App\Http\Requests\WeeklyIssueRequest;
use App\Http\Resources\WeeklyIssueResource;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\WeeklyIssue;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeeklyIssueController extends Controller
{
    /**
     * Daftar kendala mingguan dengan filter minggu, status, dan tim.
     * GET /api/issues
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $currentUser = $request->user();
        $query = WeeklyIssue::with(['activity', 'team', 'pelapor', 'pic']);

        if ($request->has('team_id') && ! empty($request->query('team_id'))) {
            $query->where('team_id', $request->query('team_id'));
        }

        if ($request->has('status') && ! empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('week') && ! empty($request->query('week'))) {
            $weekStart = Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)->toDateString();
            $query->where('minggu_mulai', $weekStart);
        }

        // Hak akses PRD:
        // Admin & Pimpinan melihat semua kendala.
        // Pegawai/Ketua tim melihat kendala timnya atau yang dibuat olehnya.
        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($role === 'pegawai') {
            $userTeams = $currentUser->teams->pluck('id')->toArray();
            $query->where(function ($q) use ($currentUser, $userTeams) {
                $q->whereIn('team_id', $userTeams)
                    ->orWhere('pelapor_id', $currentUser->id)
                    ->orWhere('pic_id', $currentUser->id);
            });
        }

        $issues = $query->orderByDesc('minggu_mulai')->orderByDesc('id')->paginate(15);

        return WeeklyIssueResource::collection($issues);
    }

    /**
     * Kendala terbawa dari minggu-minggu sebelumnya yang belum berstatus 'selesai'.
     * GET /api/issues/carried?week=YYYY-MM-DD&team_id=X
     */
    public function carried(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $weekDate = $request->query('week', Carbon::now()->toDateString());
        $currentWeekMonday = Carbon::parse($weekDate)->startOfWeek(Carbon::MONDAY)->toDateString();

        $query = WeeklyIssue::with(['activity', 'team', 'pelapor', 'pic'])
            ->where('minggu_mulai', '<', $currentWeekMonday)
            ->where('status', '!=', StatusKendala::SELESAI->value);

        if ($request->has('team_id') && ! empty($request->query('team_id'))) {
            $query->where('team_id', $request->query('team_id'));
        }

        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($role === 'pegawai') {
            $userTeams = $currentUser->teams->pluck('id')->toArray();
            $query->where(function ($q) use ($currentUser, $userTeams) {
                $q->whereIn('team_id', $userTeams)
                    ->orWhere('pelapor_id', $currentUser->id)
                    ->orWhere('pic_id', $currentUser->id);
            });
        }

        $carriedIssues = $query->orderBy('minggu_mulai')->get();

        return response()->json([
            'minggu_acuan' => $currentWeekMonday,
            'total_terbawa' => $carriedIssues->count(),
            'data' => WeeklyIssueResource::collection($carriedIssues),
        ]);
    }

    /**
     * Buat kendala baru.
     * POST /api/issues
     */
    public function store(WeeklyIssueRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $data = $request->validated();

        // Pastikan minggu_mulai diselaraskan ke hari Senin (PRD 8.5)
        $mingguMulai = Carbon::parse($data['minggu_mulai'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $data['minggu_mulai'] = $mingguMulai;
        $data['pelapor_id'] = $currentUser->id;
        $data['status'] = StatusKendala::TERBUKA;

        $issue = WeeklyIssue::create($data);
        $issue->load(['activity', 'team', 'pelapor', 'pic']);

        // Log audit
        AuditLog::create([
            'user_id' => $currentUser->id,
            'aksi' => 'create',
            'objek' => 'WeeklyIssue',
            'objek_id' => $issue->id,
            'sebelum' => null,
            'sesudah' => $issue->toArray(),
        ]);

        // Kirim notifikasi ke Ketua Tim jika pelapor bukan ketua
        $team = $issue->team;
        if ($team && $team->ketua_id && $team->ketua_id !== $currentUser->id) {
            Notification::create([
                'user_id' => $team->ketua_id,
                'tipe' => 'kendala_baru',
                'pesan' => "Kendala baru dilaporkan pada tim {$team->nama}: \"{$issue->kendala}\"",
                'tautan' => "/issues?id={$issue->id}",
            ]);
        }

        // Kirim notifikasi ke PIC jika ditunjuk
        if ($issue->pic_id && $issue->pic_id !== $currentUser->id) {
            Notification::create([
                'user_id' => $issue->pic_id,
                'tipe' => 'kendala_pic',
                'pesan' => "Anda ditunjuk sebagai PIC kendala: \"{$issue->kendala}\"",
                'tautan' => "/issues?id={$issue->id}",
            ]);
        }

        return response()->json([
            'message' => 'Kendala mingguan berhasil dilaporkan.',
            'data' => new WeeklyIssueResource($issue),
        ], 201);
    }

    /**
     * Tampilkan detail kendala beserta semua field dan riwayat status.
     * GET /api/issues/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $issue = WeeklyIssue::with(['activity', 'team', 'pelapor', 'pic'])->findOrFail($id);

        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($role === 'pegawai') {
            $userTeams = $currentUser->teams->pluck('id')->toArray();
            $allowed = in_array($issue->team_id, $userTeams)
                || $issue->pelapor_id === $currentUser->id
                || $issue->pic_id === $currentUser->id;

            if (! $allowed) {
                return response()->json(['message' => 'Anda tidak memiliki hak akses melihat kendala ini.'], 403);
            }
        }

        $data = array_merge(
            (new WeeklyIssueResource($issue))->toArray($request),
            ['history' => $this->issueHistory($issue->id)]
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Edit kendala (admin, ketua tim terkait, atau pelapor sebelum selesai).
     * Kendala berstatus selesai terkunci: hanya admin atau ketua tim yang dapat mengubah,
     * dan PIC tidak dapat membuka kembali. PUT/PATCH /api/issues/{id}
     */
    public function update(WeeklyIssueRequest $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $issue = WeeklyIssue::findOrFail($id);

        $closed = $issue->status === StatusKendala::SELESAI;

        if ($closed) {
            if (! $this->isAdminOrKetua($currentUser, $issue->team_id)) {
                return response()->json([
                    'message' => 'Kendala yang sudah selesai tidak dapat diubah. Hanya Admin atau Ketua Tim yang dapat mengubah kendala selesai.',
                ], 422);
            }
        } else {
            $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
            $isAuthorized = $role === 'admin'
                || $currentUser->isKetuaOf($issue->team_id)
                || $issue->pelapor_id === $currentUser->id;

            if (! $isAuthorized) {
                return response()->json(['message' => 'Anda tidak memiliki wewenang untuk mengubah data kendala ini.'], 403);
            }
        }

        $oldData = $issue->toArray();
        $oldPicId = $issue->getOriginal('pic_id');

        $data = $request->validated();
        if (isset($data['minggu_mulai'])) {
            $data['minggu_mulai'] = Carbon::parse($data['minggu_mulai'])->startOfWeek(Carbon::MONDAY)->toDateString();
        }

        $issue->update($data);
        $issue->load(['activity', 'team', 'pelapor', 'pic']);

        AuditLog::create([
            'user_id' => $currentUser->id,
            'aksi' => 'update',
            'objek' => 'WeeklyIssue',
            'objek_id' => $issue->id,
            'sebelum' => $oldData,
            'sesudah' => $issue->toArray(),
        ]);

        // Notifikasi ke PIC yang baru bila ditunjuk/diubah dan bukan aktornya.
        if ($issue->pic_id && (int) $oldPicId !== (int) $issue->pic_id && (int) $issue->pic_id !== (int) $currentUser->id) {
            Notification::create([
                'user_id' => $issue->pic_id,
                'tipe' => 'kendala_pic',
                'pesan' => "Anda ditunjuk sebagai PIC kendala: \"{$issue->kendala}\"",
                'tautan' => "/issues?id={$issue->id}",
            ]);
        }

        return response()->json([
            'message' => 'Kendala berhasil diperbarui.',
            'data' => new WeeklyIssueResource($issue),
        ]);
    }

    /**
     * Perbarui status kendala (terbuka, diproses, selesai).
     * Kendala selesai terkunci: hanya admin atau ketua tim yang dapat mengubah/membuka kembali.
     * PATCH /api/issues/{id}/status
     */
    public function updateStatus(UpdateWeeklyIssueStatusRequest $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $issue = WeeklyIssue::with(['team'])->findOrFail($id);

        $adminOrKetua = $this->isAdminOrKetua($currentUser, $issue->team_id);

        if ($issue->status === StatusKendala::SELESAI) {
            if (! $adminOrKetua) {
                return response()->json([
                    'message' => 'PIC tidak dapat membuka kembali kendala yang sudah selesai. Hanya Admin atau Ketua Tim yang dapat mengubah status.',
                ], 422);
            }
        } else {
            // Hanya admin, ketua tim terkait, atau PIC yang dapat mengubah status kendala.
            $isAuthorized = $adminOrKetua || $issue->pic_id === $currentUser->id;

            if (! $isAuthorized) {
                return response()->json(['message' => 'Hanya Admin, Ketua Tim, atau PIC yang berhak memperbarui status kendala.'], 403);
            }
        }

        $oldData = $issue->toArray();
        $data = $request->validated();

        $updatePayload = ['status' => $data['status']];
        if (isset($data['solusi'])) {
            $updatePayload['solusi'] = $data['solusi'];
        }
        if (isset($data['tindak_lanjut'])) {
            $updatePayload['tindak_lanjut'] = $data['tindak_lanjut'];
        }
        if (isset($data['catatan_penutupan'])) {
            $updatePayload['catatan_penutupan'] = $data['catatan_penutupan'];
        }

        $isClosing = $data['status'] === StatusKendala::SELESAI || $data['status'] === StatusKendala::SELESAI->value;
        if ($isClosing) {
            $updatePayload['ditutup_pada'] = Carbon::now();
        } else {
            $updatePayload['ditutup_pada'] = null;
        }

        $issue->update($updatePayload);
        $issue->load(['activity', 'team', 'pelapor', 'pic']);

        AuditLog::create([
            'user_id' => $currentUser->id,
            'aksi' => 'update_status',
            'objek' => 'WeeklyIssue',
            'objek_id' => $issue->id,
            'sebelum' => $oldData,
            'sesudah' => $issue->toArray(),
        ]);

        // Notifikasi ke pelapor, PIC, dan ketua tim terkait (tanpa duplikat dan tanpa aktor).
        $catatan = mb_substr(trim((string) ($data['catatan_penutupan'] ?? $data['tindak_lanjut'] ?? '')), 0, 120);
        $statusLabel = $issue->status instanceof StatusKendala ? $issue->status->label() : (string) $issue->status;
        $pesan = "Status kendala \"{$issue->kendala}\" diubah menjadi {$statusLabel}.";

        if ($catatan !== '') {
            $pesan .= " Catatan: {$catatan}";
        }

        $this->notifyIssueParticipants($issue, $currentUser, 'kendala_status', $pesan);

        return response()->json([
            'message' => 'Status kendala berhasil diperbarui.',
            'data' => new WeeklyIssueResource($issue),
        ]);
    }

    /**
     * Hapus kendala (Soft Delete) - Admin atau Ketua Tim
     * DELETE /api/issues/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $issue = WeeklyIssue::findOrFail($id);

        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($role !== 'admin' && ! $currentUser->isKetuaOf($issue->team_id)) {
            return response()->json(['message' => 'Anda tidak memiliki wewenang untuk menghapus kendala ini.'], 403);
        }

        $oldData = $issue->toArray();
        $issue->delete();

        AuditLog::create([
            'user_id' => $currentUser->id,
            'aksi' => 'delete',
            'objek' => 'WeeklyIssue',
            'objek_id' => $issue->id,
            'sebelum' => $oldData,
            'sesudah' => null,
        ]);

        return response()->json([
            'message' => 'Kendala mingguan berhasil dihapus.',
        ]);
    }

    /**
     * Apakah aktor adalah admin atau ketua tim dari tim terkait.
     */
    private function isAdminOrKetua(User $user, int $teamId): bool
    {
        $role = $user->peran instanceof \BackedEnum ? $user->peran->value : (string) $user->peran;

        return $role === 'admin' || $user->isKetuaOf($teamId);
    }

    /**
     * Kirim notifikasi ke pelapor, PIC, dan ketua tim dari kendala (tanpa duplikat dan tanpa aktor).
     */
    private function notifyIssueParticipants(WeeklyIssue $issue, User $actor, string $tipe, string $pesan): void
    {
        $recipients = array_unique(array_filter([
            $issue->pelapor_id,
            $issue->pic_id,
            $issue->team?->ketua_id,
        ]));

        foreach ($recipients as $recipientId) {
            if ((int) $recipientId === (int) $actor->id) {
                continue;
            }

            Notification::create([
                'user_id' => (int) $recipientId,
                'tipe' => $tipe,
                'pesan' => $pesan,
                'tautan' => "/issues?id={$issue->id}",
            ]);
        }
    }

    /**
     * Susun riwayat status kendala dari audit log.
     *
     * @return array<int, array<string, mixed>>
     */
    private function issueHistory(int $issueId): array
    {
        $logs = AuditLog::with('user')
            ->where('objek', 'WeeklyIssue')
            ->where('objek_id', $issueId)
            ->orderBy('id', 'asc')
            ->get();

        return $logs->map(function (AuditLog $log) {
            $before = is_array($log->sebelum) ? $log->sebelum : [];
            $after = is_array($log->sesudah) ? $log->sesudah : [];

            return [
                'id' => $log->id,
                'waktu' => $log->created_at?->toIso8601String(),
                'aktor' => $log->user?->nama ?? 'Sistem',
                'aksi' => $log->aksi,
                'status_lama' => $before['status'] ?? null,
                'status_baru' => $after['status'] ?? null,
                'catatan' => $after['catatan_penutupan'] ?? null,
            ];
        })->values()->all();
    }
}
