<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\AssignmentVisibility;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    /**
     * Mengambil daftar kegiatan untuk tampilan kalender lintas tim (PRD Bagian 12.2 S3 & Bagian 14).
     *
     * Parameter yang didukung: start, end, team_id, user_id, status, jenis, search.
     * Format `end` pada event mengikuti aturan FullCalendar (eksklusif).
     */
    public function index(Request $request): JsonResponse
    {
        // Normalisasi status agar "Belum Mulai" dan "belum mulai" sama-sama diterima.
        if ($request->filled('status')) {
            $request->merge(['status' => strtolower(trim((string) $request->input('status')))]);
        }

        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', Rule::in(['selesai', 'belum mulai', 'berjalan', 'terlambat'])],
            'jenis' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        // Filter rentang tanggal kalender (default ke 1 bulan berjalan jika tidak disediakan)
        $start = isset($validated['start'])
            ? Carbon::parse($validated['start'])->startOfDay()
            : Carbon::now()->startOfMonth();

        $end = isset($validated['end'])
            ? Carbon::parse($validated['end'])->endOfDay()
            : Carbon::now()->endOfMonth();

        $query = Activity::query()->with(['team', 'assignments.user']);

        // Cari kegiatan yang beririsan dengan periode kalender
        $query->where('mulai', '<=', $end)
            ->where('selesai', '>=', $start);

        // Filter tim
        if (! empty($validated['team_id'])) {
            $query->where('team_id', $validated['team_id']);
        }

        // Filter penugasan pegawai
        if (! empty($validated['user_id'])) {
            $userId = (int) $validated['user_id'];
            $rangeEnd = $end->format('Y-m-d');
            $rangeStart = $start->format('Y-m-d');

            $query->whereHas('assignments', function ($q) use ($userId, $rangeStart, $rangeEnd) {
                // Periode efektif penugasan: bila kosong, memakai periode kegiatan
                // (kegiatan sendiri sudah difilter beririsan dengan rentang kalender).
                $q->where('user_id', $userId)
                    ->where(function ($startClause) use ($rangeEnd) {
                        $startClause->whereNull('mulai')->orWhere('mulai', '<=', $rangeEnd);
                    })
                    ->where(function ($endClause) use ($rangeStart) {
                        $endClause->whereNull('selesai')->orWhere('selesai', '>=', $rangeStart);
                    });
            });
        }

        // Filter jenis kegiatan
        if (! empty($validated['jenis'])) {
            $query->where('jenis', $validated['jenis']);
        }

        // Pencarian nama kegiatan
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where('nama', 'like', "%{$search}%");
        }

        // Filter status dinamis
        if (! empty($validated['status'])) {
            $status = strtolower(trim($validated['status']));
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

        $viewer = $request->user();

        // Format data sesuai standar FullCalendar
        $events = $activities->map(function (Activity $activity) use ($viewer) {
            $activityMulai = $activity->mulai?->format('Y-m-d');
            $activitySelesai = $activity->selesai?->format('Y-m-d');

            return [
                'id' => (string) $activity->id,
                'title' => $activity->nama,
                'start' => $activityMulai,
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
                    'keterangan' => $activity->keterangan,
                    'assignments_count' => $activity->assignments->count(),
                    // Tanggal efektif memakai fallback tanggal kegiatan tanpa memicu query tambahan per penugasan.
                    'assigned_users' => $activity->assignments->map(function ($assignment) use ($viewer, $activityMulai, $activitySelesai, $activity) {
                        $canViewMetrics = AssignmentVisibility::canViewMetrics($viewer, $assignment, (int) $activity->team_id);

                        return [
                            'assignment_id' => $assignment->id,
                            'user_id' => $assignment->user_id,
                            'nama' => $assignment->user?->nama,
                            'peran' => $assignment->peran,
                            'volume' => $canViewMetrics ? (float) $assignment->volume : null,
                            'satuan' => $canViewMetrics ? $assignment->satuan : null,
                            'mulai' => $assignment->mulai?->format('Y-m-d') ?? $activityMulai,
                            'selesai' => $assignment->selesai?->format('Y-m-d') ?? $activitySelesai,
                            'metrics_visible' => $canViewMetrics,
                        ];
                    }),
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
