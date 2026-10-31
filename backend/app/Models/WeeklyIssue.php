<?php

namespace App\Models;

use App\Enums\StatusKendala;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyIssue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'activity_id',
        'team_id',
        'pelapor_id',
        'minggu_mulai',
        'kendala',
        'solusi',
        'tindak_lanjut',
        'pic_id',
        'tenggat',
        'status',
        'catatan_penutupan',
        'ditutup_pada',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusKendala::class,
            'minggu_mulai' => 'date',
            'tenggat' => 'date',
            'ditutup_pada' => 'datetime',
        ];
    }

    /**
     * Kegiatan terkait (opsional).
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Tim pemilik kendala.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Pengguna yang melaporkan kendala.
     */
    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelapor_id');
    }

    /**
     * Pegawai yang menjadi PIC tindak lanjut kendala.
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    /**
     * Memeriksa apakah kendala telah melewati tenggat waktu (jatuh tempo).
     */
    public function isOverdue(): bool
    {
        if (! $this->tenggat || $this->status === StatusKendala::SELESAI) {
            return false;
        }

        return Carbon::parse($this->tenggat)->startOfDay()->lt(Carbon::today());
    }

    /**
     * Scope untuk mengambil daftar kendala yang terbawa dari minggu sebelumnya (PRD Bagian 8.5).
     */
    public function scopeCarriedOver(Builder $query, string $weekMonday): Builder
    {
        return $query->where('minggu_mulai', '<', $weekMonday)
            ->where('status', '!=', StatusKendala::SELESAI->value);
    }
}
