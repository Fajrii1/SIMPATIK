<?php

namespace App\Enums;

enum JenisKegiatan: string
{
    case SURVEI = 'Survei';
    case SENSUS = 'Sensus';
    case PENGOLAHAN_DATA = 'Pengolahan Data';
    case DISEMINASI = 'Diseminasi';
    case PEMBINAAN = 'Pembinaan';
    case RAPAT_DAN_KOORDINASI = 'Rapat dan Koordinasi';
    case LAINNYA = 'Lainnya';

    /**
     * Mengambil seluruh nilai jenis kegiatan.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
