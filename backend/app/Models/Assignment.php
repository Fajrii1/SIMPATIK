<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'activity_id',
        'user_id',
        'peran',
        'work_type_id',
        'norma_menit',
        'total_menit',
        'jam_per_hari',
        'volume',
        'satuan',
        'mulai',
        'selesai',
    ];

    protected function casts(): array
    {
        return [
            'jam_per_hari' => 'decimal:2',
            'volume' => 'decimal:2',
            'norma_menit' => 'integer',
            'total_menit' => 'integer',
            'mulai' => 'date',
            'selesai' => 'date',
        ];
    }

    /**
     * Kegiatan pemilik penugasan.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Jenis pekerjaan (norma waktu) yang dipakai saat penugasan dibuat.
     */
    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    /**
     * Pegawai yang menerima penugasan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tanggal efektif mulai penugasan (fallback ke tanggal mulai kegiatan).
     */
    public function getEffectiveMulai(): ?Carbon
    {
        if ($this->mulai) {
            return Carbon::parse($this->mulai);
        }

        return $this->activity?->mulai ? Carbon::parse($this->activity->mulai) : null;
    }

    /**
     * Tanggal efektif selesai penugasan (fallback ke tanggal selesai kegiatan).
     */
    public function getEffectiveSelesai(): ?Carbon
    {
        if ($this->selesai) {
            return Carbon::parse($this->selesai);
        }

        return $this->activity?->selesai ? Carbon::parse($this->activity->selesai) : null;
    }
}
