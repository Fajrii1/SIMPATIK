<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Enums\StatusKendala;
use App\Models\Activity;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kontrak fitur kendala mingguan pada Fase 4 (Tahap 2).
 */
class WeeklyIssueManagementTest extends TestCase
{
    protected User $admin;

    protected User $ketua;

    protected User $pegawai;

    protected User $otherPegawai;

    protected User $pimpinan;

    protected Team $team;

    protected Team $otherTeam;

    protected Activity $activity;

    protected Activity $otherActivity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_issue_fase4@contoh.test'],
            [
                'nama' => 'Admin Issue Fase 4',
                'nip' => '198701012010011101',
                'jabatan' => 'Pranata Komputer',
                'password' => 'password',
                'peran' => PeranPengguna::ADMIN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->ketua = User::firstOrCreate(
            ['email' => 'ketua_issue_fase4@contoh.test'],
            [
                'nama' => 'Ketua Issue Fase 4',
                'nip' => '198701012010011102',
                'jabatan' => 'Statistisi Ahli Muda',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawai = User::firstOrCreate(
            ['email' => 'pegawai_issue_fase4@contoh.test'],
            [
                'nama' => 'Pegawai Issue Fase 4',
                'nip' => '198701012010011103',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->otherPegawai = User::firstOrCreate(
            ['email' => 'pegawai_luar_issue_fase4@contoh.test'],
            [
                'nama' => 'Pegawai Luar Issue Fase 4',
                'nip' => '198701012010011104',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pimpinan = User::firstOrCreate(
            ['email' => 'pimpinan_issue_fase4@contoh.test'],
            [
                'nama' => 'Pimpinan Issue Fase 4',
                'nip' => '198701012010011105',
                'jabatan' => 'Kepala BPS',
                'password' => 'password',
                'peran' => PeranPengguna::PIMPINAN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->team = Team::firstOrCreate(
            ['nama' => 'Tim Kendala Fase 4'],
            ['ketua_id' => $this->ketua->id]
        );
        $this->team->members()->syncWithoutDetaching([$this->ketua->id, $this->pegawai->id]);

        $this->otherTeam = Team::firstOrCreate(
            ['nama' => 'Tim Luar Kendala Fase 4'],
            ['ketua_id' => null]
        );
        $this->otherTeam->members()->syncWithoutDetaching([$this->otherPegawai->id]);

        $this->activity = Activity::firstOrCreate(
            ['nama' => 'Kegiatan Kendala Fase 4'],
            [
                'team_id' => $this->team->id,
                'jenis' => JenisKegiatan::SURVEI,
                'mulai' => '2032-01-05',
                'selesai' => '2032-01-30',
                'bobot' => 1.0,
            ]
        );

        $this->otherActivity = Activity::firstOrCreate(
            ['nama' => 'Kegiatan Luar Kendala Fase 4'],
            [
                'team_id' => $this->otherTeam->id,
                'jenis' => JenisKegiatan::SURVEI,
                'mulai' => '2032-01-05',
                'selesai' => '2032-01-30',
                'bobot' => 1.0,
            ]
        );
    }

    /**
     * 4.1: Kegiatan harus milik tim yang dipilih.
     */
    public function test_issue_rejects_activity_from_another_team(): void
    {
        Sanctum::actingAs($this->ketua);

        $response = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'activity_id' => $this->otherActivity->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Kegiatan yang dipilih milik tim lain.',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('activity_id');

        $this->assertStringContainsString(
            'Kegiatan yang dipilih bukan milik tim yang dipilih.',
            $response->json('errors.activity_id.0')
        );

        // Kegiatan satu tim tetap diterima.
        $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'activity_id' => $this->activity->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Kegiatan yang dipilih satu tim.',
        ])->assertCreated();
    }

    /**
     * 4.1: PIC harus pengguna aktif yang menjadi anggota tim yang dipilih.
     */
    public function test_issue_rejects_inactive_or_non_member_pic(): void
    {
        Sanctum::actingAs($this->ketua);

        // PIC bukan anggota tim yang dipilih.
        $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'PIC bukan anggota tim.',
            'pic_id' => $this->otherPegawai->id,
        ])->assertStatus(422)->assertJsonValidationErrors('pic_id');

        // PIC anggota tim tetapi tidak aktif.
        $inactivePic = User::create([
            'nama' => 'PIC Nonaktif Fase 4',
            'email' => 'pic_nonaktif_fase4@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => false,
            'must_change_password' => false,
        ]);
        $this->team->members()->syncWithoutDetaching([$inactivePic->id]);

        $response = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'PIC anggota tetapi tidak aktif.',
            'pic_id' => $inactivePic->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('pic_id');
        $this->assertStringContainsString(
            'PIC harus merupakan pengguna aktif yang menjadi anggota tim yang dipilih.',
            $response->json('errors.pic_id.0')
        );

        // PIC aktif anggota tim tetap diterima.
        $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'PIC anggota tim yang aktif.',
            'pic_id' => $this->pegawai->id,
        ])->assertCreated();
    }

    /**
     * 4.3: Detail kendala memuat seluruh field dan riwayat status.
     */
    public function test_issue_detail_returns_complete_fields_and_history(): void
    {
        Sanctum::actingAs($this->ketua);

        $create = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'activity_id' => $this->activity->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Detail kendala kontrol Fase 4.',
            'solusi' => 'Solusi terinci untuk diverifikasi.',
            'tindak_lanjut' => 'Tindak lanjut terinci untuk diverifikasi.',
            'pic_id' => $this->pegawai->id,
            'tenggat' => '2032-01-20',
        ]);

        $create->assertCreated();
        $issueId = $create->json('data.id');

        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::DIPROSES->value,
            'tindak_lanjut' => 'Sudah ditangani tim lapangan.',
        ])->assertOk();

        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::SELESAI->value,
            'catatan_penutupan' => 'Kendala selesai ditangani dan diverifikasi.',
        ])->assertOk();

        Sanctum::actingAs($this->pegawai);
        $response = $this->getJson("/api/issues/{$issueId}");

        $response->assertOk();

        $response->assertJsonPath('data.kendala', 'Detail kendala kontrol Fase 4.');
        $response->assertJsonPath('data.solusi', 'Solusi terinci untuk diverifikasi.');
        // Status patch memakai kolom tindak_lanjut yang sama, sehingga nilai create tertimpa.
        $response->assertJsonPath('data.tindak_lanjut', 'Sudah ditangani tim lapangan.');
        $response->assertJsonPath('data.catatan_penutupan', 'Kendala selesai ditangani dan diverifikasi.');
        $response->assertJsonPath('data.status', 'selesai');
        $response->assertJsonPath('data.team.nama', 'Tim Kendala Fase 4');
        $response->assertJsonPath('data.pelapor.nama', 'Ketua Issue Fase 4');
        $response->assertJsonPath('data.pic.nama', 'Pegawai Issue Fase 4');
        $response->assertJsonPath('data.kegiatan.nama', 'Kegiatan Kendala Fase 4');
        $response->assertJsonPath('data.minggu_mulai', '2032-01-05');
        $response->assertJsonPath('data.tenggat', '2032-01-20');
        $this->assertNotNull($response->json('data.ditutup_pada'));

        $history = $response->json('data.history');

        $this->assertIsArray($history);
    }

    /**
     * 4.3: Otorisasi detail kendala (Lihat) berlaku untuk admin, pimpinan, ketua terkait, pelapor, atau PIC.
     */
    public function test_issue_detail_authorization_rules(): void
    {
        Sanctum::actingAs($this->ketua);

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Otorisasi detail kendala Fase 4.',
            'pic_id' => $this->pegawai->id,
        ]);

        $created->assertCreated();
        $issueId = $created->json('data.id');

        // Admin dapat melihat.
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/issues/{$issueId}")->assertOk();

        // Pimpinan dapat melihat.
        Sanctum::actingAs($this->pimpinan);
        $this->getJson("/api/issues/{$issueId}")->assertOk();

        // Ketua tim terkait dapat melihat.
        Sanctum::actingAs($this->ketua);
        $this->getJson("/api/issues/{$issueId}")->assertOk();

        // PIC dapat melihat.
        Sanctum::actingAs($this->pegawai);
        $this->getJson("/api/issues/{$issueId}")->assertOk();

        // Pegawai bukan anggota tim dan bukan pihak yang terlibat ditolak.
        Sanctum::actingAs($this->otherPegawai);
        $this->getJson("/api/issues/{$issueId}")->assertForbidden();
    }

    /**
     * 4.4: Edit dan hapus kendala mengikuti aturan otorisasi, bukan diperluas.
     */
    public function test_issue_edit_and_delete_follow_authorization_rules(): void
    {
        Sanctum::actingAs($this->ketua);

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Otorisasi edit dan hapus Fase 4.',
            'pic_id' => $this->pegawai->id,
        ]);

        $created->assertCreated();
        $issueId = $created->json('data.id');

        // Pegawai yang bukan pelapor/PIC ditolak.
        Sanctum::actingAs($this->otherPegawai);
        $this->putJson("/api/issues/{$issueId}", [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Ubah dari pihak lain.',
        ])->assertForbidden();

        $this->deleteJson("/api/issues/{$issueId}")->assertForbidden();

        // PIC yang bukan pelapor ditolak mengubah isi kendala.
        Sanctum::actingAs($this->pegawai);
        $this->putJson("/api/issues/{$issueId}", [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Ubah oleh PIC.',
        ])->assertForbidden();

        // Pelapor (ketua, bukan admin/ketua lain) dapat mengubah isi sebelum selesai.
        Sanctum::actingAs($this->ketua);
        $this->putJson("/api/issues/{$issueId}", [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Konten diubah oleh pelapor.',
        ])->assertOk();

        // Hapus hanya admin atau ketua tim terkait.
        Sanctum::actingAs($this->pimpinan);
        $this->deleteJson("/api/issues/{$issueId}")->assertForbidden();

        Sanctum::actingAs($this->ketua);
        $this->deleteJson("/api/issues/{$issueId}")->assertOk();
    }

    /**
     * 4.5: Kendala selesai terkunci; admin/ketua dapat membuka kembali, PIC tidak.
     */
    public function test_closed_issue_is_locked_and_pic_cannot_reopen(): void
    {
        Sanctum::actingAs($this->ketua);

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Kendala dikunci saat selesai Fase 4.',
            'pic_id' => $this->pegawai->id,
        ]);

        $created->assertCreated();
        $issueId = $created->json('data.id');

        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::SELESAI->value,
            'catatan_penutupan' => 'Kendala selesai dan dikunci.',
        ])->assertOk();

        // Ketua tim sekaligus pelapor tetap diizinkan mengubah isi kendala selesai.
        Sanctum::actingAs($this->ketua);
        $this->putJson("/api/issues/{$issueId}", [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Ubah pelapor saat terkunci.',
        ])->assertOk();

        // PIC ditolak mengubah isi kendala selesai.
        Sanctum::actingAs($this->pegawai);
        $payload = [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Ubah PIC saat terkunci.',
        ];
        $blockedUpdate = $this->putJson("/api/issues/{$issueId}", $payload);

        $blockedUpdate->assertStatus(422);
        $this->assertStringContainsString(
            'Kendala yang sudah selesai tidak dapat diubah.',
            $blockedUpdate->json('message')
        );

        // PIC ditolak membuka kembali kendala selesai.
        $blockedStatus = $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::DIPROSES->value,
        ]);

        $blockedStatus->assertStatus(422);
        $this->assertStringContainsString(
            'PIC tidak dapat membuka kembali kendala yang sudah selesai.',
            $blockedStatus->json('message')
        );

        // Admin dapat membuka kembali; ditutup_pada dikosongkan.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::DIPROSES->value,
            'tindak_lanjut' => 'Dibuka kembali oleh admin.',
        ])->assertOk()->assertJsonPath('data.status', 'diproses')
            ->assertJsonPath('data.ditutup_pada', null);

        // Ketua tim juga dapat menutup kembali setelah diproses.
        Sanctum::actingAs($this->ketua);
        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::SELESAI->value,
            'catatan_penutupan' => 'Ditutup kembali oleh ketua tim.',
        ])->assertOk()->assertJsonPath('data.status', 'selesai');
    }

    /**
     * 4.5: Pelapor non-admin dan bukan ketua tim juga terkunci saat kendala selesai.
     */
    public function test_closed_issue_rejects_ordinary_reporter(): void
    {
        Sanctum::actingAs($this->pegawai);

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Kendala laporan anggota tim Fase 4.',
        ]);

        $created->assertCreated();
        $issueId = $created->json('data.id');

        // Sebelum terkunci, pelapor dapat mengubah kendala.
        $this->putJson("/api/issues/{$issueId}", [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Kendala laporan anggota tim Fase 4 (diubah).',
        ])->assertOk();

        Sanctum::actingAs($this->ketua);
        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::SELESAI->value,
            'catatan_penutupan' => 'Ditutup oleh ketua tim.',
        ])->assertOk();

        Sanctum::actingAs($this->pegawai);

        $this->putJson("/api/issues/{$issueId}", [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Ubah pelapor biasa saat terkunci.',
        ])->assertStatus(422);

        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::DIPROSES->value,
        ])->assertStatus(422);
    }

    /**
     * 4.6: Notifikasi perubahan status dikirim ke pelapor, PIC, dan ketua tim tanpa duplikat dan tanpa aktor.
     */
    public function test_status_change_notifies_participants_with_capped_catatan(): void
    {
        Notification::whereIn('user_id', [
            $this->admin->id,
            $this->ketua->id,
            $this->pegawai->id,
        ])->where('tipe', 'kendala_status')->delete();

        Sanctum::actingAs($this->admin);

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Pemberitahuan status Fase 4.',
            'pic_id' => $this->pegawai->id,
        ]);

        $created->assertCreated();
        $issueId = $created->json('data.id');

        $longCatatan = str_repeat('catatan panjang ', 20); // 320 karakter
        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::SELESAI->value,
            'catatan_penutupan' => $longCatatan,
        ])->assertOk();

        $catatanCount = 0;
        $expectedPrefix = mb_substr(trim($longCatatan), 0, 120);

        foreach ([$this->ketua, $this->pegawai] as $recipient) {
            $notifications = Notification::where('user_id', $recipient->id)
                ->where('tipe', 'kendala_status')
                ->get();

            $this->assertCount(
                1,
                $notifications,
                "Penerima {$recipient->nama} harus menerima tepat satu notifikasi status."
            );

            $notification = $notifications->first();

            $this->assertStringContainsString('/issues?id='.$issueId, $notification->tautan);
            $this->assertStringContainsString('Selesai', $notification->pesan);
            $this->assertStringContainsString($expectedPrefix, $notification->pesan);
            $this->assertStringNotContainsString($longCatatan, $notification->pesan);

            $catatanCount++;
        }

        $this->assertSame(2, $catatanCount);

        // Aktor (admin, pelapor) tidak menerima notifikasi status miliknya.
        $this->assertFalse(
            Notification::where('user_id', $this->admin->id)
                ->where('tipe', 'kendala_status')
                ->exists()
        );
    }

    /**
     * 4.6: Notifikasi status tidak ganda ketika satu orang merangkap pelapor dan PIC.
     */
    public function test_status_change_does_not_duplicate_notification_for_same_person(): void
    {
        Notification::where('tipe', 'kendala_status')->delete();

        // Pegawai adalah pelapor sekaligus PIC pada timnya.
        Sanctum::actingAs($this->pegawai);

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Satu orang pelapor sekaligus PIC Fase 4.',
            'pic_id' => $this->pegawai->id,
        ]);

        $created->assertCreated();
        $issueId = $created->json('data.id');

        Notification::where('tipe', 'kendala_status')->delete();

        Sanctum::actingAs($this->ketua);
        $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::DIPROSES->value,
        ])->assertOk();

        // Pelapor = PIC: tepat satu notifikasi, bukan dua.
        $this->assertSame(
            1,
            Notification::where('user_id', $this->pegawai->id)
                ->where('tipe', 'kendala_status')
                ->count()
        );

        // Ketua adalah aktor dan tidak menerima notifikasi.
        $this->assertSame(
            0,
            Notification::where('user_id', $this->ketua->id)
                ->where('tipe', 'kendala_status')
                ->count()
        );
    }

    /**
     * 4.6: PIC yang baru ditunjuk menerima notifikasi saat kendala diubah.
     */
    public function test_reassigning_pic_notifies_the_new_pic(): void
    {
        Notification::whereIn('user_id', [$this->otherPegawai->id, $this->pegawai->id])
            ->where('tipe', 'kendala_pic')
            ->delete();

        $this->team->members()->syncWithoutDetaching([$this->otherPegawai->id]);

        Sanctum::actingAs($this->admin);

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Penunjukan ulang PIC Fase 4.',
            'pic_id' => $this->pegawai->id,
        ]);

        $created->assertCreated();
        $issueId = $created->json('data.id');

        Notification::where('tipe', 'kendala_pic')->delete();

        $this->putJson("/api/issues/{$issueId}", [
            'team_id' => $this->team->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Penunjukan ulang PIC Fase 4.',
            'pic_id' => $this->otherPegawai->id,
        ])->assertOk();

        $notifications = Notification::where('user_id', $this->otherPegawai->id)
            ->where('tipe', 'kendala_pic')
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertStringContainsString('/issues?id='.$issueId, $notifications->first()->tautan);
    }
}
