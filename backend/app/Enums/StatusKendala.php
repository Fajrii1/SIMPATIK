<?php

namespace App\Enums;

enum StatusKendala: string
{
    case TERBUKA = 'terbuka';
    case DIPROSES = 'diproses';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::TERBUKA => 'Terbuka',
            self::DIPROSES => 'Diproses',
            self::SELESAI => 'Selesai',
        };
    }
}
