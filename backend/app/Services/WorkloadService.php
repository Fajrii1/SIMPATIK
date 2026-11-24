<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkloadService
{
    /**
     * Jam kerja per hari dari pengaturan (default 7 jam).
     */
    public function getDailyWorkHours(): float
    {
        $value = (float) Setting::getVal('jam_kerja_per_hari', 7);

        return $value > 0 ? $value : 7.0;
    }

    /**
     * Daftar hari libur (format Y-m-d) dari pengaturan.
     *
     * @return list<string>
     */
    public function getHolidayDates(): array
    {
        $raw = Setting::getVal('hari_libur', '[]');

        if (is_array($raw)) {
            $items = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        $dates = [];

        foreach ($items as $item) {
            if (! is_string($item) || $item === '') {
                continue;
            }

            try {
                $dates[] = Carbon::parse($item)->format('Y-m-d');
            } catch (\Throwable) {
                // Abaikan nilai yang bukan tanggal valid.
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * Menghitung jumlah hari kerja (Senin-Jumat, bukan hari libur) pada rentang tanggal.
     */
    public function countWorkdays(CarbonInterface|string $mulai, CarbonInterface|string $selesai, ?array $holidays = null): int
    {
        $start = Carbon::parse($mulai)->startOfDay();
        $end = Carbon::parse($selesai)->startOfDay();

        if ($start->gt($end)) {
            return 0;
        }

        $holidays ??= $this->getHolidayDates();
        $count = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if ($cursor->isWeekday() && ! in_array($cursor->format('Y-m-d'), $holidays, true)) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    /**
     * Menghitung jam terpakai satu penugasan per bulan.
     *
     * Jam terpakai bulan = jam_per_hari x jumlah hari kerja pada irisan periode
     * efektif penugasan dengan bulan tersebut.
     *
     * @return array<string, array{jam: float, hari_kerja: int}>
     */
    public function calculateAssignmentMonthlyHours(
        float $jamPerHari,
        CarbonInterface|string $mulai,
        CarbonInterface|string $selesai
    ): array {
        $startDate = Carbon::parse($mulai)->startOfDay();
        $endDate = Carbon::parse($selesai)->startOfDay();

        if ($startDate->gt($endDate)) {
            return [];
        }

        $holidays = $this->getHolidayDates();
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
                $workdays = $this->countWorkdays($overlapStart, $overlapEnd, $holidays);
                $distribution[$monthKey] = [
                    'jam' => round($jamPerHari * $workdays, 2),
                    'hari_kerja' => $workdays,
                ];
            }

            $currentMonth->addMonth();
        }

        return $distribution;
    }

    /**
     * Menyebar total menit beban secara merata ke hari kerja pada periode penugasan.
     *
     * @return array<string, array{jam: float, hari_kerja: int, menit: float}>
     */
    public function calculateAssignmentMonthlyHoursFromMinutes(
        int $totalMenit,
        CarbonInterface|string $mulai,
        CarbonInterface|string $selesai
    ): array {
        $startDate = Carbon::parse($mulai)->startOfDay();
        $endDate = Carbon::parse($selesai)->startOfDay();

        if ($startDate->gt($endDate)) {
            return [];
        }

        $holidays = $this->getHolidayDates();
        $totalWorkdays = $this->countWorkdays($startDate, $endDate, $holidays);

        if ($totalWorkdays === 0) {
            return [];
        }

        $totalHours = $totalMenit / 60;
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
                $workdays = $this->countWorkdays($overlapStart, $overlapEnd, $holidays);
                $ratio = $workdays / $totalWorkdays;

                $distribution[$monthKey] = [
                    'jam' => round($totalHours * $ratio, 2),
                    'hari_kerja' => $workdays,
                    'menit' => round($totalMenit * $ratio, 2),
                ];
            }

            $currentMonth->addMonth();
        }

        return $distribution;
    }

    /**
     * Jam per hari efektif sebuah penugasan (hasil sebaran total menit).
     */
    public function getAssignmentDailyHours(Assignment $assignment): float
    {
        if ($assignment->total_menit === null) {
            return (float) $assignment->jam_per_hari;
        }

        $start = $assignment->getEffectiveMulai();
        $end = $assignment->getEffectiveSelesai();

        if (! $start || ! $end) {
            return 0.0;
        }

        $workdays = $this->countWorkdays($start, $end);

        if ($workdays === 0) {
            return 0.0;
        }

        return round(($assignment->total_menit / 60) / $workdays, 4);
    }

    /**
     * Menyiapkan atribut ABK penugasan dari payload form (jenis pekerjaan + volume).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveAssignmentAttributes(array $data, Activity $activity, ?Assignment $existing = null, bool $onlyProvided = false): array
    {
        $has = fn (string $key): bool => array_key_exists($key, $data);
        $relevant = ['work_type_id', 'jam_per_hari', 'volume', 'mulai', 'selesai'];

        if ($onlyProvided && ! array_intersect($relevant, array_keys($data))) {
            return [];
        }

        $workTypeId = $has('work_type_id') ? $data['work_type_id'] : $existing?->work_type_id;
        $volume = $has('volume') ? $data['volume'] : $existing?->volume;
        $jamPerHari = $has('jam_per_hari') ? $data['jam_per_hari'] : $existing?->jam_per_hari;

        $mulai = $has('mulai') ? $data['mulai'] : ($existing?->mulai?->toDateString());
        $selesai = $has('selesai') ? $data['selesai'] : ($existing?->selesai?->toDateString());

        $start = $mulai ?: $activity->mulai?->toDateString();
        $end = $selesai ?: $activity->selesai?->toDateString();
        $workdays = ($start && $end) ? $this->countWorkdays($start, $end) : 0;

        if ($workTypeId !== null && $workTypeId !== '') {
            if ($volume === null || $volume === '') {
                return [];
            }

            /** @var WorkType $workType */
            $workType = WorkType::findOrFail((int) $workTypeId);
            $totalMenit = (int) round((float) $volume * $workType->norma_menit);

            return [
                'work_type_id' => $workType->id,
                'norma_menit' => $workType->norma_menit,
                'total_menit' => $totalMenit,
                'satuan' => $workType->satuan,
                'jam_per_hari' => $workdays > 0 ? round(($totalMenit / 60) / $workdays, 2) : 0,
            ];
        }

        if ($jamPerHari !== null && $jamPerHari !== '') {
            $jam = (float) $jamPerHari;

            return [
                'total_menit' => (int) round($jam * $workdays * 60),
                'jam_per_hari' => $jam,
            ];
        }

        return [];
    }

    /**
     * Menghitung rencana beban dari payload pratinjau (jenis pekerjaan/volume atau data lama).
     *
     * @param  array<string, mixed>  $data
     * @return array{total_menit: int, norma_menit: int|null, work_type_id: int|null, satuan: string|null, jam_per_hari: float, total_jam: float}
     */
    public function resolveWorkloadForPeriod(array $data, CarbonInterface|string $mulai, CarbonInterface|string $selesai): array
    {
        $workdays = $this->countWorkdays($mulai, $selesai);
        $workTypeId = isset($data['work_type_id']) && $data['work_type_id'] !== '' ? (int) $data['work_type_id'] : null;
        $volume = isset($data['volume']) && $data['volume'] !== null && $data['volume'] !== '' ? (float) $data['volume'] : null;

        if ($workTypeId !== null) {
            /** @var WorkType $workType */
            $workType = WorkType::findOrFail($workTypeId);
            $totalMenit = (int) round(($volume ?? 0.0) * $workType->norma_menit);

            return [
                'total_menit' => $totalMenit,
                'norma_menit' => $workType->norma_menit,
                'work_type_id' => $workType->id,
                'satuan' => $workType->satuan,
                'jam_per_hari' => $workdays > 0 ? round(($totalMenit / 60) / $workdays, 4) : 0.0,
                'total_jam' => round($totalMenit / 60, 2),
            ];
        }

        if (isset($data['total_menit']) && $data['total_menit'] !== null) {
            $totalMenit = (int) $data['total_menit'];

            return [
                'total_menit' => $totalMenit,
                'norma_menit' => isset($data['norma_menit']) ? (int) $data['norma_menit'] : null,
                'work_type_id' => null,
                'satuan' => $data['satuan'] ?? null,
                'jam_per_hari' => $workdays > 0 ? round(($totalMenit / 60) / $workdays, 4) : 0.0,
                'total_jam' => round($totalMenit / 60, 2),
            ];
        }

        $jamPerHari = (float) ($data['jam_per_hari'] ?? 0);
        $totalMenit = (int) round($jamPerHari * $workdays * 60);

        return [
            'total_menit' => $totalMenit,
            'norma_menit' => null,
            'work_type_id' => null,
            'satuan' => $data['satuan'] ?? null,
            'jam_per_hari' => $jamPerHari,
            'total_jam' => round($totalMenit / 60, 2),
        ];
    }

    /**
     * Menghitung puncak jumlah penugasan yang berjalan bersamaan (tumpang tindih pada hari yang sama)
     * dalam satu bulan kalender.
     *
     * @param  Collection<int, Assignment>  $assignments
     */
    public function calculatePeakConcurrentAssignments(Collection $assignments, CarbonInterface $monthStart, CarbonInterface $monthEnd): int
    {
        $daysInMonth = $monthStart->daysInMonth;
        $dailyCounts = array_fill(1, $daysInMonth, 0);
        $holidays = $this->getHolidayDates();

        foreach ($assignments as $assignment) {
            $activity = $assignment->activity;
            if (! $activity) {
                continue;
            }

            $start = $assignment->mulai ? Carbon::parse($assignment->mulai)->startOfDay() : Carbon::parse($activity->mulai)->startOfDay();
            $end = $assignment->selesai ? Carbon::parse($assignment->selesai)->startOfDay() : Carbon::parse($activity->selesai)->startOfDay();

            $this->countConcurrentDays($dailyCounts, $start, $end, $monthStart, $monthEnd, $holidays);
        }

        return ! empty($dailyCounts) ? max($dailyCounts) : 0;
    }

    /**
     * Menghitung puncak jumlah kegiatan besar yang berjalan bersamaan dalam satu bulan kalender.
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
        $holidays = $this->getHolidayDates();

        foreach ($kegiatanBesarList as $activity) {
            $start = Carbon::parse($activity->mulai)->startOfDay();
            $end = Carbon::parse($activity->selesai)->startOfDay();

            $this->countConcurrentDays($dailyCounts, $start, $end, $monthStart, $monthEnd, $holidays);
        }

        return ! empty($dailyCounts) ? max($dailyCounts) : 0;
    }

    /**
     * Menghitung beban kerja bulanan seorang pegawai (jam terpakai, kapasitas, persentase).
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

        $query = Assignment::query()
            ->with(['activity', 'workType'])
            ->where('user_id', $userId)
            ->whereHas('activity', function ($q) {
                $q->whereNull('deleted_at');
            });

        if ($excludeAssignmentId !== null) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        $assignments = $query->get();

        $dailyWorkHours = $this->getDailyWorkHours();
        $holidays = $this->getHolidayDates();
        $totalHours = 0.0;
        $activeAssignmentsCount = 0;
        $assignmentBreakdown = [];
        $dailyLoads = [];

        foreach ($assignments as $assignment) {
            $activity = $assignment->activity;
            if (! $activity) {
                continue;
            }

            $effectiveStart = $assignment->mulai ? Carbon::parse($assignment->mulai)->startOfDay() : Carbon::parse($activity->mulai)->startOfDay();
            $effectiveEnd = $assignment->selesai ? Carbon::parse($assignment->selesai)->startOfDay() : Carbon::parse($activity->selesai)->startOfDay();
            $jamPerHari = (float) $assignment->jam_per_hari;

            if ($effectiveStart->lte($monthEnd) && $effectiveEnd->gte($monthStart)) {
                $overlapStart = $effectiveStart->gt($monthStart) ? $effectiveStart : $monthStart;
                $overlapEnd = $effectiveEnd->lt($monthEnd) ? $effectiveEnd : $monthEnd;
                $workdays = $this->countWorkdays($overlapStart, $overlapEnd, $holidays);

                if ($assignment->total_menit !== null) {
                    $totalWorkdays = $this->countWorkdays($effectiveStart, $effectiveEnd, $holidays);
                    $hoursInMonth = $totalWorkdays > 0
                        ? round(($assignment->total_menit / 60) * ($workdays / $totalWorkdays), 2)
                        : 0.0;
                    $jamPerHari = $totalWorkdays > 0
                        ? round(($assignment->total_menit / 60) / $totalWorkdays, 4)
                        : 0.0;
                    $totalJam = round($assignment->total_menit / 60, 2);
                } else {
                    $hoursInMonth = round($jamPerHari * $workdays, 2);
                    $totalJam = round($jamPerHari * $this->countWorkdays($effectiveStart, $effectiveEnd, $holidays), 2);
                }

                $activeAssignmentsCount++;
                $totalHours += $hoursInMonth;

                $assignmentBreakdown[] = [
                    'assignment_id' => $assignment->id,
                    'activity_id' => $activity->id,
                    'activity_nama' => $activity->nama,
                    'peran' => $assignment->peran,
                    'work_type_id' => $assignment->work_type_id,
                    'work_type_nama' => $assignment->workType?->nama,
                    'norma_menit' => $assignment->norma_menit,
                    'total_menit' => $assignment->total_menit,
                    'total_jam' => $totalJam,
                    'jam_per_hari' => round($jamPerHari, 2),
                    'hari_kerja' => $workdays,
                    'jam_terpakai' => $hoursInMonth,
                    'volume' => $assignment->volume !== null ? (float) $assignment->volume : null,
                    'satuan' => $assignment->satuan,
                    'mulai' => $effectiveStart->format('Y-m-d'),
                    'selesai' => $effectiveEnd->format('Y-m-d'),
                ];

                // Akumulasi beban harian untuk hari kerja pada bulan ini.
                $cursor = $overlapStart->copy();
                while ($cursor->lte($overlapEnd)) {
                    if ($cursor->isWeekday() && ! in_array($cursor->format('Y-m-d'), $holidays, true)) {
                        $key = $cursor->format('Y-m-d');
                        $dailyLoads[$key] = round(($dailyLoads[$key] ?? 0.0) + $jamPerHari, 2);
                    }
                    $cursor->addDay();
                }
            }
        }

        $totalHours = round($totalHours, 2);
        $capacityWorkdays = $this->countWorkdays($monthStart, $monthEnd, $holidays);
        $capacityHours = round($capacityWorkdays * $dailyWorkHours, 2);
        $persentase = $capacityHours > 0 ? round(($totalHours / $capacityHours) * 100, 2) : 0.0;

        $excessDays = [];
        foreach ($dailyLoads as $tanggal => $jam) {
            if ($jam > $dailyWorkHours + 0.0001) {
                $excessDays[] = [
                    'tanggal' => $tanggal,
                    'jam' => round($jam, 2),
                ];
            }
        }
        usort($excessDays, fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

        $peakConcurrent = $this->calculatePeakConcurrentAssignments($assignments, $monthStart, $monthEnd);

        return [
            'user_id' => $userId,
            'bulan' => $month,
            'jam_terpakai' => $totalHours,
            'kapasitas_jam' => $capacityHours,
            'hari_kerja' => $capacityWorkdays,
            'jam_kerja_per_hari' => $dailyWorkHours,
            // Alias lama agar konsumen yang belum diperbarui tetap membaca nilai jam yang benar.
            'beban' => $totalHours,
            'persentase' => $persentase,
            'indikator' => $this->determineIndicator($persentase),
            'jumlah_penugasan' => $activeAssignmentsCount,
            'penugasan_bersamaan_maks' => $peakConcurrent,
            'beban_harian_maks' => ! empty($dailyLoads) ? max($dailyLoads) : 0.0,
            'hari_melebihi_kapasitas' => $excessDays,
            'rincian' => $assignmentBreakdown,
        ];
    }

    /**
     * Menentukan kategori indikator berdasarkan persentase kapasitas bulanan.
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
     * Menghitung seluruh peringatan jadwal menumpuk untuk suatu bulan.
     *
     * @param  string  $month  Format 'YYYY-MM'
     * @return array<int, array{jenis: string, pegawai_id: int|null, bulan: string, pesan: string}>
     */
    public function getMonthlyWarnings(string $month): array
    {
        $warnings = [];

        $maksPenugasan = (int) Setting::getVal('maks_penugasan_bersamaan', 3);
        $maksKegiatanBesar = (int) Setting::getVal('maks_kegiatan_besar_bersamaan', 3);

        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();

        $users = User::where('aktif', true)->get();

        foreach ($users as $user) {
            $workload = $this->calculateMonthlyWorkloadForUser($user, $month);

            if ($workload['persentase'] >= 100.0) {
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
                    'pesan' => "Beban kerja {$user->nama} pada bulan {$month} mencapai {$workload['jam_terpakai']} jam dari kapasitas {$workload['kapasitas_jam']} jam ({$workload['persentase']}%).",
                    'detail' => [
                        'jam_terpakai' => $workload['jam_terpakai'],
                        'kapasitas_jam' => $workload['kapasitas_jam'],
                        'persentase' => $workload['persentase'],
                    ],
                ];
            }

            if ($workload['hari_melebihi_kapasitas'] !== []) {
                $tanggal = array_map(
                    fn ($item) => Carbon::parse($item['tanggal'])->format('d/m/Y').' ('.$item['jam'].' jam)',
                    $workload['hari_melebihi_kapasitas']
                );

                $warnings[] = [
                    'jenis' => 'melebihi_kapasitas_harian',
                    'tipe' => 'melebihi_kapasitas_harian',
                    'tingkat_risiko' => 'tinggi',
                    'pegawai_id' => $user->id,
                    'pegawai_nama' => $user->nama,
                    'user' => [
                        'id' => $user->id,
                        'nama' => $user->nama,
                    ],
                    'bulan' => $month,
                    'pesan' => "Beban harian {$user->nama} melebihi kapasitas {$workload['jam_kerja_per_hari']} jam per hari pada: ".implode(', ', $tanggal).'.',
                    'detail' => [
                        'kapasitas_harian' => $workload['jam_kerja_per_hari'],
                        'hari' => $workload['hari_melebihi_kapasitas'],
                    ],
                ];
            }

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
     * Melakukan simulasi (pratinjau) dampak suatu penugasan tanpa menyimpan ke database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewAssignmentImpact(array $data): array
    {
        $userId = (int) $data['user_id'];
        $activityId = (int) $data['activity_id'];
        $volume = isset($data['volume']) && $data['volume'] !== null ? (float) $data['volume'] : null;
        $peran = $data['peran'];
        $excludeAssignmentId = isset($data['assignment_id']) ? (int) $data['assignment_id'] : null;

        $user = User::findOrFail($userId);
        $activity = Activity::findOrFail($activityId);

        $mulai = ! empty($data['mulai']) ? Carbon::parse($data['mulai'])->startOfDay() : Carbon::parse($activity->mulai)->startOfDay();
        $selesai = ! empty($data['selesai']) ? Carbon::parse($data['selesai'])->startOfDay() : Carbon::parse($activity->selesai)->startOfDay();

        $simulatedPlan = $this->resolveWorkloadForPeriod($data, $mulai, $selesai);
        $totalMenit = $simulatedPlan['total_menit'];
        $jamPerHari = $simulatedPlan['jam_per_hari'];
        $satuan = $simulatedPlan['satuan'];
        $simulatedDistribution = $this->calculateAssignmentMonthlyHoursFromMinutes($totalMenit, $mulai, $selesai);

        $oldAssignment = null;
        $oldDistribution = [];

        if ($excludeAssignmentId !== null) {
            $oldAssignment = Assignment::query()
                ->with('activity')
                ->where('user_id', $userId)
                ->find($excludeAssignmentId);

            if ($oldAssignment && $oldAssignment->activity) {
                $oldActivity = $oldAssignment->activity;
                $oldStart = $oldAssignment->mulai
                    ? Carbon::parse($oldAssignment->mulai)->startOfDay()
                    : Carbon::parse($oldActivity->mulai)->startOfDay();
                $oldEnd = $oldAssignment->selesai
                    ? Carbon::parse($oldAssignment->selesai)->startOfDay()
                    : Carbon::parse($oldActivity->selesai)->startOfDay();

                $oldDistribution = $oldAssignment->total_menit !== null
                    ? $this->calculateAssignmentMonthlyHoursFromMinutes((int) $oldAssignment->total_menit, $oldStart, $oldEnd)
                    : $this->calculateAssignmentMonthlyHours((float) $oldAssignment->jam_per_hari, $oldStart, $oldEnd);
            }
        }

        $dailyWorkHours = $this->getDailyWorkHours();
        $maksPenugasan = (int) Setting::getVal('maks_penugasan_bersamaan', 3);
        $holidays = $this->getHolidayDates();

        $monthlyImpacts = [];
        $warnings = [];
        $hasExceededDaily = false;

        $months = array_keys($simulatedDistribution + $oldDistribution);
        sort($months);

        foreach ($months as $month) {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthStart = $monthDate->copy()->startOfMonth();
            $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();

            $additionalHours = (float) ($simulatedDistribution[$month]['jam'] ?? 0);
            $oldHours = (float) ($oldDistribution[$month]['jam'] ?? 0);
            $deltaHours = round($additionalHours - $oldHours, 2);

            $currentWorkload = $this->calculateMonthlyWorkloadForUser($user, $month);
            $hoursBefore = $currentWorkload['jam_terpakai'];
            $hoursAfter = round(max(0, $hoursBefore + $deltaHours), 2);

            $capacityHours = $currentWorkload['kapasitas_jam'];
            $percentageBefore = $currentWorkload['persentase'];
            $percentageAfter = $capacityHours > 0 ? round(($hoursAfter / $capacityHours) * 100, 2) : 0.0;

            $existingAssignments = Assignment::query()
                ->with('activity')
                ->where('user_id', $userId)
                ->whereHas('activity', fn ($q) => $q->whereNull('deleted_at'))
                ->when($excludeAssignmentId, fn ($q) => $q->where('id', '!=', $excludeAssignmentId))
                ->get();

            // Simulasi beban harian setelah penugasan baru ditambahkan.
            $dailyLoads = [];
            foreach ($existingAssignments as $existing) {
                $eAct = $existing->activity;
                if (! $eAct) {
                    continue;
                }
                $eStart = $existing->mulai ? Carbon::parse($existing->mulai)->startOfDay() : Carbon::parse($eAct->mulai)->startOfDay();
                $eEnd = $existing->selesai ? Carbon::parse($existing->selesai)->startOfDay() : Carbon::parse($eAct->selesai)->startOfDay();

                if ($eStart->lte($monthEnd) && $eEnd->gte($monthStart)) {
                    $this->accumulateDailyLoad($dailyLoads, $eStart, $eEnd, $monthStart, $monthEnd, $this->getAssignmentDailyHours($existing), $holidays);
                }
            }

            $simStart = $mulai->gt($monthStart) ? $mulai : $monthStart;
            $simEnd = $selesai->lt($monthEnd) ? $selesai : $monthEnd;
            $this->accumulateDailyLoad($dailyLoads, $simStart, $simEnd, $monthStart, $monthEnd, $jamPerHari, $holidays);

            $excessDays = [];
            foreach ($dailyLoads as $tanggal => $jam) {
                if ($jam > $dailyWorkHours + 0.0001) {
                    $excessDays[] = ['tanggal' => $tanggal, 'jam' => round($jam, 2)];
                }
            }
            usort($excessDays, fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

            // Simulasi penugasan bersamaan hanya pada hari kerja efektif.
            $dailyCounts = array_fill(1, $monthDate->daysInMonth, 0);
            foreach ($existingAssignments as $existing) {
                $eAct = $existing->activity;
                if (! $eAct) {
                    continue;
                }
                $eStart = $existing->mulai ? Carbon::parse($existing->mulai)->startOfDay() : Carbon::parse($eAct->mulai)->startOfDay();
                $eEnd = $existing->selesai ? Carbon::parse($existing->selesai)->startOfDay() : Carbon::parse($eAct->selesai)->startOfDay();

                $this->countConcurrentDays($dailyCounts, $eStart, $eEnd, $monthStart, $monthEnd, $holidays);
            }

            $this->countConcurrentDays($dailyCounts, $simStart, $simEnd, $monthStart, $monthEnd, $holidays);

            $concurrencyAfter = ! empty($dailyCounts) ? max($dailyCounts) : 1;
            $concurrencyBefore = $currentWorkload['penugasan_bersamaan_maks'];

            if (isset($simulatedDistribution[$month])) {
                if ($excessDays !== []) {
                    $hasExceededDaily = true;
                    $tanggal = array_map(
                        fn ($item) => Carbon::parse($item['tanggal'])->format('d/m/Y').' ('.$item['jam'].' jam)',
                        $excessDays
                    );

                    $warnings[] = [
                        'jenis' => 'melebihi_kapasitas_harian',
                        'bulan' => $month,
                        'pesan' => "Penugasan ini menyebabkan beban harian {$user->nama} melebihi kapasitas {$dailyWorkHours} jam pada: ".implode(', ', $tanggal).'.',
                    ];
                }

                if ($concurrencyAfter >= $maksPenugasan) {
                    $warnings[] = [
                        'jenis' => 'penugasan_bersamaan',
                        'bulan' => $month,
                        'pesan' => "Penugasan ini menyebabkan {$user->nama} memiliki {$concurrencyAfter} penugasan bersamaan pada tanggal yang sama di bulan {$month} (batas: {$maksPenugasan}).",
                    ];
                }
            }

            $monthlyImpacts[] = [
                'bulan' => $month,
                'tambahan_jam' => $deltaHours,
                'hari_kerja' => (int) ($simulatedDistribution[$month]['hari_kerja'] ?? 0),
                'jam_terpakai' => $additionalHours,
                'kapasitas_jam' => $capacityHours,
                'beban_sebelum' => $hoursBefore,
                'beban_sesudah' => $hoursAfter,
                'persentase_sebelum' => $percentageBefore,
                'persentase_sesudah' => $percentageAfter,
                'indikator_sebelum' => $currentWorkload['indikator'],
                'indikator_sesudah' => $this->determineIndicator($percentageAfter),
                'jumlah_penugasan_sebelum' => $currentWorkload['jumlah_penugasan'],
                'jumlah_penugasan_sesudah' => $currentWorkload['jumlah_penugasan']
                    - (isset($oldDistribution[$month]) ? 1 : 0)
                    + (isset($simulatedDistribution[$month]) ? 1 : 0),
                'penugasan_bersamaan_sebelum' => $concurrencyBefore,
                'penugasan_bersamaan_sesudah' => $concurrencyAfter,
                'hari_melebihi_kapasitas' => $excessDays,
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
                'kegiatan_besar' => (bool) $activity->kegiatan_besar,
            ],
            'rencana' => [
                'peran' => $peran,
                'work_type_id' => $simulatedPlan['work_type_id'],
                'norma_menit' => $simulatedPlan['norma_menit'],
                'total_menit' => $totalMenit,
                'jam_per_hari' => $jamPerHari,
                'volume' => $volume,
                'satuan' => $satuan,
                'mulai' => $mulai->format('Y-m-d'),
                'selesai' => $selesai->format('Y-m-d'),
                'total_jam' => $simulatedPlan['total_jam'],
            ],
            'dampak_bulanan' => $monthlyImpacts,
            'peringatan' => $warnings,
            'melebihi_kapasitas_harian' => $hasExceededDaily,
        ];
    }

    /**
     * Pratinjau batch dampak beberapa penugasan dari kegiatan yang belum disimpan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewBatchImpact(array $data): array
    {
        $teamId = (int) $data['team_id'];
        $kegiatanMulai = Carbon::parse($data['mulai'])->startOfDay();
        $kegiatanSelesai = ! empty($data['selesai'])
            ? Carbon::parse($data['selesai'])->startOfDay()
            : $kegiatanMulai->copy();

        $dailyWorkHours = $this->getDailyWorkHours();
        $maksPenugasan = (int) Setting::getVal('maks_penugasan_bersamaan', 3);
        $holidays = $this->getHolidayDates();

        $byUser = [];

        foreach ($data['assignments'] ?? [] as $row) {
            $userId = (int) $row['user_id'];
            $rowMulai = ! empty($row['mulai']) ? Carbon::parse($row['mulai'])->startOfDay() : $kegiatanMulai->copy();
            $rowSelesai = ! empty($row['selesai']) ? Carbon::parse($row['selesai'])->startOfDay() : $kegiatanSelesai->copy();

            $byUser[$userId][] = array_merge($row, [
                'user_id' => $userId,
                'mulai' => $rowMulai,
                'selesai' => $rowSelesai,
                '_workload' => $this->resolveWorkloadForPeriod($row, $rowMulai, $rowSelesai),
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

            $monthlyAdditions = [];

            foreach ($userRows as $row) {
                $totalMenit = (int) $row['_workload']['total_menit'];
                foreach ($this->calculateAssignmentMonthlyHoursFromMinutes($totalMenit, $row['mulai'], $row['selesai']) as $month => $info) {
                    $monthlyAdditions[$month] = round(($monthlyAdditions[$month] ?? 0.0) + $info['jam'], 2);
                }
            }

            ksort($monthlyAdditions);

            $monthlyImpacts = [];
            $warnings = [];

            foreach ($monthlyAdditions as $month => $additionalHours) {
                $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $monthStart = $monthDate->copy()->startOfMonth();
                $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();

                $currentWorkload = $this->calculateMonthlyWorkloadForUser($userId, $month);
                $hoursBefore = $currentWorkload['jam_terpakai'];
                $hoursAfter = round($hoursBefore + $additionalHours, 2);
                $capacityHours = $currentWorkload['kapasitas_jam'];
                $percentageAfter = $capacityHours > 0 ? round(($hoursAfter / $capacityHours) * 100, 2) : 0.0;

                $rowsInMonth = count(array_filter(
                    $userRows,
                    fn (array $row) => $row['mulai']->lte($monthEnd) && $row['selesai']->gte($monthStart)
                ));
                $jumlahSebelum = $currentWorkload['jumlah_penugasan'];
                $jumlahSesudah = $jumlahSebelum + $rowsInMonth;

                $dailyCounts = array_fill(1, $monthDate->daysInMonth, 0);
                $dailyLoads = [];

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

                    $this->countConcurrentDays($dailyCounts, $existingStart, $existingEnd, $monthStart, $monthEnd, $holidays);
                    $this->accumulateDailyLoad($dailyLoads, $existingStart, $existingEnd, $monthStart, $monthEnd, $this->getAssignmentDailyHours($existing), $holidays);
                }

                foreach ($userRows as $row) {
                    $this->countConcurrentDays($dailyCounts, $row['mulai'], $row['selesai'], $monthStart, $monthEnd, $holidays);
                    $this->accumulateDailyLoad($dailyLoads, $row['mulai'], $row['selesai'], $monthStart, $monthEnd, (float) $row['_workload']['jam_per_hari'], $holidays);
                }

                $concurrencyAfter = max($dailyCounts);
                $concurrencyBefore = $currentWorkload['penugasan_bersamaan_maks'];

                $excessDays = [];
                foreach ($dailyLoads as $tanggal => $jam) {
                    if ($jam > $dailyWorkHours + 0.0001) {
                        $excessDays[] = ['tanggal' => $tanggal, 'jam' => round($jam, 2)];
                    }
                }
                usort($excessDays, fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

                if ($excessDays !== []) {
                    $tanggal = array_map(
                        fn ($item) => Carbon::parse($item['tanggal'])->format('d/m/Y').' ('.$item['jam'].' jam)',
                        $excessDays
                    );

                    $warnings[] = [
                        'jenis' => 'melebihi_kapasitas_harian',
                        'bulan' => $month,
                        'pesan' => "Penugasan ini menyebabkan beban harian {$user->nama} melebihi kapasitas {$dailyWorkHours} jam pada: ".implode(', ', $tanggal).'.',
                    ];
                }

                if ($concurrencyAfter >= $maksPenugasan) {
                    $warnings[] = [
                        'jenis' => 'penugasan_bersamaan',
                        'bulan' => $month,
                        'pesan' => "Penugasan ini menyebabkan {$user->nama} memiliki {$concurrencyAfter} penugasan bersamaan pada tanggal yang sama di bulan {$month} (batas: {$maksPenugasan}).",
                    ];
                }

                $monthlyImpacts[] = [
                    'bulan' => $month,
                    'tambahan_jam' => $additionalHours,
                    'kapasitas_jam' => $capacityHours,
                    'beban_sebelum' => $hoursBefore,
                    'beban_sesudah' => $hoursAfter,
                    'persentase_sebelum' => $currentWorkload['persentase'],
                    'persentase_sesudah' => $percentageAfter,
                    'indikator_sebelum' => $currentWorkload['indikator'],
                    'indikator_sesudah' => $this->determineIndicator($percentageAfter),
                    'jumlah_penugasan_sebelum' => $jumlahSebelum,
                    'jumlah_penugasan_sesudah' => $jumlahSesudah,
                    'penugasan_bersamaan_sebelum' => $concurrencyBefore,
                    'penugasan_bersamaan_sesudah' => $concurrencyAfter,
                    'hari_melebihi_kapasitas' => $excessDays,
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
            ],
            'rows' => $results,
        ];
    }

    /**
     * Menambahkan beban jam pada hari kerja dalam rentang tertentu.
     *
     * @param  array<string, float>  $dailyLoads
     * @param  list<string>  $holidays
     */
    private function accumulateDailyLoad(
        array &$dailyLoads,
        CarbonInterface $start,
        CarbonInterface $end,
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
        float $jamPerHari,
        array $holidays
    ): void {
        if ($start->gt($monthEnd) || $end->lt($monthStart)) {
            return;
        }

        $cursor = ($start->gt($monthStart) ? Carbon::parse($start) : Carbon::parse($monthStart))->startOfDay();
        $limit = ($end->lt($monthEnd) ? Carbon::parse($end) : Carbon::parse($monthEnd))->startOfDay();

        while ($cursor->lte($limit)) {
            if ($cursor->isWeekday() && ! in_array($cursor->format('Y-m-d'), $holidays, true)) {
                $key = $cursor->format('Y-m-d');
                $dailyLoads[$key] = round(($dailyLoads[$key] ?? 0.0) + $jamPerHari, 2);
            }
            $cursor->addDay();
        }
    }

    /**
     * Menambah hitungan penugasan bersamaan pada hari kerja efektif per tanggal.
     *
     * @param  array<int, int>  $dailyCounts
     * @param  list<string>  $holidays
     */
    private function countConcurrentDays(array &$dailyCounts, CarbonInterface $start, CarbonInterface $end, CarbonInterface $monthStart, CarbonInterface $monthEnd, array $holidays): void
    {
        if ($start->gt($monthEnd) || $end->lt($monthStart)) {
            return;
        }

        $clampedStart = ($start->gt($monthStart) ? $start : $monthStart)->copy()->startOfDay();
        $clampedEnd = ($end->lt($monthEnd) ? $end : $monthEnd)->copy()->startOfDay();
        $cursor = $clampedStart->copy();

        while ($cursor->lte($clampedEnd)) {
            if ($cursor->isWeekday() && ! in_array($cursor->format('Y-m-d'), $holidays, true)) {
                $dailyCounts[(int) $cursor->format('j')]++;
            }

            $cursor->addDay();
        }
    }
}
