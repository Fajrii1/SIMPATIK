<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkTypeRequest;
use App\Http\Requests\UpdateWorkTypeRequest;
use App\Http\Resources\WorkTypeResource;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkTypeController extends Controller
{
    /**
     * Daftar norma waktu (khusus admin).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin() ?? false, 403);

        $query = WorkType::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('satuan', 'like', "%{$search}%");
            });
        }

        if ($request->has('aktif') && $request->input('aktif') !== '') {
            $query->where('aktif', filter_var($request->input('aktif'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true);
        }

        $perPage = min(max($request->integer('per_page', 10), 1), 100);
        $paginator = $query->orderBy('nama')->paginate($perPage);

        return response()->json([
            'data' => WorkTypeResource::collection($paginator->items())->resolve(),
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
     * Opsi jenis pekerjaan aktif untuk form (admin dan ketua tim).
     */
    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkType::class);

        $items = WorkType::query()->aktif()->orderBy('nama')->get();

        return response()->json([
            'data' => WorkTypeResource::collection($items)->resolve(),
        ]);
    }

    /**
     * Menambah jenis pekerjaan baru.
     */
    public function store(StoreWorkTypeRequest $request): JsonResponse
    {
        $this->authorize('create', WorkType::class);

        $workType = WorkType::create([
            'nama' => $request->string('nama')->trim()->toString(),
            'satuan' => $request->string('satuan')->trim()->toString(),
            'norma_menit' => $request->integer('norma_menit'),
            'jenis_norma' => $request->string('jenis_norma')->toString(),
            'sumber' => $request->filled('sumber') ? $request->string('sumber')->trim()->toString() : null,
            'aktif' => $request->has('aktif') ? $request->boolean('aktif') : true,
        ]);

        return (new WorkTypeResource($workType))->response()->setStatusCode(201);
    }

    /**
     * Mengubah jenis pekerjaan. Perubahan norma tidak mengubah penugasan lama (snapshot).
     */
    public function update(UpdateWorkTypeRequest $request, WorkType $workType): WorkTypeResource
    {
        $this->authorize('update', $workType);

        $validated = $request->validated();

        if (array_key_exists('nama', $validated)) {
            $validated['nama'] = trim((string) $validated['nama']);
        }

        if (array_key_exists('satuan', $validated)) {
            $validated['satuan'] = trim((string) $validated['satuan']);
        }

        if (array_key_exists('sumber', $validated) && $validated['sumber'] !== null) {
            $validated['sumber'] = trim((string) $validated['sumber']);
        }

        $workType->update($validated);

        return new WorkTypeResource($workType->fresh());
    }

    /**
     * Menghapus jenis pekerjaan yang belum pernah dipakai.
     */
    public function destroy(WorkType $workType): JsonResponse
    {
        $this->authorize('delete', $workType);

        if ($workType->assignments()->exists()) {
            return response()->json([
                'message' => 'Jenis pekerjaan sudah dipakai pada penugasan dan tidak boleh dihapus. Nonaktifkan saja agar tidak muncul di form.',
            ], 422);
        }

        $workType->delete();

        return response()->json([
            'message' => 'Jenis pekerjaan berhasil dihapus.',
        ]);
    }
}
