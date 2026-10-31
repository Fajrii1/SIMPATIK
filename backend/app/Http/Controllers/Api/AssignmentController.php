<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAssignmentRequest;
use App\Http\Requests\PreviewAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Services\WorkloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    /**
     * Menampilkan daftar penugasan pada suatu kegiatan statistik (PRD Bagian 14).
     */
    public function indexForActivity(Activity $activity): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Assignment::class);

        $assignments = $activity->assignments()
            ->with(['user', 'activity'])
            ->orderBy('mulai', 'asc')
            ->get();

        return AssignmentResource::collection($assignments);
    }

    /**
     * Menambahkan penugasan baru pada suatu kegiatan statistik (PRD Bagian 14).
     */
    public function storeForActivity(CreateAssignmentRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('create', [Assignment::class, $activity]);

        $validated = $request->validated();

        $assignment = DB::transaction(function () use ($validated, $activity) {
            $assignment = $activity->assignments()->create([
                'user_id' => $validated['user_id'],
                'peran' => $validated['peran'],
                'volume' => $validated['volume'],
                'satuan' => $validated['satuan'],
                'mulai' => $validated['mulai'] ?? null,
                'selesai' => $validated['selesai'] ?? null,
            ]);

            // Catat log audit (PRD Bagian 8.4)
            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'create',
                'objek' => 'Assignment',
                'objek_id' => $assignment->id,
                'sebelum' => null,
                'sesudah' => $assignment->toArray(),
            ]);

            // Kirim notifikasi ke pegawai yang ditugaskan (PRD Bagian 8.6)
            Notification::create([
                'user_id' => $assignment->user_id,
                'tipe' => 'penugasan_baru',
                'pesan' => "Anda ditugaskan pada kegiatan '{$activity->nama}' sebagai {$assignment->peran} ({$assignment->volume} {$assignment->satuan}).",
                'tautan' => "/kegiatan/{$activity->id}",
            ]);

            return $assignment;
        });

        return (new AssignmentResource($assignment->load(['user', 'activity'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Menampilkan detail satu penugasan.
     */
    public function show(Assignment $assignment): AssignmentResource
    {
        $this->authorize('view', $assignment);

        return new AssignmentResource($assignment->load(['user', 'activity.team']));
    }

    /**
     * Memperbarui informasi penugasan pegawai.
     */
    public function update(UpdateAssignmentRequest $request, Assignment $assignment): AssignmentResource
    {
        $this->authorize('update', $assignment);

        $validated = $request->validated();

        $updated = DB::transaction(function () use ($assignment, $validated) {
            $sebelum = $assignment->toArray();

            $assignment->update($validated);
            $sesudah = $assignment->fresh()->toArray();

            // Catat log audit
            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'update',
                'objek' => 'Assignment',
                'objek_id' => $assignment->id,
                'sebelum' => $sebelum,
                'sesudah' => $sesudah,
            ]);

            // Kirim notifikasi ke pegawai yang ditugaskan (PRD Bagian 8.6)
            Notification::create([
                'user_id' => $assignment->user_id,
                'tipe' => 'penugasan_diubah',
                'pesan' => "Penugasan Anda pada kegiatan '{$assignment->activity->nama}' telah diperbarui.",
                'tautan' => "/kegiatan/{$assignment->activity_id}",
            ]);

            return $assignment;
        });

        return new AssignmentResource($updated->load(['user', 'activity']));
    }

    /**
     * Menghapus (soft delete) penugasan pegawai.
     */
    public function destroy(Assignment $assignment): JsonResponse
    {
        $this->authorize('delete', $assignment);

        DB::transaction(function () use ($assignment) {
            $sebelum = $assignment->toArray();
            $activityNama = $assignment->activity?->nama ?? 'Kegiatan';
            $userId = $assignment->user_id;

            $assignment->delete();

            // Catat log audit
            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => 'delete',
                'objek' => 'Assignment',
                'objek_id' => $assignment->id,
                'sebelum' => $sebelum,
                'sesudah' => null,
            ]);

            // Kirim notifikasi ke pegawai yang penugasannya dihapus (PRD Bagian 8.6)
            Notification::create([
                'user_id' => $userId,
                'tipe' => 'penugasan_dihapus',
                'pesan' => "Penugasan Anda pada kegiatan '{$activityNama}' telah dihapus.",
                'tautan' => '/kegiatan',
            ]);
        });

        return response()->json([
            'message' => 'Penugasan berhasil dihapus.',
        ]);
    }

    /**
     * Melakukan pratinjau (simulasi) dampak beban kerja penugasan sebelum disimpan (PRD Bagian 14).
     */
    public function preview(PreviewAssignmentRequest $request, WorkloadService $workloadService): JsonResponse
    {
        $activity = Activity::find($request->integer('activity_id'));
        $this->authorize('preview', [Assignment::class, $activity]);

        $result = $workloadService->previewAssignmentImpact($request->validated());

        return response()->json([
            'message' => 'Pratinjau dampak penugasan berhasil dihitung.',
            'data' => $result,
        ]);
    }
}
