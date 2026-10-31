<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi milik pengguna yang sedang login.
     * GET /api/notifications
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20);

        return NotificationResource::collection($notifications);
    }

    /**
     * Hitung notifikasi belum dibaca.
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = Notification::where('user_id', $user->id)->unread()->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Tandai satu notifikasi telah dibaca.
     * PATCH /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        if (! $notification->dibaca_pada) {
            $notification->update(['dibaca_pada' => Carbon::now()]);
        }

        return response()->json([
            'message' => 'Notifikasi telah ditandai dibaca.',
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * Tandai seluruh notifikasi pengguna telah dibaca.
     * POST /api/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        Notification::where('user_id', $user->id)
            ->unread()
            ->update(['dibaca_pada' => Carbon::now()]);

        return response()->json([
            'message' => 'Seluruh notifikasi telah ditandai dibaca.',
        ]);
    }
}
