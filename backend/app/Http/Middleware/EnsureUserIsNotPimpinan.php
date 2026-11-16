<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses pimpinan hanya pada dashboard dan monitoring beban kerja.
 *
 * Pimpinan tidak mengelola kegiatan, penugasan, kendala, maupun ekspor.
 */
class EnsureUserIsNotPimpinan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->peran instanceof \BackedEnum ? $user->peran->value : (string) $user?->peran;

        if ($role === 'pimpinan') {
            return response()->json([
                'message' => 'Pimpinan hanya dapat mengakses dashboard dan monitoring beban kerja.',
            ], 403);
        }

        return $next($request);
    }
}
