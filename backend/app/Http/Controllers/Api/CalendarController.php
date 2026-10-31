<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Mengambil daftar kegiatan untuk tampilan kalender lintas tim (PRD Bagian 12.2 S3 & Bagian 14).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query()->with(['team', 'assignments.user']);

        // Filter rentang tanggal kalender (default ke 1 bulan berjalan jika tidak disediakan)
        $start = $request->filled('start')
            ? Carbon::parse($request->input('start'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $end = $request->filled('end')
            ? Carbon::parse($request->input('end'))->endOfDay()
            : Carbon::now()->endOfMonth();

        // Cari kegiatan yang beririsan dengan periode kalender
        $query->where('mulai', '<=', $end)
            ->where('selesai', '>=', $start);

        // Filter tim
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        // Filter penugasan pegawai
        if ($request->filled('user_id')) {
            $userId = $request->integer('user_id');
            $query->whereHas('assignments', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        // Filter status dinamis
        if ($request->filled('status')) {
            $status = strtolower($request->string('status')->trim()->toString());
            $today = Carbon::today();

            match ($status) {
                'selesai' => $query->where('selesai_manual', true),
                'belum mulai' => $query->where('selesai_manual', false)->where('mulai', '>', $today),
                'berjalan' => $query->where('selesai_manual', false)->where('mulai', '<=', $today)->where('selesai', '>=', $today),
                'terlambat' => $query->where('selesai_manual', false)->where('selesai', '<', $today),
                default => null,
            };
        }

        $activities = $query->orderBy('mulai', 'asc')->get();

        // Format data sesuai standar FullCalendar
        $events = $activities->map(function (Activity $activity) {
            return [
                'id' => (string) $activity->id,
                'title' => $activity->nama,
                'start' => $activity->mulai?->format('Y-m-d'),
                'end' => $activity->selesai?->copy()->addDay()->format('Y-m-d'), // FullCalendar end date is exclusive for all-day events
                'allDay' => true,
                'extendedProps' => [
                    'activity_id' => $activity->id,
                    'team_id' => $activity->team_id,
                    'team_nama' => $activity->team?->nama,
                    'jenis' => $activity->jenis,
                    'bobot' => (float) $activity->bobot,
                    'kegiatan_besar' => (bool) $activity->kegiatan_besar,
                    'selesai_manual' => (bool) $activity->selesai_manual,
                    'status' => $activity->status,
                    'assignments_count' => $activity->assignments->count(),
                    'assigned_users' => $activity->assignments->map(fn ($a) => [
                        'user_id' => $a->user_id,
                        'nama' => $a->user?->nama,
                        'peran' => $a->peran,
                    ]),
                ],
            ];
        });

        return response()->json([
            'events' => $events,
            'meta' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'total_events' => $events->count(),
            ],
        ]);
    }
}
