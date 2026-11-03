<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Services\WorkloadReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function __construct(
        protected WorkloadReminderService $workloadReminder
    ) {}

    /**
     * Menampilkan daftar kegiatan statistik dengan filter dan pagination (PRD Bagian 14).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::query()->with(['team', 'assignments.user']);

        // Filter pencarian nama / keterangan
        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        // Filter tim
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        // Filter jenis kegiatan
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->string('jenis')->toString());
        }

        // Filter kegiatan besar
        if ($request->has('kegiatan_besar')) {
            $kegiatanBesar = filter_var($request->kegiatan_besar, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($kegiatanBesar !== null) {
                $query->where('kegiatan_besar', $kegiatanBesar);
            }
        }

        // Filter rentang tanggal
        if ($request->filled('start') || $request->filled('mulai')) {
            $start = $request->input('start', $request->input('mulai'));
            $query->where('selesai', '>=', Carbon::parse($start)->startOfDay());
        }

        if ($request->filled('end') || $request->filled('selesai')) {
            $end = $request->input('end', $request->input('selesai'));
            $query->where('mulai', '<=', Carbon::parse($end)->endOfDay());
        }

        // Filter status dinamis (PRD Bagian 8.3)
        if ($request->filled('status')) {
            $status = strtolower($request->string('status')->trim()->toString());
            $today = Carbon::today();

            match ($status) {
                'selesai' => $query->where('selesai_manual', true),
                'belum mulai' => $query->where('selesai_manual', false)->where('mulai', '>', $today),
                'berjalan' => $query->where('selesai_manual', false)->where('mulai', '<=', $today)->where('selesai', '>=', $today),
                'terlambat' => $query->where('selesai_manual', false)->where('selesai', '<', $today),
                default => null,
            };
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $activities = $query->orderBy('mulai', 'asc')->paginate($perPage);

        return ActivityResource::collection($activities);
    }

    /**
     * Membuat kegiatan statistik baru (Admin atau Ketua Tim bersangkutan).
     */
    public function store(CreateActivityRequest $request): JsonResponse
    {
        $this->authorize('create', Activity::class);

        $validated = $request->validated();

        $userIdsForReminder = array_values(array_unique(array_map(
            'intval',
            array_column($validated['assignments'] ?? [], 'user_id')
        )));
        $monthsForReminder = $this->workloadReminder->monthsBetween($validated['mulai'], $validated['selesai']);
        $beforeWarnings = $this->workloadReminder->collectWarnings($userIdsForReminder, $monthsForReminder);

        $activity = DB::transaction(function () use ($validated, $userIdsForReminder, $monthsForReminder, $beforeWarnings) {
            $activity = Activity::create([
                'nama' => $validated['nama'],
                'jenis' => $validated['jenis'],
                'team_id' => $validated['team_id'],
                'mulai' => $validated['mulai'],
                'selesai' => $validated['selesai'],
                'bobot' => $validated['bobot'] ?? 1.00,
                'kegiatan_besar' => $validated['kegiatan_besar'] ?? false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            // Simpan penugasan awal secara atomik bersama kegiatan (PRD Bagian 14).
            foreach ($validated['assignments'] ?? [] as $assignmentData) {
                $assignment = $activity->assignments()->create([
                    'user_id' => $assignmentData['user_id'],
                    'peran' => $assignmentData['peran'],
                    'volume' => $assignmentData['volume'],
                    'satuan' => $assignmentData['satuan'],
                    'mulai' => $assignmentData['mulai'] ?? null,
                    'selesai' => $assignmentData['selesai'] ?? null,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'aksi' => 'create',
                    'objek' => 'Assignment',
                    'objek_id' => $assignment->id,
                    'sebelum' => null,
                    'sesudah' => $assignment->toArray(),
                ]);

                Notification::create([
                    'user_id' => $assignment->user_id,
                    'tipe' => 'penugasan_baru',
                    'pesan' => "Anda ditugaskan pada kegiatan '{$activity->nama}' sebagai {$assignment->peran} ({$assignment->volume} {$assignment->satuan}).",
                    'tautan' => "/kegiatan/{$activity->id}",
                ]);
            }

            // Catat ke audit_logs (PRD Bagian 8.4)
            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'create',
                'objek' => 'Activity',
                'objek_id' => $activity->id,
                'sebelum' => null,
                'sesudah' => $activity->toArray(),
            ]);

            $this->workloadReminder->notifyNewWarnings($userIdsForReminder, $monthsForReminder, $beforeWarnings);

            return $activity;
        });

        return (new ActivityResource($activity->load(['team', 'assignments.user'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Menampilkan detail satu kegiatan statistik.
     */
    public function show(Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);

        return new ActivityResource($activity->load(['team', 'assignments.user', 'weeklyIssues']));
    }

    /**
     * Memperbarui informasi kegiatan statistik.
     */
    public function update(UpdateActivityRequest $request, Activity $activity): ActivityResource
    {
        $this->authorize('update', $activity);

        $validated = $request->validated();

        $updated = DB::transaction(function () use ($activity, $validated) {
            $sebelum = $activity->toArray();
            $oldMulai = $activity->mulai?->format('Y-m-d');
            $oldSelesai = $activity->selesai?->format('Y-m-d');

            $activity->update($validated);
            $sesudah = $activity->fresh()->toArray();

            // Catat ke audit_logs
            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'update',
                'objek' => 'Activity',
                'objek_id' => $activity->id,
                'sebelum' => $sebelum,
                'sesudah' => $sesudah,
            ]);

            // Jika jadwal berubah, beri notifikasi ke pegawai yang ditugaskan (PRD Bagian 8.6)
            $newMulai = $activity->mulai?->format('Y-m-d');
            $newSelesai = $activity->selesai?->format('Y-m-d');

            if ($oldMulai !== $newMulai || $oldSelesai !== $newSelesai) {
                $assignedUserIds = $activity->assignments()->pluck('user_id')->unique();
                foreach ($assignedUserIds as $userId) {
                    Notification::create([
                        'user_id' => $userId,
                        'tipe' => 'jadwal_berubah',
                        'pesan' => "Jadwal kegiatan '{$activity->nama}' telah diperbarui menjadi {$activity->mulai->format('d/m/Y')} s.d. {$activity->selesai->format('d/m/Y')}.",
                        'tautan' => "/kegiatan/{$activity->id}",
                    ]);
                }
            }

            return $activity;
        });

        return new ActivityResource($updated->load(['team', 'assignments.user']));
    }

    /**
     * Menghapus (soft delete) kegiatan statistik.
     */
    public function destroy(Activity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        DB::transaction(function () use ($activity) {
            $sebelum = $activity->toArray();

            // Soft delete
            $activity->delete();

            // Catat ke audit_logs
            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'delete',
                'objek' => 'Activity',
                'objek_id' => $activity->id,
                'sebelum' => $sebelum,
                'sesudah' => null,
            ]);
        });

        return response()->json([
            'message' => 'Kegiatan berhasil dihapus.',
        ]);
    }

    /**
     * Menandai kegiatan selesai (PRD Bagian 8.3 & 14).
     */
    public function complete(Activity $activity): JsonResponse
    {
        $this->authorize('complete', $activity);

        $activity = DB::transaction(function () use ($activity) {
            $sebelum = $activity->toArray();

            $activity->update([
                'selesai_manual' => true,
                'selesai_pada' => Carbon::now(),
            ]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'complete',
                'objek' => 'Activity',
                'objek_id' => $activity->id,
                'sebelum' => $sebelum,
                'sesudah' => $activity->fresh()->toArray(),
            ]);

            return $activity;
        });

        return response()->json([
            'message' => 'Kegiatan berhasil ditandai selesai.',
            'data' => new ActivityResource($activity->fresh()->load(['team', 'assignments.user'])),
        ]);
    }

    /**
     * Membuka kembali kegiatan yang telah selesai (PRD Bagian 8.3 & 14).
     */
    public function reopen(Activity $activity): JsonResponse
    {
        $this->authorize('reopen', $activity);

        $activity = DB::transaction(function () use ($activity) {
            $sebelum = $activity->toArray();

            $activity->update([
                'selesai_manual' => false,
                'selesai_pada' => null,
            ]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'reopen',
                'objek' => 'Activity',
                'objek_id' => $activity->id,
                'sebelum' => $sebelum,
                'sesudah' => $activity->fresh()->toArray(),
            ]);

            return $activity;
        });

        return response()->json([
            'message' => 'Kegiatan berhasil dibuka kembali.',
            'data' => new ActivityResource($activity->fresh()->load(['team', 'assignments.user'])),
        ]);
    }
}
