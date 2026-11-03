<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\UserSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserSessionService $userSessions,
    ) {}

    /**
     * Autentikasi pengguna dan mulai sesi baru.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => new UserResource($request->user()->load(['teams', 'ledTeams'])),
        ]);
    }

    /**
     * Keluar dari sesi dan batalkan token sesi saat ini.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Berhasil keluar.',
        ]);
    }

    /**
     * Dapatkan data profil pengguna yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['teams', 'ledTeams']);

        return response()->json([
            'data' => new UserResource($user),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Ubah kata sandi pengguna dan nonaktifkan kewajiban ganti sandi.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
        ]);

        $currentSessionId = $request->hasSession()
            ? $request->session()->getId()
            : null;

        $this->userSessions->invalidate($user->id, $currentSessionId);

        return response()->json([
            'message' => 'Kata sandi berhasil diubah.',
            'user' => new UserResource($user->fresh()->load(['teams', 'ledTeams'])),
        ]);
    }
}
