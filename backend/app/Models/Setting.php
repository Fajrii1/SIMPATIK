<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'kunci',
        'nilai',
    ];

    /**
     * Mengambil nilai pengaturan berdasarkan kunci.
     */
    public static function getVal(string $kunci, mixed $default = null): mixed
    {
        $setting = static::where('kunci', $kunci)->first();

        return $setting ? $setting->nilai : $default;
    }

    /**
     * Menyimpan atau memperbarui nilai pengaturan.
     */
    public static function setVal(string $kunci, mixed $nilai): void
    {
        static::updateOrCreate(
            ['kunci' => $kunci],
            ['nilai' => (string) $nilai]
        );
    }
}
