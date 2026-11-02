<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Services\SimpleXlsxWriter;
use App\Services\WorkloadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected WorkloadService $workloadService
    ) {}

    /**
     * Laporan Beban Kerja Pegawai (PDF atau CSV/Excel).
     * GET /api/reports/workload?format=pdf|csv&month=YYYY-MM&team_id=X
     */
    public function workloadReport(Request $request)
    {
        $currentUser = $request->user();
        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        if ($role === 'pegawai' && ! $currentUser->ledTeams()->exists()) {
            return response()->json(['message' => 'Anda tidak berwenang mengekspor laporan beban kerja seluruh pegawai.'], 403);
        }

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        $format = strtolower($request->query('format', 'csv'));
        $teamId = $request->query('team_id');

        $query = User::where('aktif', true);
        if ($role === 'pegawai') {
            $ledTeams = $currentUser->ledTeams->pluck('id')->toArray();
            $query->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $ledTeams));
        } elseif ($teamId) {
            $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
        }

        $users = $query->with('teams')->orderBy('nama')->get();
        $ambang = $this->workloadService->getMonthlyThreshold();

        $rows = [];
        foreach ($users as $user) {
            $wl = $this->workloadService->calculateMonthlyWorkloadForUser($user->id, $month);
            $points = $wl['beban'] ?? $wl['poin'] ?? 0.0;
            $rows[] = [
                'nama' => $user->nama,
                'nip' => $user->nip,
                'tim' => $user->teams->pluck('nama')->implode(', '),
                'poin' => $points,
                'persentase' => $wl['persentase'],
                'indikator' => $wl['indikator'],
                'jumlah_penugasan' => $wl['jumlah_penugasan'],
                'penugasan_bersamaan_maks' => $wl['penugasan_bersamaan_maks'],
            ];
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.workload_pdf', [
                'bulan' => $month,
                'ambang' => $ambang,
                'rows' => $rows,
                'tanggal_cetak' => Carbon::now()->isoFormat('D MMMM Y, HH:mm'),
            ]);

            return $pdf->download("Laporan_Beban_Kerja_{$month}.pdf");
        }

        if ($format === 'xlsx' || $format === 'excel') {
            $xlsxRows = [
                ["LAPORAN BEBAN KERJA PEGAWAI BPS KOTA PALEMBANG - BULAN {$month}"],
                ["Ambang Beban Maksimal: {$ambang} poin"],
                [],
                ['No', 'Nama Pegawai', 'NIP', 'Tim Kerja', 'Total Poin', '% Ambang', 'Indikator', 'Jumlah Penugasan', 'Puncak Bersamaan'],
            ];

            $no = 1;
            foreach ($rows as $row) {
                $xlsxRows[] = [
                    $no++,
                    $row['nama'],
                    $row['nip'],
                    $row['tim'],
                    $row['poin'],
                    $row['persentase'] . '%',
                    strtoupper($row['indikator']),
                    $row['jumlah_penugasan'],
                    $row['penugasan_bersamaan_maks'],
                ];
            }

            $tempPath = SimpleXlsxWriter::createXlsx($xlsxRows, 'Beban Kerja');
            return response()->download($tempPath, "Laporan_Beban_Kerja_{$month}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        // CSV Export
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"Laporan_Beban_Kerja_{$month}.csv\"",
        ];

        $callback = function () use ($rows, $month, $ambang) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ["LAPORAN BEBAN KERJA PEGAWAI BPS KOTA PALEMBANG - BULAN {$month}"]);
            fputcsv($file, ["Ambang Beban Maksimal: {$ambang} poin"]);
            fputcsv($file, []);
            fputcsv($file, ['No', 'Nama Pegawai', 'NIP', 'Tim Kerja', 'Total Poin', '% Ambang', 'Indikator', 'Jumlah Penugasan', 'Puncak Bersamaan']);

            $no = 1;
            foreach ($rows as $row) {
                fputcsv($file, [
                    $no++,
                    $row['nama'],
                    $row['nip'],
                    $row['tim'],
                    $row['poin'],
                    $row['persentase'].'%',
                    strtoupper($row['indikator']),
                    $row['jumlah_penugasan'],
                    $row['penugasan_bersamaan_maks'],
                ]);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Laporan Daftar Kegiatan Statistik.
     * GET /api/reports/activities?format=pdf|csv&year=YYYY&team_id=X
     */
    public function activitiesReport(Request $request)
    {
        $currentUser = $request->user();
        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;
        $ledTeamIds = $currentUser->ledTeams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        $isGlobalExporter = in_array($role, ['admin', 'pimpinan'], true);

        // PRD Bagian 14: pegawai biasa tidak berhak mengekspor rekap.
        if (! $isGlobalExporter && $ledTeamIds === []) {
            return response()->json(['message' => 'Anda tidak berwenang mengekspor laporan kegiatan seluruh tim.'], 403);
        }

        $year = $request->query('year', Carbon::now()->format('Y'));
        $format = strtolower($request->query('format', 'csv'));
        $teamId = $request->query('team_id');

        // Ketua tim hanya boleh mengekspor tim yang dipimpinnya.
        if (! $isGlobalExporter) {
            if ($teamId !== null && $teamId !== '') {
                if (! in_array((int) $teamId, $ledTeamIds, true)) {
                    return response()->json(['message' => 'Anda hanya dapat mengekspor laporan tim yang Anda pimpin.'], 403);
                }
            }
        }

        $query = Activity::with(['team', 'assignments'])
            ->where(function ($q) use ($year) {
                $q->whereYear('mulai', $year)
                    ->orWhereYear('selesai', $year);
            });

        if (! $isGlobalExporter) {
            $query->whereIn('team_id', $ledTeamIds);
        } elseif ($teamId) {
            $query->where('team_id', $teamId);
        }

        $activities = $query->orderBy('mulai')->get();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.activities_pdf', [
                'tahun' => $year,
                'activities' => $activities,
                'tanggal_cetak' => Carbon::now()->isoFormat('D MMMM Y, HH:mm'),
            ]);

            return $pdf->download("Laporan_Kegiatan_{$year}.pdf");
        }

        if ($format === 'xlsx' || $format === 'excel') {
            $xlsxRows = [
                ["LAPORAN KEGIATAN STATISTIK BPS KOTA PALEMBANG TAHUN {$year}"],
                [],
                ['No', 'Nama Kegiatan', 'Tim Penanggung Jawab', 'Jenis', 'Bobot', 'Mulai', 'Selesai', 'Status', 'Jumlah Petugas'],
            ];

            $no = 1;
            foreach ($activities as $act) {
                $xlsxRows[] = [
                    $no++,
                    $act->nama,
                    $act->team ? $act->team->nama : '-',
                    $act->jenis->value,
                    (float) $act->bobot,
                    $act->mulai->toDateString(),
                    $act->selesai->toDateString(),
                    $act->status,
                    $act->assignments->count(),
                ];
            }

            $tempPath = SimpleXlsxWriter::createXlsx($xlsxRows, 'Kegiatan');
            return response()->download($tempPath, "Laporan_Kegiatan_{$year}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        // CSV Export
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"Laporan_Kegiatan_{$year}.csv\"",
        ];

        $callback = function () use ($activities, $year) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ["LAPORAN KEGIATAN STATISTIK BPS KOTA PALEMBANG TAHUN {$year}"]);
            fputcsv($file, []);
            fputcsv($file, ['No', 'Nama Kegiatan', 'Tim Penanggung Jawab', 'Jenis', 'Bobot', 'Mulai', 'Selesai', 'Status', 'Jumlah Petugas']);

            $no = 1;
            foreach ($activities as $act) {
                fputcsv($file, [
                    $no++,
                    $act->nama,
                    $act->team ? $act->team->nama : '-',
                    $act->jenis->value,
                    $act->bobot,
                    $act->mulai->toDateString(),
                    $act->selesai->toDateString(),
                    $act->status,
                    $act->assignments->count(),
                ]);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
