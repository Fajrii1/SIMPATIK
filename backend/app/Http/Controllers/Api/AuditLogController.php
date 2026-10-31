<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    /**
     * Riwayat Audit Log (Khusus Admin).
     * GET /api/audit-logs?user_id=X&objek=Activity&aksi=update&limit=25
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $currentUser = $request->user();
        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($role !== 'admin') {
            return response()->json(['message' => 'Hanya Administrator yang memiliki akses ke Audit Log.'], 403);
        }

        $query = AuditLog::with('user');

        if ($request->has('user_id') && ! empty($request->query('user_id'))) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->has('objek') && ! empty($request->query('objek'))) {
            $query->where('objek', $request->query('objek'));
        }

        if ($request->has('aksi') && ! empty($request->query('aksi'))) {
            $query->where('aksi', $request->query('aksi'));
        }

        $logs = $query->orderByDesc('id')->paginate($request->query('per_page', 20));

        return AuditLogResource::collection($logs);
    }
}
