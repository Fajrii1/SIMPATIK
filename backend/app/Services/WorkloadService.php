<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkloadService
{
    /**
     * Menghitung distribusi poin bulanan suatu penugasan secara proporsional hari (PRD Bagian 8.1).
     *
     * @return array<string, float> Array dengan kunci 'YYYY-MM' dan nilai poin bulan tersebut (2 desimal).
     */
    public function calculateAssignmentMonthlyPoints(
        float $volume,
        float $bobot,
        CarbonInterface|string $mulai,
        CarbonInterface|string $selesai
    ): array {
        $poinTotal = round($volume * $bobot, 2);

        $startDate = Carbon::parse($mulai)->startOfDay();
        $endDate = Carbon::parse($selesai)->startOfDay();

        if ($startDate->gt($endDate)) {
            return [];
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;

        if ($totalDays <= 0) {
            return [];
        }

        $distribution = [];
        $currentMonth = $startDate->copy()->startOfMonth();
        $endMonth = $endDate->copy()->startOfMonth();

        while ($currentMonth->lte($endMonth)) {
            $monthKey = $currentMonth->format('Y-m');

            $monthStart = $currentMonth->copy()->startOfMonth();
            $monthEnd = $currentMonth->copy()->endOfMonth()->startOfDay();

            $overlapStart = $monthStart->gt($startDate) ? $monthStart : $startDate;
            $overlapEnd = $monthEnd->lt($endDate) ? $monthEnd : $endDate;

            if ($overlapStart->lte($overlapEnd)) {
                $daysInMonth = $overlapStart->diffInDays($overlapEnd) + 1;
                $points = round($poinTotal * ($daysInMonth / $totalDays), 2);
                $distribution[$monthKey] = $points;
            }

            $currentMonth->addMonth();
        }

        return $distribution;
    }

    /**
     * Menghitung puncak jumlah penugasan yang berjalan bersamaan (tumpang tindih pada hari yang sama)
     * dalam satu bulan kalender (PRD Bagian 8.2).
     *
     * @param  Collection<int, Assignment>  $assignments
     */
    public function calculatePeakConcurrentAssignments(Collection $assignments, CarbonInterface $monthStart, CarbonInterface $monthEnd): int
    {
        $daysInMonth = $monthStart->daysInMonth;
        $dailyCounts = array_fill(1, $daysInMonth, 0);

        foreach ($assignments as $assignment) {
            $activity = $assignment->activity;
            if (! $activity) {
                continue;
            }

            $start = $assignment->mulai ? Carbon::parse($assignment->mulai)->startOfDay() : Carbon::parse($activity->mulai)->startOfDay();
            $end = $assignment->selesai ? Carbon::parse($assignment->selesai)->startOfDay() : Carbon::parse($activity->selesai)->startOfDay();

            if ($start->lte($monthEnd) && $end->gte($monthStart)) {
                $clampedStart = $start->gt($monthStart) ? $start : $monthStart;
                $clampedEnd = $end->lt($monthEnd) ? $end : $monthEnd;

                $startDay = (int) $clampedStart->format('j');
                $endDay = (int) $clampedEnd->format('j');

                for ($d = $startDay; $d <= $endDay; $d++) {
                    $dailyCounts[$d]++;
                }
            }
        }

        return ! empty($dailyCounts) ? max($dailyCounts) : 0;
    }

    /**
     * Menghitung puncak jumlah kegiatan besar yang berjalan bersamaan (tumpang tindih pada hari yang sama)
     * dalam satu bulan kalender (PRD Bagian 8.2).
     */
    public function calculatePeakConcurrentKegiatanBesar(CarbonInterface $monthStart, CarbonInterface $monthEnd): int
    {
        $kegiatanBesarList = Activity::query()
            ->where('kegiatan_besar', true)
            ->where('mulai', '<=', $monthEnd)
            ->where('selesai', '>=', $monthStart)
            ->get();

        $daysInMonth = $monthStart->daysInMonth;
        $dailyCounts = array_fill(1, $daysInMonth, 0);

        foreach ($kegiatanBesarList as $activity) {
            $start = Carbon::parse($activity->mulai)->startOfDay();
            $end = Carbon::parse($activity->selesai)->startOfDay();

            $clampedStart = $start->gt($monthStart) ? $start : $monthStart;
            $clampedEnd = $end->lt($monthEnd) ? $end : $monthEnd;

            $startDay = (int) $clampedStart->format('j');
            $endDay = (int) $clampedEnd->format('j');

            for ($d = $startDay; $d <= $endDay; $d++) {
                $dailyCounts[$d]++;
            }
        }

        return ! empty($dailyCounts) ? max($dailyCounts) : 0;
    }

    /**
     * Menghitung total beban kerja bulanan seorang pegawai pada bulan tertentu (PRD Bagian 8.1).
     *
     * @param  string  $month  Format 'YYYY-MM'
     * @param  int|null  $excludeAssignmentId  ID penugasan yang diabaikan (misal saat pratinjau update)
     * @return array<string, mixed>
     */
    public function calculateMonthlyWorkloadForUser(int|User $user, string $month, ?int $excludeAssignmentId = null): array
    {
        $userId = $user instanceof User ? $user->id : $user;

        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();

        // Ambil seluruh penugasan aktif (tidak terhapus secara soft delete)
        $query = Assignment::query()
            ->with('activity')
            ->where('user_id', $userId)
            ->whereHas('activity', function ($q) {
                $q->whereNull('deleted_at');
            });

        if ($excludeAssignmentId !== null) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        $assignments = $query->get();

        $totalBeban = 0.0;
        $activeAssignmentsCount = 0;
        $assignmentBreakdown = [];

        foreach ($assignments as $assignment) {
            $activity = $assignment->activity;
            if (! $activity) {
                continue;
            }

            $effectiveStart = $assignment->mulai ? Carbon::parse($assignment->mulai)->startOfDay() : Carbon::parse($activity->mulai)->startOfDay();
            $effectiveEnd = $assignment->selesai ? Carbon::parse($assignment->selesai)->startOfDay() : Carbon::parse($activity->selesai)->startOfDay();

            // Cek apakah penugasan beririsan dengan bulan terpilih
            if ($effectiveStart->lte($monthEnd) && $effectiveEnd->gte($monthStart)) {
                $activeAssignmentsCount++;

                $monthlyPoints = $this->calculateAssignmentMonthlyPoints(
                    (float) $assignment->volume,
                    (float) ($activity->bobot ?? 1.00),
                    $effectiveStart,
                    $effectiveEnd
                );

                $pointsInMonth = $monthlyPoints[$month] ?? 0.0;
                $totalBeban += $pointsInMonth;

                $assignmentBreakdown[] = [
                    'assignment_id' => $assignment->id,
                    'activity_id' => $activity->id,
                    'activity_nama' => $activity->nama,
                    'peran' => $assignment->peran,
                    'volume' => (float) $assignment->volume,
                    'satuan' => $assignment->satuan,
                    'poin_bulan' => $pointsInMonth,
                    'mulai' => $effectiveStart->format('Y-m-d'),
                    'selesai' => $effectiveEnd->format('Y-m-d'),
                ];
            }
        }

        $totalBeban = round($totalBeban, 2);
        $ambang = (float) Setting::getVal('ambang_beban_bulanan', 100);
        $persentase = $ambang > 0 ? round(($totalBeban / $ambang) * 100, 2) : 0.0;

        $indikator = $this->determineIndicator($persentase);

        // Hitung puncak tumpang tindih harian yang benar-benar bersamaan
        $peakConcurrent = $this->calculatePeakConcurrentAssignments($assignments, $monthStart, $monthEnd);

        return [
            'user_id' => $userId,
            'bulan' => $month,
            'beban' => $totalBeban,
            'ambang' => $ambang,
            'persentase' => $persentase,
            'indikator' => $indikator,
            'jumlah_penugasan' => $activeAssignmentsCount,
            'penugasan_bersamaan_maks' => $peakConcurrent,
            'rincian' => $assignmentBreakdown,
        ];
    }

    /**
     * Menentukan kategori indikator berdasarkan persentase terhadap ambang batas (PRD Bagian 8.1).
     *
     * - 'aman': < 80%
     * - 'hampir_penuh': 80% s.d. < 100%
     * - 'melebihi': >= 100%
     */
    /**
     * Mendapatkan nilai ambang beban bulanan dari pengaturan.
     */
    public function getMonthlyThreshold(): float
    {
        return (float) Setting::getVal('ambang_beban_bulanan', 100);
    }

    /**
     * Menentukan kategori indikator berdasarkan persentase terhadap ambang batas (PRD Bagian 8.1).
     *
     * - 'aman': < 80%
     * - 'hampir_penuh': 80% s.d. < 100%
     * - 'melebihi': >= 100%
     */
    public function determineIndicator(float $persentase): string
    {
        if ($persentase < 80.0) {
            return 'aman';
        }

        if ($persentase < 100.0) {
            return 'hampir_penuh';
        }

        return 'melebihi';
    }

    /**
     * Menghitung seluruh peringatan jadwal menumpuk untuk suatu bulan (PRD Bagian 8.2).
     *
     * @param  string  $month  Format 'YYYY-MM'
     * @return array<int, array{jenis: string, pegawai_id: int|null, bulan: string, pesan: string}>
     */
    public function getMonthlyWarnings(string $month): array
    {
        $warnings = [];

        $ambangBeban = (float) Setting::getVal('ambang_beban_bulanan', 100);
        $maksPenugasan = (int) Setting::getVal('maks_penugasan_bersamaan', 3);
        $maksKegiatanBesar = (int) Setting::getVal('maks_kegiatan_besar_bersamaan', 3);

        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();

        // 1 & 2: Peringatan Beban & Penugasan Bersamaan per Pegawai Aktif
        $users = User::where('aktif', true)->get();

        foreach ($users as $user) {
            $workload = $this->calculateMonthlyWorkloadForUser($user, $month);

            // Peringatan beban mencapai atau melebihi ambang
            if ($workload['beban'] >= $ambangBeban) {
                $warnings[] = [
                    'jenis' => 'beban',
                    'tipe' => 'beban',
                    'tingkat_risiko' => 'tinggi',
                    'pegawai_id' => $user->id,
                    'pegawai_nama' => $user->nama,
                    'user' => [
                        'id' => $user->id,
                        'nama' => $user->nama,
                    ],
                    'bulan' => $month,
                    'pesan' => "Beban kerja {$user->nama} pada bulan {$month} mencapai {$workload['beban']} poin (melebihi/mencapai ambang {$ambangBeban} poin).",
                    'detail' => [
                        'beban_aktual' => $workload['beban'],
                        'ambang' => $ambangBeban,
                    ],
                ];
            }

            // Peringatan penugasan bersamaan mencapai batas atau lebih (berdasarkan tanggal tumpang tindih nyata)
            if ($workload['penugasan_bersamaan_maks'] >= $maksPenugasan) {
                $warnings[] = [
                    'jenis' => 'penugasan_bersamaan',
                    'tipe' => 'penugasan_bersamaan',
                    'tingkat_risiko' => 'sedang',
                    'pegawai_id' => $user->id,
                    'pegawai_nama' => $user->nama,
                    'user' => [
                        'id' => $user->id,
                        'nama' => $user->nama,
                    ],
                    'bulan' => $month,
                    'pesan' => "Terdapat {$workload['penugasan_bersamaan_maks']} penugasan yang berjalan bersamaan (tumpang tindih tanggal) untuk {$user->nama} pada bulan {$month} (batas maksimal: {$maksPenugasan}).",
                    'detail' => [
                        'puncak_bersamaan' => $workload['penugasan_bersamaan_maks'],
                        'batas_maksimal' => $maksPenugasan,
                    ],
                ];
            }
        }

        // 3: Peringatan Kegiatan Besar Bersamaan (Tingkat Kantor berdasarkan tanggal tumpang tindih nyata)
        $peakKegiatanBesar = $this->calculatePeakConcurrentKegiatanBesar($monthStart, $monthEnd);

        if ($peakKegiatanBesar > $maksKegiatanBesar) {
            $warnings[] = [
                'jenis' => 'kegiatan_besar_bersamaan',
                'tipe' => 'kegiatan_besar_bersamaan',
                'tingkat_risiko' => 'tinggi',
                'pegawai_id' => null,
                'pegawai_nama' => null,
                'user' => null,
                'bulan' => $month,
                'pesan' => "Jumlah kegiatan besar yang berjalan bersamaan (tumpang tindih tanggal) pada bulan {$month} mencapai {$peakKegiatanBesar} kegiatan (melebihi batas kantor: {$maksKegiatanBesar}).",
                'detail' => [
                    'kegiatan_besar_bersamaan' => $peakKegiatanBesar,
                    'batas_kantor' => $maksKegiatanBesar,
                ],
            ];
        }

        return $warnings;
    }

    /**
     * Melakukan simulasi (pratinjau) dampak suatu penugasan tanpa menyimpan ke database (PRD Bagian 14).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewAssignmentImpact(array $data): array
    {
        $userId = (int) $data['user_id'];
        $activityId = (int) $data['activity_id'];
        $volume = (float) $data['volume'];
        $satuan = $data['satuan'];
        $peran = $data['peran'];
        $excludeAssignmentId = isset($data['assignment_id']) ? (int) $data['assignment_id'] : null;

        $user = User::findOrFail($userId);
        $activity = Activity::findOrFail($activityId);

        $mulai = ! empty($data['mulai']) ? Carbon::parse($data['mulai'])->startOfDay() : Carbon::parse($activity->mulai)->startOfDay();
        $selesai = ! empty($data['selesai']) ? Carbon::parse($data['selesai'])->startOfDay() : Carbon::parse($activity->selesai)->startOfDay();

        $bobot = (float) ($activity->bobot ?? 1.00);
        $totalPoints = round($volume * $bobot, 2);

        // Distribusi poin rencana penugasan
        $simulatedDistribution = $this->calculateAssignmentMonthlyPoints($volume, $bobot, $mulai, $selesai);

        $ambangBeban = (float) Setting::getVal('ambang_beban_bulanan', 100);
        $maksPenugasan = (int) Setting::getVal('maks_penugasan_bersamaan', 3);

        $monthlyImpacts = [];
        $warnings = [];
        $hasExceededThreshold = false;

        foreach ($simulatedDistribution as $month => $additionalPoints) {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthStart = $monthDate->copy()->startOfMonth();
            $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();

            $currentWorkload = $this->calculateMonthlyWorkloadForUser($user, $month, $excludeAssignmentId);

            $bebanSebelum = $currentWorkload['beban'];
            $bebanSesudah = round($bebanSebelum + $additionalPoints, 2);

            $persentaseSebelum = $currentWorkload['persentase'];
            $persentaseSesudah = $ambangBeban > 0 ? round(($bebanSesudah / $ambangBeban) * 100, 2) : 0.0;

            $indikatorSebelum = $currentWorkload['indikator'];
            $indikatorSesudah = $this->determineIndicator($persentaseSesudah);

            // Hitung simulasi penugasan bersamaan harian
            $daysInMonth = $monthDate->daysInMonth;
            $dailyCounts = array_fill(1, $daysInMonth, 0);

            // Hitung penugasan yang sudah ada
            $existingAssignments = Assignment::query()
                ->with('activity')
                ->where('user_id', $userId)
                ->whereHas('activity', fn ($q) => $q->whereNull('deleted_at'))
                ->when($excludeAssignmentId, fn ($q) => $q->where('id', '!=', $excludeAssignmentId))
                ->get();

            foreach ($existingAssignments as $existing) {
                $eAct = $existing->activity;
                if (! $eAct) {
                    continue;
                }
                $eStart = $existing->mulai ? Carbon::parse($existing->mulai)->startOfDay() : Carbon::parse($eAct->mulai)->startOfDay();
                $eEnd = $existing->selesai ? Carbon::parse($existing->selesai)->startOfDay() : Carbon::parse($eAct->selesai)->startOfDay();

                if ($eStart->lte($monthEnd) && $eEnd->gte($monthStart)) {
                    $cStart = $eStart->gt($monthStart) ? $eStart : $monthStart;
                    $cEnd = $eEnd->lt($monthEnd) ? $eEnd : $monthEnd;
                    for ($d = (int) $cStart->format('j'); $d <= (int) $cEnd->format('j'); $d++) {
                        $dailyCounts[$d]++;
                    }
                }
            }

            // Tambahkan rencana penugasan yang disimulasikan
            $simClampedStart = $mulai->gt($monthStart) ? $mulai : $monthStart;
            $simClampedEnd = $selesai->lt($monthEnd) ? $selesai : $monthEnd;
            for ($d = (int) $simClampedStart->format('j'); $d <= (int) $simClampedEnd->format('j'); $d++) {
                $dailyCounts[$d]++;
            }

            $concurrencySesudah = ! empty($dailyCounts) ? max($dailyCounts) : 1;
            $concurrencySebelum = $currentWorkload['penugasan_bersamaan_maks'];

            // Jumlah total penugasan di bulan ini
            $penugasanSesudah = $currentWorkload['jumlah_penugasan'] + 1;

            if ($bebanSesudah >= $ambangBeban) {
                $hasExceededThreshold = true;
                $warnings[] = [
                    'jenis' => 'beban',
                    'bulan' => $month,
                    'pesan' => "Penugasan ini menyebabkan beban kerja {$user->nama} pada bulan {$month} mencapai {$bebanSesudah} poin (melebihi/mencapai ambang {$ambangBeban}).",
                ];
            }

            if ($concurrencySesudah >= $maksPenugasan) {
                $warnings[] = [
                    'jenis' => 'penugasan_bersamaan',
                    'bulan' => $month,
                    'pesan' => "Penugasan ini menyebabkan {$user->nama} memiliki {$concurrencySesudah} penugasan bersamaan pada tanggal yang sama di bulan {$month} (batas: {$maksPenugasan}).",
                ];
            }

            $monthlyImpacts[] = [
                'bulan' => $month,
                'tambahan_poin' => $additionalPoints,
                'beban_sebelum' => $bebanSebelum,
                'beban_sesudah' => $bebanSesudah,
                'persentase_sebelum' => $persentaseSebelum,
                'persentase_sesudah' => $persentaseSesudah,
                'indikator_sebelum' => $indikatorSebelum,
                'indikator_sesudah' => $indikatorSesudah,
                'jumlah_penugasan_sebelum' => $currentWorkload['jumlah_penugasan'],
                'jumlah_penugasan_sesudah' => $penugasanSesudah,
                'penugasan_bersamaan_sebelum' => $concurrencySebelum,
                'penugasan_bersamaan_sesudah' => $concurrencySesudah,
            ];
        }

        return [
            'pegawai' => [
                'id' => $user->id,
                'nama' => $user->nama,
            ],
            'kegiatan' => [
                'id' => $activity->id,
                'nama' => $activity->nama,
                'bobot' => $bobot,
                'kegiatan_besar' => (bool) $activity->kegiatan_besar,
            ],
            'rencana' => [
                'peran' => $peran,
                'volume' => $volume,
                'satuan' => $satuan,
                'mulai' => $mulai->format('Y-m-d'),
                'selesai' => $selesai->format('Y-m-d'),
                'total_poin' => $totalPoints,
            ],
            'dampak_bulanan' => $monthlyImpacts,
            'peringatan' => $warnings,
            'melebihi_ambang' => $hasExceededThreshold,
        ];
    }

    /**
     * Pratinjau batch dampak beberapa penugasan dari kegiatan yang belum disimpan.
     *
     * Tidak menulis ke database dan tidak mengubah hasil perhitungan yang ada.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewBatchImpact(array $data): array
    {
        $teamId = (int) $data['team_id'];
        $bobot = (float) $data['bobot'];
        $kegiatanMulai = Carbon::parse($data['mulai'])->startOfDay();
        $kegiatanSelesai = ! empty($data['selesai'])
            ? Carbon::parse($data['selesai'])->startOfDay()
            : $kegiatanMulai->copy();

        $ambangBeban = (float) Setting::getVal('ambang_beban_bulanan', 100);
        $maksPenugasan = (int) Setting::getVal('maks_penugasan_bersamaan', 3);

        // Kelompokkan baris per pegawai, dengan periode efektif per baris.
        $byUser = [];

        foreach ($data['assignments'] ?? [] as $row) {
            $userId = (int) $row['user_id'];
            $rowMulai = ! empty($row['mulai']) ? Carbon::parse($row['mulai'])->startOfDay() : $kegiatanMulai->copy();
            $rowSelesai = ! empty($row['selesai']) ? Carbon::parse($row['selesai'])->startOfDay() : $kegiatanSelesai->copy();

            $byUser[$userId][] = array_merge($row, [
                'user_id' => $userId,
                'mulai' => $rowMulai,
                'selesai' => $rowSelesai,
            ]);
        }

        $results = [];

        foreach ($byUser as $userId => $userRows) {
            $user = User::find($userId);

            if (! $user) {
                continue;
            }

            $existingAssignments = Assignment::query()
                ->with('activity')
                ->where('user_id', $userId)
                ->whereHas('activity', fn ($q) => $q->whereNull('deleted_at'))
                ->get();

            // Akumulasi tambahan poin per bulan dari seluruh baris pegawai ini.
            $monthlyAdditions = [];

            foreach ($userRows as $row) {
                foreach ($this->calculateAssignmentMonthlyPoints((float) $row['volume'], $bobot, $row['mulai'], $row['selesai']) as $month => $points) {
                    $monthlyAdditions[$month] = round(($monthlyAdditions[$month] ?? 0.0) + $points, 2);
                }
            }

            ksort($monthlyAdditions);

            $monthlyImpacts = [];
            $warnings = [];

            foreach ($monthlyAdditions as $month => $additionalPoints) {
                $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $monthStart = $monthDate->copy()->startOfMonth();
                $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();

                $currentWorkload = $this->calculateMonthlyWorkloadForUser($userId, $month);
                $bebanSebelum = $currentWorkload['beban'];
                $bebanSesudah = round($bebanSebelum + $additionalPoints, 2);
                $persentaseSebelum = $currentWorkload['persentase'];
                $persentaseSesudah = $ambangBeban > 0 ? round(($bebanSesudah / $ambangBeban) * 100, 2) : 0.0;
                $indikatorSesudah = $this->determineIndicator($persentaseSesudah);

                $rowsInMonth = count(array_filter(
                    $userRows,
                    fn (array $row) => $row['mulai']->lte($monthEnd) && $row['selesai']->gte($monthStart)
                ));
                $jumlahSebelum = $currentWorkload['jumlah_penugasan'];
                $jumlahSesudah = $jumlahSebelum + $rowsInMonth;

                // Puncak harian: penugasan terdaftar ditambah baris batch pada bulan ini.
                $dailyCounts = array_fill(1, $monthDate->daysInMonth, 0);

                $countOnDay = function ($start, $end) use (&$dailyCounts, $monthStart, $monthEnd) {
                    if ($start->lte($monthEnd) && $end->gte($monthStart)) {
                        $clampedStart = $start->gt($monthStart) ? $start : $monthStart;
                        $clampedEnd = $end->lt($monthEnd) ? $end : $monthEnd;
                        for ($d = (int) $clampedStart->format('j'); $d <= (int) $clampedEnd->format('j'); $d++) {
                            $dailyCounts[$d]++;
                        }
                    }
                };

                foreach ($existingAssignments as $existing) {
                    $activity = $existing->activity;

                    if (! $activity) {
                        continue;
                    }

                    $existingStart = $existing->mulai
                        ? Carbon::parse($existing->mulai)->startOfDay()
                        : Carbon::parse($activity->mulai)->startOfDay();
                    $existingEnd = $existing->selesai
                        ? Carbon::parse($existing->selesai)->startOfDay()
                        : Carbon::parse($activity->selesai)->startOfDay();

                    $countOnDay($existingStart, $existingEnd);
                }

                foreach ($userRows as $row) {
                    $countOnDay($row['mulai'], $row['selesai']);
                }

                $concurrencySesudah = max($dailyCounts);
                $concurrencySebelum = $currentWorkload['penugasan_bersamaan_maks'];

                if ($bebanSesudah >= $ambangBeban) {
                    $warnings[] = [
                        'jenis' => 'beban',
                        'bulan' => $month,
                        'pesan' => "Penugasan ini menyebabkan beban kerja {$user->nama} pada bulan {$month} mencapai {$bebanSesudah} poin (melebihi/mencapai ambang {$ambangBeban}).",
                    ];
                }

                if ($concurrencySesudah >= $maksPenugasan) {
                    $warnings[] = [
                        'jenis' => 'penugasan_bersamaan',
                        'bulan' => $month,
                        'pesan' => "Penugasan ini menyebabkan {$user->nama} memiliki {$concurrencySesudah} penugasan bersamaan pada tanggal yang sama di bulan {$month} (batas: {$maksPenugasan}).",
                    ];
                }

                $monthlyImpacts[] = [
                    'bulan' => $month,
                    'tambahan_poin' => $additionalPoints,
                    'beban_sebelum' => $bebanSebelum,
                    'beban_sesudah' => $bebanSesudah,
                    'persentase_sebelum' => $persentaseSebelum,
                    'persentase_sesudah' => $persentaseSesudah,
                    'indikator_sebelum' => $currentWorkload['indikator'],
                    'indikator_sesudah' => $indikatorSesudah,
                    'jumlah_penugasan_sebelum' => $jumlahSebelum,
                    'jumlah_penugasan_sesudah' => $jumlahSesudah,
                    'penugasan_bersamaan_sebelum' => $concurrencySebelum,
                    'penugasan_bersamaan_sesudah' => $concurrencySesudah,
                ];
            }

            $results[] = [
                'user_id' => $user->id,
                'pegawai' => [
                    'id' => $user->id,
                    'nama' => $user->nama,
                ],
                'dampak_bulanan' => $monthlyImpacts,
                'peringatan' => $warnings,
            ];
        }

        return [
            'kegiatan' => [
                'team_id' => $teamId,
                'mulai' => $kegiatanMulai->format('Y-m-d'),
                'selesai' => $kegiatanSelesai->format('Y-m-d'),
                'bobot' => $bobot,
            ],
            'rows' => $results,
        ];
    }
}
