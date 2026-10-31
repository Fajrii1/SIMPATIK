<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipe',
        'pesan',
        'tautan',
        'dibaca_pada',
    ];

    protected function casts(): array
    {
        return [
            'dibaca_pada' => 'datetime',
        ];
    }

    /**
     * Pengguna penerima notifikasi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope untuk notifikasi yang belum dibaca.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('dibaca_pada');
    }

    /**
     * Status sudah dibaca.
     */
    public function isRead(): bool
    {
        return $this->dibaca_pada !== null;
    }
}
