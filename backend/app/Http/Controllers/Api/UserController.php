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
use App\Services\AuditLogger;
use App\Services\UserSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UserSessionService $userSessions,
    ) {}

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

            $this->auditLogger->log(
                'create',
                'User',
                $user->id,
                null,
                $this->auditLogger->userSnapshot($user->fresh())
            );

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

        $before = $this->auditLogger->userSnapshot($user);

        $after = DB::transaction(function () use ($user, $validated, $request, $before) {
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

            $after = $this->auditLogger->userSnapshot($user->fresh());

            $this->auditLogger->log('update', 'User', $user->id, $before, $after);

            return $after;
        });

        if ($before['aktif'] && ! $after['aktif']) {
            $this->userSessions->invalidate($user->id);
        }

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

        $ledTeams = $user->ledTeams()->get();
        $before = $this->auditLogger->userSnapshot($user);

        DB::transaction(function () use ($user, $ledTeams, $before) {
            // Jika pengguna menjadi ketua tim, kosongkan ketua_id pada tim yang dipimpin.
            foreach ($ledTeams as $team) {
                $teamBefore = $this->auditLogger->teamSnapshot($team);
                $team->update(['ketua_id' => null]);

                $this->auditLogger->log(
                    'update_ketua',
                    'Team',
                    $team->id,
                    $teamBefore,
                    $this->auditLogger->teamSnapshot($team->fresh())
                );
            }

            $this->userSessions->invalidate($user->id);

            // Lakukan soft delete
            $user->delete();

            $this->auditLogger->log('delete', 'User', $user->id, $before, null);
        });

        $message = 'Pengguna berhasil dinonaktifkan/dihapus.';

        if ($ledTeams->isNotEmpty()) {
            $message .= ' Ketua tim dikosongkan.';
        }

        return response()->json([
            'message' => $message,
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

        $wasActive = (bool) $user->aktif;
        $before = $this->auditLogger->userSnapshot($user);

        $user->update(['aktif' => $aktif]);

        if ($wasActive && ! $aktif) {
            $this->userSessions->invalidate($user->id);
        }

        if ($wasActive !== $aktif) {
            $this->auditLogger->log(
                $aktif ? 'activate' : 'deactivate',
                'User',
                $user->id,
                $before,
                $this->auditLogger->userSnapshot($user->fresh())
            );
        }

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

        $generated = ! $request->filled('password');
        $newPassword = $generated
            ? $this->generateTemporaryPassword()
            : $request->string('password')->toString();

        $before = $this->auditLogger->userSnapshot($user);

        DB::transaction(function () use ($user, $newPassword, $before) {
            $user->update([
                'password' => Hash::make($newPassword),
                'must_change_password' => true,
            ]);

            $this->userSessions->invalidate($user->id);

            $this->auditLogger->log(
                'reset_password',
                'User',
                $user->id,
                $before,
                $this->auditLogger->userSnapshot($user->fresh())
            );
        });

        $response = [
            'message' => 'Kata sandi pengguna berhasil direset. Pengguna wajib mengganti kata sandi pada login berikutnya.',
            'user' => new UserResource($user->fresh()),
        ];

        if ($generated) {
            $response['temporary_password'] = $newPassword;
        }

        return response()->json($response);
    }

    /**
     * Buat kata sandi sementara 12 karakter tanpa huruf atau angka ambigu.
     */
    private function generateTemporaryPassword(int $length = 12): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all = $letters.$digits;

        $characters = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($index = count($characters); $index < $length; $index++) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapWith = random_int(0, $index);
            [$characters[$index], $characters[$swapWith]] = [$characters[$swapWith], $characters[$index]];
        }

        return implode('', $characters);
    }
}
