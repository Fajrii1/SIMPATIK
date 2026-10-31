<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'aksi',
        'objek',
        'objek_id',
        'sebelum',
        'sesudah',
    ];

    protected function casts(): array
    {
        return [
            'sebelum' => 'array',
            'sesudah' => 'array',
            'objek_id' => 'integer',
        ];
    }

    /**
     * Pengguna yang melakukan mutasi (aktor). Null bila dipicu sistem.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
