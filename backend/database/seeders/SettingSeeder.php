<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'kunci' => 'ambang_beban_bulanan',
                'nilai' => '100',
            ],
            [
                'kunci' => 'maks_penugasan_bersamaan',
                'nilai' => '3',
            ],
            [
                'kunci' => 'maks_kegiatan_besar_bersamaan',
                'nilai' => '3',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['kunci' => $setting['kunci']],
                ['nilai' => $setting['nilai']]
            );
        }

        // Nilai bawaan untuk instalasi baru; nilai yang sudah tersimpan tidak ditimpa.
        Setting::firstOrCreate(
            ['kunci' => 'pegawai_boleh_isi_kendala'],
            ['nilai' => 'true']
        );
    }
}
