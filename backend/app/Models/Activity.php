<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama',
        'jenis',
        'team_id',
        'mulai',
        'selesai',
        'bobot',
        'kegiatan_besar',
        'selesai_manual',
        'selesai_pada',
        'keterangan',
    ];

    protected $appends = [
        'status',
    ];

    protected function casts(): array
    {
        return [
            'mulai' => 'date',
            'selesai' => 'date',
            'bobot' => 'decimal:2',
            'kegiatan_besar' => 'boolean',
            'selesai_manual' => 'boolean',
            'selesai_pada' => 'datetime',
        ];
    }

    /**
     * Tim penanggung jawab kegiatan.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Penugasan pegawai pada kegiatan ini.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Kendala mingguan yang terkait langsung dengan kegiatan ini.
     */
    public function weeklyIssues(): HasMany
    {
        return $this->hasMany(WeeklyIssue::class);
    }

    /**
     * Accessor status kegiatan dinamis (PRD Bagian 8.3).
     * Nilai: 'Selesai', 'Belum mulai', 'Berjalan', 'Terlambat'.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->selesai_manual) {
                    return 'Selesai';
                }

                $today = Carbon::today();
                $mulai = Carbon::parse($this->mulai)->startOfDay();
                $selesai = Carbon::parse($this->selesai)->endOfDay();

                if ($today->lt($mulai)) {
                    return 'Belum mulai';
                }

                if ($today->gt($selesai)) {
                    return 'Terlambat';
                }

                return 'Berjalan';
            }
        );
    }
}
