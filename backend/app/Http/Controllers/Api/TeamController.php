<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddTeamMemberRequest;
use App\Http\Requests\CreateTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    /**
     * Menampilkan daftar tim kerja.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Team::class);

        $query = Team::query()->with(['ketua', 'members']);

        // Filter pencarian nama tim
        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where('nama', 'like', "%{$search}%");
        }

        // Filter status aktif
        if ($request->has('aktif')) {
            $aktif = filter_var($request->aktif, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($aktif !== null) {
                $query->where('aktif', $aktif);
            }
        }

        // Filter ketua tim
        if ($request->filled('ketua_id')) {
            $query->where('ketua_id', $request->integer('ketua_id'));
        }

        $teams = $query->orderBy('nama')->get();

        return TeamResource::collection($teams);
    }

    /**
     * Membuat tim kerja baru.
     */
    public function store(CreateTeamRequest $request): JsonResponse
    {
        $this->authorize('create', Team::class);

        $validated = $request->validated();

        if (! empty($validated['ketua_id'])) {
            $ketua = User::find($validated['ketua_id']);
            if (! $ketua || ! $ketua->aktif) {
                return response()->json([
                    'message' => 'Ketua tim yang dipilih harus merupakan pengguna yang aktif.',
                ], 422);
            }
        }

        $team = DB::transaction(function () use ($validated) {
            $team = Team::create([
                'nama' => $validated['nama'],
                'ketua_id' => $validated['ketua_id'] ?? null,
                'aktif' => $validated['aktif'] ?? true,
            ]);

            $memberIds = $validated['member_ids'] ?? [];
            if (! empty($validated['ketua_id']) && ! in_array($validated['ketua_id'], $memberIds)) {
                $memberIds[] = $validated['ketua_id'];
            }

            if (! empty($memberIds)) {
                $team->members()->sync($memberIds);
            }

            return $team;
        });

        return (new TeamResource($team->load(['ketua', 'members'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Menampilkan detail satu tim kerja beserta anggotanya.
     */
    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        return new TeamResource($team->load(['ketua', 'members']));
    }

    /**
     * Memperbarui informasi tim kerja dan ketuanya.
     */
    public function update(UpdateTeamRequest $request, Team $team): TeamResource|JsonResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validated();

        if (array_key_exists('ketua_id', $validated) && ! empty($validated['ketua_id'])) {
            $ketua = User::find($validated['ketua_id']);
            if (! $ketua || ! $ketua->aktif) {
                return response()->json([
                    'message' => 'Ketua tim yang dipilih harus merupakan pengguna yang aktif.',
                ], 422);
            }
        }

        DB::transaction(function () use ($team, $validated) {
            $updateData = [
                'nama' => $validated['nama'] ?? $team->nama,
                'aktif' => array_key_exists('aktif', $validated) ? $validated['aktif'] : $team->aktif,
            ];

            if (array_key_exists('ketua_id', $validated)) {
                $updateData['ketua_id'] = $validated['ketua_id'];
            }

            $team->update($updateData);

            if (array_key_exists('member_ids', $validated)) {
                $memberIds = $validated['member_ids'] ?? [];
                if (! empty($team->ketua_id) && ! in_array($team->ketua_id, $memberIds)) {
                    $memberIds[] = $team->ketua_id;
                }
                $team->members()->sync($memberIds);
            } elseif (! empty($team->ketua_id) && ! $team->members()->where('user_id', $team->ketua_id)->exists()) {
                $team->members()->attach($team->ketua_id);
            }
        });

        return new TeamResource($team->fresh()->load(['ketua', 'members']));
    }

    /**
     * Menghapus (soft delete) tim kerja.
     */
    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        $team->delete();

        return response()->json([
            'message' => 'Tim berhasil dihapus.',
        ]);
    }

    /**
     * Menampilkan daftar anggota dari suatu tim kerja.
     */
    public function members(Team $team): AnonymousResourceCollection
    {
        $this->authorize('view', $team);

        return UserResource::collection($team->members()->orderBy('nama')->get());
    }

    /**
     * Menambahkan seorang pengguna menjadi anggota tim.
     */
    public function addMember(AddTeamMemberRequest $request, Team $team): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        $userId = $request->integer('user_id');
        $user = User::find($userId);

        if (! $user || ! $user->aktif) {
            return response()->json([
                'message' => 'Pengguna tidak aktif tidak dapat ditambahkan ke dalam tim kerja.',
            ], 422);
        }

        if ($team->members()->where('user_id', $userId)->exists()) {
            return response()->json([
                'message' => 'Pengguna sudah terdaftar sebagai anggota pada tim ini.',
            ], 422);
        }

        $team->members()->attach($userId);

        return response()->json([
            'message' => 'Anggota berhasil ditambahkan ke dalam tim.',
            'members' => UserResource::collection($team->fresh()->members),
        ], 201);
    }

    /**
     * Menghapus seorang pengguna dari keanggotaan tim.
     */
    public function removeMember(Team $team, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        if (! $team->members()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Pengguna bukan merupakan anggota pada tim ini.',
            ], 422);
        }

        DB::transaction(function () use ($team, $user) {
            // Jika anggota yang dihapus adalah ketua tim, kosongkan ketua_id
            if ($team->ketua_id === $user->id) {
                $team->update(['ketua_id' => null]);
            }

            $team->members()->detach($user->id);
        });

        return response()->json([
            'message' => 'Anggota berhasil dihapus dari tim kerja.',
        ]);
    }
}
