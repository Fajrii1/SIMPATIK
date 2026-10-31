<?php

namespace App\Enums;

enum PeranPengguna: string
{
    case ADMIN = 'admin';
    case PEGAWAI = 'pegawai';
    case PIMPINAN = 'pimpinan';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::PEGAWAI => 'Pegawai',
            self::PIMPINAN => 'Pimpinan',
        };
    }
}
