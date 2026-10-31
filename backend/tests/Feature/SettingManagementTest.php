<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Setting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingManagementTest extends TestCase
{
    /**
     * Uji akses tanpa autentikasi ke endpoint pengaturan sistem ditolak (401).
     */
    public function test_unauthenticated_cannot_access_settings_endpoints(): void
    {
        $this->getJson('/api/settings')->assertStatus(401);
        $this->putJson('/api/settings', [])->assertStatus(401);
    }

    /**
     * Uji seluruh peran terautentikasi (pegawai, pimpinan, admin) dapat melihat pengaturan.
     */
    public function test_all_authenticated_roles_can_view_settings(): void
    {
        $tempPegawai = User::create([
            'nama' => 'Pegawai Viewer Setting',
            'email' => 'pegawai_setting_viewer@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($tempPegawai);

            $response = $this->getJson('/api/settings');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'settings' => [
                        'ambang_beban_bulanan',
                        'maks_penugasan_bersamaan',
                        'maks_kegiatan_besar_bersamaan',
                        'pegawai_boleh_isi_kendala',
                    ],
                    'data' => [
                        '*' => [
                            'id',
                            'kunci',
                            'nilai',
                        ],
                    ],
                ]);

            $this->assertIsInt($response->json('settings.ambang_beban_bulanan'));
            $this->assertIsBool($response->json('settings.pegawai_boleh_isi_kendala'));
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji pengguna non-admin (pegawai & pimpinan) ditolak saat mencoba mengubah pengaturan (403).
     */
    public function test_non_admin_cannot_update_settings(): void
    {
        $tempPegawai = User::create([
            'nama' => 'Pegawai Mutator Setting Fail',
            'email' => 'pegawai_setting_mutator@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($tempPegawai);

            $this->putJson('/api/settings', [
                'ambang_beban_bulanan' => 150,
            ])->assertStatus(403);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji admin dapat memperbarui pengaturan dengan validasi.
     */
    public function test_admin_can_update_settings_with_validation(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        Sanctum::actingAs($admin);

        // 1. Validasi nilai negatif/kurang dari 1
        $this->putJson('/api/settings', [
            'ambang_beban_bulanan' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['ambang_beban_bulanan']);

        // Nilai awal untuk restorasi
        $originalAmbang = Setting::getVal('ambang_beban_bulanan', '100');
        $originalIzin = Setting::getVal('pegawai_boleh_isi_kendala', 'false');

        try {
            // 2. Berhasil memperbarui
            $response = $this->putJson('/api/settings', [
                'ambang_beban_bulanan' => 125,
                'pegawai_boleh_isi_kendala' => true,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('settings.ambang_beban_bulanan', 125)
                ->assertJsonPath('settings.pegawai_boleh_isi_kendala', true);

            $this->assertEquals('125', Setting::getVal('ambang_beban_bulanan'));
            $this->assertEquals('true', Setting::getVal('pegawai_boleh_isi_kendala'));
        } finally {
            // Kembalikan ke pengaturan awal seeder
            Setting::setVal('ambang_beban_bulanan', $originalAmbang);
            Setting::setVal('pegawai_boleh_isi_kendala', $originalIzin);
        }
    }

    /**
     * Uji admin dapat memperbarui pengaturan menggunakan format array settings.
     */
    public function test_admin_can_update_settings_via_array_format(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        Sanctum::actingAs($admin);

        $originalMaks = Setting::getVal('maks_penugasan_bersamaan', '3');

        try {
            $response = $this->putJson('/api/settings', [
                'settings' => [
                    [
                        'kunci' => 'maks_penugasan_bersamaan',
                        'nilai' => 5,
                    ],
                ],
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('settings.maks_penugasan_bersamaan', 5);

            $this->assertEquals('5', Setting::getVal('maks_penugasan_bersamaan'));
        } finally {
            Setting::setVal('maks_penugasan_bersamaan', $originalMaks);
        }
    }
}
