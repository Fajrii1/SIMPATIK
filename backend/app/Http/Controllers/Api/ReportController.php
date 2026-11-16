<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKendala;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Models\WeeklyIssue;
use App\Services\SimpleXlsxWriter;
use App\Services\WorkloadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected WorkloadService $workloadService
    ) {}

    /**
     * Laporan Beban Kerja Pegawai (PDF, CSV, atau Excel).
     * GET /api/reports/workload?format=pdf|csv|xlsx&month=YYYY-MM&team_id=X
     */
    public function workloadReport(Request $request)
    {
        $currentUser = $request->user();
        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;

        // Pimpinan hanya memantau dashboard dan monitoring, tidak mengekspor.
        if ($role === 'pimpinan') {
            return response()->json(['message' => 'Pimpinan tidak berwenang mengekspor laporan.'], 403);
        }

        $ledTeamIds = $currentUser->ledTeams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        $isGlobalExporter = $role === 'admin';

        if (! $isGlobalExporter && $ledTeamIds === []) {
            return response()->json(['message' => 'Anda tidak berwenang mengekspor laporan beban kerja seluruh pegawai.'], 403);
        }

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $month = Carbon::now()->format('Y-m');
        }

        $format = strtolower((string) $request->query('format', 'csv'));
        $teamId = $request->query('team_id');
        $teamId = ($teamId === null || $teamId === '') ? null : (int) $teamId;

        if (! $isGlobalExporter && $teamId !== null && ! in_array($teamId, $ledTeamIds, true)) {
            return response()->json(['message' => 'Anda hanya dapat mengekspor laporan tim yang Anda pimpin.'], 403);
        }

        // Laporan beban kerja hanya memuat pegawai; admin dan pimpinan tidak dihitung.
        $query = User::query()
            ->where('aktif', true)
            ->where('peran', 'pegawai');

        if (! $isGlobalExporter) {
            $query->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $ledTeamIds));
        } elseif ($teamId !== null) {
            $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
        }

        $users = $query->with('teams')->orderBy('nama')->get();
        $jamKerjaPerHari = $this->workloadService->getDailyWorkHours();

        $rows = [];
        foreach ($users as $user) {
            $wl = $this->workloadService->calculateMonthlyWorkloadForUser($user, $month);
            $rows[] = [
                'nama' => $user->nama,
                'nip' => $user->nip,
                'tim' => $user->teams->pluck('nama')->implode(', '),
                'jam_terpakai' => $wl['jam_terpakai'],
                'kapasitas_jam' => $wl['kapasitas_jam'],
                'persentase' => $wl['persentase'],
                'indikator' => $wl['indikator'],
                'jumlah_penugasan' => $wl['jumlah_penugasan'],
                'penugasan_bersamaan_maks' => $wl['penugasan_bersamaan_maks'],
            ];
        }

        $judul = 'Laporan Beban Kerja Pegawai';
        $kolom = ['No', 'Nama Pegawai', 'NIP', 'Tim Kerja', 'Jam Terpakai', 'Kapasitas (Jam)', '% Kapasitas', 'Indikator', 'Jumlah Penugasan', 'Puncak Bersamaan'];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.workload_pdf', [
                'bulan' => $month,
                'jam_kerja_per_hari' => $jamKerjaPerHari,
                'rows' => $rows,
                'tanggal_cetak' => Carbon::now()->isoFormat('D MMMM Y, HH:mm'),
            ]);

            return $pdf->download("Laporan_Beban_Kerja_{$month}.pdf");
        }

        $tableRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $tableRows[] = [
                $no++,
                $row['nama'],
                $row['nip'],
                $row['tim'],
                $row['jam_terpakai'],
                $row['kapasitas_jam'],
                $row['persentase'].'%',
                self::labelIndikator($row['indikator']),
                $row['jumlah_penugasan'],
                $row['penugasan_bersamaan_maks'],
            ];
        }

        if ($format === 'xlsx' || $format === 'excel') {
            $xlsxRows = [
                ['LAPORAN BEBAN KERJA PEGAWAI BPS KOTA PALEMBANG - BULAN '.$month],
                ['Jam kerja per hari: '.$jamKerjaPerHari.' jam'],
                [],
                $kolom,
                ...$tableRows,
            ];

            $tempPath = SimpleXlsxWriter::createXlsx($xlsxRows, 'Beban Kerja');

            return response()->download($tempPath, "Laporan_Beban_Kerja_{$month}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return $this->csvResponse(
            "Laporan_Beban_Kerja_{$month}.csv",
            [
                ['LAPORAN BEBAN KERJA PEGAWAI BPS KOTA PALEMBANG - BULAN '.$month],
                ['Jam kerja per hari: '.$jamKerjaPerHari.' jam'],
                [],
                $kolom,
                ...$tableRows,
            ]
        );
    }

    /**
     * Laporan Daftar Kegiatan Statistik.
     * GET /api/reports/activities?format=pdf|csv|xlsx&year=YYYY&team_id=X
     */
    public function activitiesReport(Request $request)
    {
        $currentUser = $request->user();
        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;

        if ($role === 'pimpinan') {
            return response()->json(['message' => 'Pimpinan tidak berwenang mengekspor laporan.'], 403);
        }

        $ledTeamIds = $currentUser->ledTeams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        $isGlobalExporter = $role === 'admin';

        // PRD Bagian 14: pegawai biasa tidak berhak mengekspor rekap.
        if (! $isGlobalExporter && $ledTeamIds === []) {
            return response()->json(['message' => 'Anda tidak berwenang mengekspor laporan kegiatan seluruh tim.'], 403);
        }

        $year = $request->query('year', Carbon::now()->format('Y'));
        $format = strtolower((string) $request->query('format', 'csv'));
        $teamId = $request->query('team_id');

        // Ketua tim hanya boleh mengekspor tim yang dipimpinnya.
        if (! $isGlobalExporter) {
            if ($teamId !== null && $teamId !== '') {
                if (! in_array((int) $teamId, $ledTeamIds, true)) {
                    return response()->json(['message' => 'Anda hanya dapat mengekspor laporan tim yang Anda pimpin.'], 403);
                }
            }
        }

        // Jumlah Petugas hanya menghitung pegawai, bukan admin atau pimpinan.
        $query = Activity::with([
            'team',
            'assignments' => fn ($q) => $q->whereHas('user', fn ($u) => $u->where('peran', 'pegawai')),
        ])
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

        $kolom = ['No', 'Nama Kegiatan', 'Tim Penanggung Jawab', 'Jenis', 'Kegiatan Besar', 'Mulai', 'Selesai', 'Status', 'Jumlah Petugas'];
        $tableRows = [];
        $no = 1;

        foreach ($activities as $act) {
            $tableRows[] = [
                $no++,
                $act->nama,
                $act->team ? $act->team->nama : '-',
                $act->jenis,
                $act->kegiatan_besar ? 'Ya' : 'Tidak',
                $act->mulai->toDateString(),
                $act->selesai->toDateString(),
                $act->status,
                $act->assignments->count(),
            ];
        }

        if ($format === 'xlsx' || $format === 'excel') {
            $xlsxRows = [
                ['LAPORAN KEGIATAN STATISTIK BPS KOTA PALEMBANG TAHUN '.$year],
                [],
                $kolom,
                ...$tableRows,
            ];

            $tempPath = SimpleXlsxWriter::createXlsx($xlsxRows, 'Kegiatan');

            return response()->download($tempPath, "Laporan_Kegiatan_{$year}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return $this->csvResponse(
            "Laporan_Kegiatan_{$year}.csv",
            [
                ['LAPORAN KEGIATAN STATISTIK BPS KOTA PALEMBANG TAHUN '.$year],
                [],
                $kolom,
                ...$tableRows,
            ]
        );
    }

    /**
     * Laporan Kendala Bulanan (PDF, CSV, atau Excel).
     *
     * GET /api/reports/issues?format=pdf|csv|xlsx&month_from=YYYY-MM&month_to=YYYY-MM&team_id=X&status=terbuka
     */
    public function issuesReport(Request $request)
    {
        $currentUser = $request->user();
        $role = $currentUser->peran instanceof \BackedEnum ? $currentUser->peran->value : (string) $currentUser->peran;

        if ($role === 'pimpinan') {
            return response()->json(['message' => 'Pimpinan tidak berwenang mengekspor laporan.'], 403);
        }

        $ledTeamIds = $currentUser->ledTeams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        $isGlobalExporter = $role === 'admin';

        // PRD Bagian 14: pegawai biasa tidak berhak mengekspor rekap kendala.
        if (! $isGlobalExporter && $ledTeamIds === []) {
            return response()->json(['message' => 'Anda tidak berwenang mengekspor laporan kendala seluruh tim.'], 403);
        }

        // Parameter baru berbasis bulan; week_from/week_to tetap diterima sebagai alias.
        $monthFrom = $request->query('month_from', $request->query('week_from'));
        $monthTo = $request->query('month_to', $request->query('week_to'));
        $status = strtolower((string) $request->query('status', ''));
        $format = strtolower((string) $request->query('format', 'csv'));
        $teamId = $request->query('team_id');

        if ($teamId !== null && $teamId !== '') {
            if (! $isGlobalExporter && ! in_array((int) $teamId, $ledTeamIds, true)) {
                return response()->json(['message' => 'Anda hanya dapat mengekspor laporan tim yang Anda pimpin.'], 403);
            }
            $teamId = (int) $teamId;
        } else {
            $teamId = null;
        }

        if ($status !== '' && ! in_array($status, ['terbuka', 'diproses', 'selesai'], true)) {
            return response()->json(['message' => 'Status kendala tidak valid.'], 422);
        }

        $query = WeeklyIssue::with(['team', 'pelapor', 'pic', 'activity']);

        if ($monthFrom) {
            $query->where('bulan', '>=', Carbon::parse($monthFrom)->startOfMonth()->toDateString());
        }

        if ($monthTo) {
            $query->where('bulan', '<=', Carbon::parse($monthTo)->startOfMonth()->toDateString());
        }

        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        } elseif (! $isGlobalExporter) {
            $query->whereIn('team_id', $ledTeamIds);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $issues = $query->orderBy('bulan')->orderBy('id')->get();

        $rows = [];
        foreach ($issues as $issue) {
            $rows[] = [
                'bulan' => $issue->bulan?->format('Y-m') ?? '-',
                'tim' => $issue->team?->nama ?? '-',
                'kegiatan' => $issue->activity?->nama ?? '-',
                'kendala' => $issue->kendala,
                'solusi' => $issue->solusi ?? '-',
                'tindak_lanjut' => $issue->tindak_lanjut ?? '-',
                'pelapor' => $issue->pelapor && $issue->pelapor->isPegawai() ? $issue->pelapor->nama : '-',
                'pic' => $issue->pic && $issue->pic->isPegawai() ? $issue->pic->nama : '-',
                'tenggat' => $issue->tenggat?->format('Y-m-d') ?? '-',
                'status_key' => $issue->status instanceof StatusKendala ? $issue->status->value : (string) $issue->status,
                'status' => $issue->status instanceof StatusKendala ? $issue->status->label() : (string) $issue->status,
                'catatan_penutupan' => $issue->catatan_penutupan ?? '-',
            ];
        }

        $label = ($monthFrom || $monthTo)
            ? '_'.($monthFrom ? Carbon::parse($monthFrom)->format('Ym') : 'awal')
                .'-'.($monthTo ? Carbon::parse($monthTo)->format('Ym') : 'akhir')
            : '';
        $filename = "Laporan_Kendala{$label}";
        $kolom = ['No', 'Bulan', 'Tim', 'Kegiatan', 'Kendala', 'Solusi', 'Tindak Lanjut', 'Pelapor', 'PIC', 'Tenggat', 'Status', 'Catatan Penutupan'];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.issues_pdf', [
                'month_from' => $monthFrom,
                'month_to' => $monthTo,
                'status' => $status !== '' ? (StatusKendala::tryFrom($status)?->label() ?? $status) : null,
                'rows' => $rows,
                'tanggal_cetak' => Carbon::now()->isoFormat('D MMMM Y, HH:mm'),
            ]);

            return $pdf->download("{$filename}.pdf");
        }

        $tableRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $tableRows[] = [
                $no++,
                $row['bulan'],
                $row['tim'],
                $row['kegiatan'],
                $row['kendala'],
                $row['solusi'],
                $row['tindak_lanjut'],
                $row['pelapor'],
                $row['pic'],
                $row['tenggat'],
                $row['status'],
                $row['catatan_penutupan'],
            ];
        }

        if ($format === 'xlsx' || $format === 'excel') {
            $xlsxRows = [
                ['LAPORAN KENDALA BULANAN BPS KOTA PALEMBANG'],
                [],
                $kolom,
                ...$tableRows,
            ];

            $tempPath = SimpleXlsxWriter::createXlsx($xlsxRows, 'Kendala');

            return response()->download($tempPath, "{$filename}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return $this->csvResponse(
            "{$filename}.csv",
            [
                ['LAPORAN KENDALA BULANAN BPS KOTA PALEMBANG'],
                ['Rentang Bulan: '.($label !== '' ? ltrim($label, '_') : 'Semua')],
                [],
                $kolom,
                ...$tableRows,
            ]
        );
    }

    /**
     * Menulis baris CSV dengan BOM UTF-8 agar tampil benar di Excel.
     *
     * @param  array<int, array<int, mixed>>  $lines
     */
    private function csvResponse(string $filename, array $lines): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($lines) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            foreach ($lines as $line) {
                fputcsv($file, $line);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Label indikator beban kerja untuk ekspor.
     */
    private static function labelIndikator(string $indikator): string
    {
        return match ($indikator) {
            'aman' => 'Aman',
            'hampir_penuh' => 'Hampir Penuh',
            default => 'Melebihi Kapasitas',
        };
    }
}
