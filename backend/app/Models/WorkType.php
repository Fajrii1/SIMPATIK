<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkType extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'satuan',
        'norma_menit',
        'jenis_norma',
        'sumber',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'norma_menit' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    /**
     * Penugasan yang memakai jenis pekerjaan ini.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Jenis pekerjaan yang masih dapat dipilih pada form.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
