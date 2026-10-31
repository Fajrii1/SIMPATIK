<?php

namespace App\Enums;

enum SatuanPenugasan: string
{
    case DOKUMEN = 'Dokumen';
    case BLOK_SENSUS = 'Blok Sensus';
    case RESPONDEN = 'Responden';
    case HARI = 'Hari';
    case LAPORAN = 'Laporan';
    case LAINNYA = 'Lainnya';

    /**
     * Mengambil seluruh nilai satuan penugasan.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
