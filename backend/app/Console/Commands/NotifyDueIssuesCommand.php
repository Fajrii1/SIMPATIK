<?php

namespace App\Console\Commands;

use App\Enums\StatusKendala;
use App\Models\Notification;
use App\Models\WeeklyIssue;
use Illuminate\Console\Command;

class NotifyDueIssuesCommand extends Command
{
    /**
     * Tanda kendala jatuh tempo yang dipakai untuk dedupe notifikasi.
     */
    private const TIPE = 'kendala_jatuh_tempo';

    protected $signature = 'notify:due-issues';

    protected $description = 'Kirim notifikasi kendala yang tenggatnya sudah lewat dan belum berstatus selesai (sekali per kendala per hari per penerima).';

    /**
     * Eksekusi perintah.
     */
    public function handle(): int
    {
        $today = now()->startOfDay();

        $issues = WeeklyIssue::with('team')
            ->where('status', '!=', StatusKendala::SELESAI->value)
            ->whereNotNull('tenggat')
            ->whereDate('tenggat', '<', $today)
            ->get();

        $sent = 0;

        foreach ($issues as $issue) {
            $recipientIds = array_unique(array_filter([
                $issue->pic_id,
                $issue->team?->ketua_id,
            ]));

            foreach ($recipientIds as $recipientId) {
                if ($this->alreadyNotifiedToday((int) $recipientId, $issue->id)) {
                    continue;
                }

                Notification::create([
                    'user_id' => (int) $recipientId,
                    'tipe' => self::TIPE,
                    'pesan' => "Kendala \"{$issue->kendala}\" sudah melewati tenggat ({$issue->tenggat->format('d F Y')}) dan belum selesai.",
                    'tautan' => "/issues?id={$issue->id}",
                ]);

                $sent++;
            }
        }

        $this->info("Notifikasi kendala jatuh tempo: {$sent} dikirim, {$issues->count()} kendala diperiksa.");

        return self::SUCCESS;
    }

    /**
     * Periksa apakah penerima sudah diberi notifikasi untuk kendala ini hari ini.
     */
    private function alreadyNotifiedToday(int $userId, int $issueId): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('tipe', self::TIPE)
            ->where('tautan', "/issues?id={$issueId}")
            ->whereDate('created_at', now()->startOfDay())
            ->exists();
    }
}
