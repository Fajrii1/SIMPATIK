<?php

namespace App\Support;

/**
 * Konstanta terpusat terkait peran penugasan.
 */
final class PeranPenugasan
{
    public const PENANGGUNG_JAWAB = 'Penanggung Jawab';

    public static function isPenanggungJawab(?string $peran): bool
    {
        return $peran === self::PENANGGUNG_JAWAB;
    }
}
