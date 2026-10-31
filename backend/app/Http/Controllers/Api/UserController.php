<?php

namespace App\Http\Controllers\Api;

use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Menampilkan daftar pengguna dengan filter dan pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with('teams');

        // Filter pencarian (nama, email, NIP)
        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        // Filter peran
        if ($request->filled('peran')) {
            $query->where('peran', $request->peran);
        }

        // Filter status aktif
        if ($request->has('aktif')) {
            $aktif = filter_var($request->aktif, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($aktif !== null) {
                $query->where('aktif', $aktif);
            }
        }

        // Filter berdasarkan tim kerja
        if ($request->filled('team_id')) {
            $teamId = $request->integer('team_id');
            $query->whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $users = $query->orderBy('nama')->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * Membuat akun pengguna baru.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated, $request) {
            $initialPassword = $request->filled('password')
                ? $request->string('password')->toString()
                : 'password';

            $user = User::create([
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'peran' => $validated['peran'],
                'nip' => $validated['nip'] ?? null,
                'jabatan' => $validated['jabatan'] ?? null,
                'password' => Hash::make($initialPassword),
                'aktif' => $validated['aktif'] ?? true,
                'must_change_password' => true,
            ]);

            if (! empty($validated['team_ids'])) {
                $user->teams()->sync($validated['team_ids']);
            }

            return $user;
        });

        return (new UserResource($user->load('teams')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Menampilkan detail satu pengguna.
     */
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['teams', 'ledTeams']));
    }

    /**
     * Memperbarui data pengguna.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse|UserResource
    {
        $this->authorize('update', $user);

        $currentUser = $request->user();
        $validated = $request->validated();

        // Mencegah admin menonaktifkan akun sendiri
        if ($currentUser && $currentUser->id === $user->id && isset($validated['aktif']) && ! $validated['aktif']) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        // Mencegah admin mengubah perannya sendiri menjadi selain admin
        if ($currentUser && $currentUser->id === $user->id && isset($validated['peran']) && $validated['peran'] !== PeranPengguna::ADMIN->value) {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah peran akun Anda sendiri.',
            ], 422);
        }

        DB::transaction(function () use ($user, $validated, $request) {
            $updateData = [
                'nama' => $validated['nama'] ?? $user->nama,
                'email' => $validated['email'] ?? $user->email,
                'peran' => $validated['peran'] ?? $user->peran,
                'nip' => array_key_exists('nip', $validated) ? $validated['nip'] : $user->nip,
                'jabatan' => array_key_exists('jabatan', $validated) ? $validated['jabatan'] : $user->jabatan,
                'aktif' => array_key_exists('aktif', $validated) ? $validated['aktif'] : $user->aktif,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->string('password')->toString());
            }

            $user->update($updateData);

            if (array_key_exists('team_ids', $validated)) {
                $user->teams()->sync($validated['team_ids'] ?? []);
            }
        });

        return new UserResource($user->fresh()->load(['teams', 'ledTeams']));
    }

    /**
     * Menghapus (soft delete) akun pengguna.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $currentUser = $request->user();

        // Mencegah admin menghapus akun sendiri
        if ($currentUser && $currentUser->id === $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            // Jika pengguna menjadi ketua tim, kosongkan ketua_id pada tim yang dipimpin
            $user->ledTeams()->update(['ketua_id' => null]);

            // Lakukan soft delete
            $user->delete();
        });

        return response()->json([
            'message' => 'Pengguna berhasil dinonaktifkan/dihapus.',
        ]);
    }

    /**
     * Memperbarui status aktif/nonaktif pengguna.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $currentUser = $request->user();
        $aktif = $request->boolean('aktif');

        if ($currentUser && $currentUser->id === $user->id && ! $aktif) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        $user->update(['aktif' => $aktif]);

        return response()->json([
            'message' => 'Status pengguna berhasil diperbarui.',
            'user' => new UserResource($user->fresh()->load('teams')),
        ]);
    }

    /**
     * Mereset kata sandi pengguna secara manual oleh admin.
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $newPassword = $request->filled('password')
            ? $request->string('password')->toString()
            : 'password';

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => 'Kata sandi pengguna berhasil direset. Pengguna wajib mengganti kata sandi pada login berikutnya.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
