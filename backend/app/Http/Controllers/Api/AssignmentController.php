<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAssignmentRequest;
use App\Http\Requests\PreviewAssignmentBatchRequest;
use App\Http\Requests\PreviewAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Notification;
use App\Models\User;
use App\Services\WorkloadReminderService;
use App\Services\WorkloadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function __construct(
        protected WorkloadReminderService $workloadReminder
    ) {}

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

        $userIdsForReminder = [(int) $validated['user_id']];
        $monthsForReminder = $this->workloadReminder->monthsBetween(
            $this->effectiveAssignmentStart($activity, $validated['mulai'] ?? null),
            $this->effectiveAssignmentEnd($activity, $validated['selesai'] ?? null)
        );
        $beforeWarnings = $this->workloadReminder->collectWarnings($userIdsForReminder, $monthsForReminder);

        $assignment = DB::transaction(function () use ($validated, $activity, $userIdsForReminder, $monthsForReminder, $beforeWarnings) {
            $assignment = $activity->assignments()->create([
                'user_id' => $validated['user_id'],
                'peran' => $validated['peran'],
                'volume' => $validated['volume'],
                'satuan' => $validated['satuan'],
                'mulai' => $validated['mulai'] ?? null,
                'selesai' => $validated['selesai'] ?? null,
            ]);

            // Catat log audit (PRD Bagian 8.4)

            // Kirim notifikasi ke pegawai yang ditugaskan (PRD Bagian 8.6)
            Notification::create([
                'user_id' => $assignment->user_id,
                'tipe' => 'penugasan_baru',
                'pesan' => "Anda ditugaskan pada kegiatan '{$activity->nama}' sebagai {$assignment->peran} ({$assignment->volume} {$assignment->satuan}).",
                'tautan' => "/kegiatan/{$activity->id}",
            ]);

            $this->workloadReminder->notifyNewWarnings($userIdsForReminder, $monthsForReminder, $beforeWarnings);

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

        $beforeUserId = (int) $assignment->getOriginal('user_id');
        $newUserId = (int) ($validated['user_id'] ?? $assignment->user_id);
        $userIdsForReminder = array_values(array_unique([$beforeUserId, $newUserId]));

        $oldStart = $this->effectiveAssignmentStart($assignment->activity, $assignment->mulai?->toDateString());
        $oldEnd = $this->effectiveAssignmentEnd($assignment->activity, $assignment->selesai?->toDateString());
        $newStart = $this->effectiveUpdateStart($assignment, $validated);
        $newEnd = $this->effectiveUpdateEnd($assignment, $validated);

        $minStart = Carbon::parse($oldStart)->lte(Carbon::parse($newStart)) ? $oldStart : $newStart;
        $maxEnd = Carbon::parse($oldEnd)->gte(Carbon::parse($newEnd)) ? $oldEnd : $newEnd;

        $monthsForReminder = $this->workloadReminder->monthsBetween($minStart, $maxEnd);
        $beforeWarnings = $this->workloadReminder->collectWarnings($userIdsForReminder, $monthsForReminder);

        $updated = DB::transaction(function () use ($assignment, $validated, $userIdsForReminder, $monthsForReminder, $beforeWarnings) {
            $sebelum = $assignment->toArray();

            $assignment->update($validated);
            $sesudah = $assignment->fresh()->toArray();

            // Catat log audit

            // Kirim notifikasi ke pegawai yang ditugaskan (PRD Bagian 8.6)
            Notification::create([
                'user_id' => $assignment->user_id,
                'tipe' => 'penugasan_diubah',
                'pesan' => "Penugasan Anda pada kegiatan '{$assignment->activity->nama}' telah diperbarui.",
                'tautan' => "/kegiatan/{$assignment->activity_id}",
            ]);

            $this->workloadReminder->notifyNewWarnings($userIdsForReminder, $monthsForReminder, $beforeWarnings);

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

        $userIdsForReminder = [(int) $assignment->user_id];
        $monthsForReminder = $this->workloadReminder->monthsBetween(
            $this->effectiveAssignmentStart($assignment->activity, $assignment->mulai?->toDateString()),
            $this->effectiveAssignmentEnd($assignment->activity, $assignment->selesai?->toDateString())
        );
        $beforeWarnings = $this->workloadReminder->collectWarnings($userIdsForReminder, $monthsForReminder);

        DB::transaction(function () use ($assignment, $userIdsForReminder, $monthsForReminder, $beforeWarnings) {
            $sebelum = $assignment->toArray();
            $activityNama = $assignment->activity?->nama ?? 'Kegiatan';
            $userId = $assignment->user_id;

            $assignment->delete();

            // Catat log audit

            // Kirim notifikasi ke pegawai yang penugasannya dihapus (PRD Bagian 8.6)
            Notification::create([
                'user_id' => $userId,
                'tipe' => 'penugasan_dihapus',
                'pesan' => "Penugasan Anda pada kegiatan '{$activityNama}' telah dihapus.",
                'tautan' => '/kegiatan',
            ]);

            $this->workloadReminder->notifyNewWarnings($userIdsForReminder, $monthsForReminder, $beforeWarnings);
        });

        return response()->json([
            'message' => 'Penugasan berhasil dihapus.',
        ]);
    }

    /**
     * Menampilkan daftar pegawai aktif yang dapat ditugaskan pada suatu kegiatan.
     * Sumbernya seluruh pegawai pada sistem, bukan hanya anggota tim kegiatan tersebut.
     */
    public function candidates(Activity $activity): JsonResponse
    {
        $this->authorize('create', [Assignment::class, $activity]);

        $users = User::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'nip', 'jabatan']);

        return response()->json(['data' => $users]);
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

    /**
     * Pratinjau batch dampak beberapa penugasan dari kegiatan yang belum disimpan (Fase 6).
     * POST /api/assignments/preview-batch
     */
    public function previewBatch(PreviewAssignmentBatchRequest $request, WorkloadService $workloadService): JsonResponse
    {
        $validated = $request->validated();
        $validated['selesai'] = $validated['selesai'] ?? $validated['mulai'];

        $result = $workloadService->previewBatchImpact($validated);

        return response()->json([
            'message' => 'Pratinjau batch dampak penugasan berhasil dihitung.',
            'data' => $result,
        ]);
    }

    private function effectiveAssignmentStart(Activity $activity, ?string $mulai): string
    {
        return $mulai !== null && $mulai !== ''
            ? Carbon::parse($mulai)->toDateString()
            : Carbon::parse($activity->mulai)->toDateString();
    }

    private function effectiveAssignmentEnd(Activity $activity, ?string $selesai): string
    {
        return $selesai !== null && $selesai !== ''
            ? Carbon::parse($selesai)->toDateString()
            : Carbon::parse($activity->selesai)->toDateString();
    }

    private function effectiveUpdateStart(Assignment $assignment, array $validated): string
    {
        if (array_key_exists('mulai', $validated)) {
            return $this->effectiveAssignmentStart($assignment->activity, $validated['mulai']);
        }

        return $this->effectiveAssignmentStart($assignment->activity, $assignment->mulai?->toDateString());
    }

    private function effectiveUpdateEnd(Assignment $assignment, array $validated): string
    {
        if (array_key_exists('selesai', $validated)) {
            return $this->effectiveAssignmentEnd($assignment->activity, $validated['selesai']);
        }

        return $this->effectiveAssignmentEnd($assignment->activity, $assignment->selesai?->toDateString());
    }
}
