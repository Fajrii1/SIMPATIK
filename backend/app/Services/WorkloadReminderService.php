<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Team;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Layanan notifikasi peringatan beban dan penugasan menumpuk.
 *
 * Mengabdikan peringatan BARU (beban atau penugasan bersamaan) yang muncul
 * setelah perubahan jadwal/penugasan, dengan dedupe berdasarkan notifikasi
 * peringatan_jadwal yang belum dibaca untuk pegawai, bulan, dan jenis yang sama.
 */
class WorkloadReminderService
{
    /**
     * Jenis peringatan yang relevan untuk satu pegawai (bukan tingkat kantor).
     */
    private const JENIS_PER_PEGAWAI = ['beban', 'penugasan_bersamaan'];

    public function __construct(
        protected WorkloadService $workloadService
    ) {}

    /**
     * Daftar bulan (YYYY-MM) yang dilalui antara dua tanggal.
     *
     * @return list<string>
     */
    public function monthsBetween(CarbonInterface|string|null $mulai, CarbonInterface|string|null $selesai): array
    {
        if ($mulai === null && $selesai === null) {
            return [now()->format('Y-m')];
        }

        $start = Carbon::parse($mulai ?? $selesai)->startOfMonth();
        $end = Carbon::parse($selesai ?? $mulai)->startOfMonth();

        if ($start->gt($end)) {
            return [$start->format('Y-m')];
        }

        $months = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Kumpulkan peringatan yang berlaku untuk pengguna tertentu pada bulan tertentu.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $months
     * @return list<array<string, mixed>>
     */
    public function collectWarnings(array $userIds, array $months): array
    {
        $wanted = array_map('intval', $userIds);
        $collected = [];

        foreach ($months as $month) {
            foreach ($this->workloadService->getMonthlyWarnings($month) as $warning) {
                if (! in_array($warning['jenis'] ?? null, self::JENIS_PER_PEGAWAI, true)) {
                    continue;
                }

                $pegawaiId = $warning['pegawai_id'] !== null ? (int) $warning['pegawai_id'] : null;

                if ($pegawaiId === null || ! in_array($pegawaiId, $wanted, true)) {
                    continue;
                }

                $warning['pegawai_id'] = $pegawaiId;
                $collected[] = $warning;
            }
        }

        return $collected;
    }

    /**
     * Kirim notifikasi untuk peringatan yang baru muncul (tidak ada sebelum perubahan).
     *
     * Dedupe: tidak membuat notifikasi baru bila sudah ada notifikasi peringatan_jadwal
     * yang belum dibaca untuk pegawai, bulan, dan jenis yang sama.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $months
     * @param  list<array<string, mixed>>  $beforeWarnings
     */
    public function notifyNewWarnings(array $userIds, array $months, array $beforeWarnings): int
    {
        if ($userIds === [] || $months === []) {
            return 0;
        }

        $beforeKeys = array_map(fn (array $warning) => $this->warningKey($warning), $beforeWarnings);
        $afterWarnings = $this->collectWarnings($userIds, $months);

        $sent = 0;

        foreach ($afterWarnings as $warning) {
            if (in_array($this->warningKey($warning), $beforeKeys, true)) {
                continue;
            }

            $sent += $this->notifyKetuaTim($warning);
        }

        return $sent;
    }

    /**
     * Notifikasi ke ketua tim dari tim tempat pegawai itu menjadi anggota.
     */
    private function notifyKetuaTim(array $warning): int
    {
        $pegawaiId = (int) $warning['pegawai_id'];
        $month = (string) $warning['bulan'];
        $jenis = (string) $warning['jenis'];

        $tautan = "/workload?month={$month}&pegawai_id={$pegawaiId}&jenis={$jenis}";

        $ketuaIds = Team::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $pegawaiId))
            ->whereNotNull('ketua_id')
            ->pluck('ketua_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $sent = 0;

        foreach ($ketuaIds as $ketuaId) {
            $alreadyUnread = Notification::query()
                ->where('user_id', $ketuaId)
                ->where('tipe', 'peringatan_jadwal')
                ->where('tautan', $tautan)
                ->whereNull('dibaca_pada')
                ->exists();

            if ($alreadyUnread) {
                continue;
            }

            Notification::create([
                'user_id' => $ketuaId,
                'tipe' => 'peringatan_jadwal',
                'pesan' => (string) $warning['pesan'],
                'tautan' => $tautan,
            ]);

            $sent++;
        }

        return $sent;
    }

    private function warningKey(array $warning): string
    {
        return ($warning['pegawai_id'] !== null ? (int) $warning['pegawai_id'] : 'office')
            .'|'.$warning['bulan']
            .'|'.$warning['jenis'];
    }
}
