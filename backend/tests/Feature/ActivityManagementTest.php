<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityManagementTest extends TestCase
{
    /**
     * Uji akses tanpa autentikasi ke seluruh endpoint kegiatan ditolak (401).
     */
    public function test_unauthenticated_cannot_access_activity_endpoints(): void
    {
        $this->getJson('/api/activities')->assertStatus(401);
        $this->postJson('/api/activities', [])->assertStatus(401);
        $this->getJson('/api/activities/1')->assertStatus(401);
        $this->putJson('/api/activities/1', [])->assertStatus(401);
        $this->deleteJson('/api/activities/1')->assertStatus(401);
        $this->patchJson('/api/activities/1/complete')->assertStatus(401);
        $this->patchJson('/api/activities/1/reopen')->assertStatus(401);
    }

    /**
     * Uji seluruh pengguna terautentikasi dapat melihat daftar kegiatan dengan filter dan pencarian.
     */
    public function test_all_authenticated_users_can_view_activities(): void
    {
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $this->assertNotNull($pegawai);

        // Bypass must_change_password untuk pengujian otorisasi
        $tempPegawai = User::create([
            'nama' => 'Pegawai Viewer Kegiatan',
            'email' => 'viewer_kegiatan@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($tempPegawai);

            // 1. Ambil daftar default
            $response = $this->getJson('/api/activities');
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'nama',
                            'jenis',
                            'team_id',
                            'mulai',
                            'selesai',
                            'bobot',
                            'status',
                        ],
                    ],
                    'links',
                    'meta',
                ]);

            // 2. Filter berdasarkan status
            $berjalanResponse = $this->getJson('/api/activities?status=berjalan');
            $berjalanResponse->assertStatus(200);

            // 3. Pencarian kata kunci
            $searchResponse = $this->getJson('/api/activities?search=Survei');
            $searchResponse->assertStatus(200);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji Admin dapat membuat kegiatan pada tim mana pun.
     */
    public function test_admin_can_create_activity_for_any_team(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        $this->assertNotNull($team);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/activities', [
            'nama' => 'Survei Uji Admin Baru',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-30',
            'bobot' => 1.25,
            'kegiatan_besar' => true,
            'keterangan' => 'Keterangan uji kegiatan admin',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nama', 'Survei Uji Admin Baru')
            ->assertJsonPath('data.bobot', 1.25)
            ->assertJsonPath('data.kegiatan_besar', true);

        $activityId = $response->json('data.id');
        $created = Activity::find($activityId);
        $this->assertNotNull($created);

        // Verifikasi tercatat di audit_logs (PRD Bagian 8.4)
        $this->assertTrue(AuditLog::where('objek', 'Activity')->where('objek_id', $activityId)->where('aksi', 'create')->exists());

        $created->forceDelete();
    }

    /**
     * Uji pembuatan kegiatan beserta penugasan awal tersimpan atomik dalam satu transaksi.
     */
    public function test_admin_can_create_activity_with_initial_assignments(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        $users = User::where('aktif', true)->orderBy('id')->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $users->count());

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Atomik Dengan Penugasan',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-02',
            'selesai' => '2026-11-20',
            'bobot' => 2,
            'assignments' => [
                [
                    'user_id' => $users[0]->id,
                    'peran' => 'PIC',
                    'volume' => 5,
                    'satuan' => 'Dokumen',
                    'mulai' => '2026-11-02',
                    'selesai' => '2026-11-10',
                ],
                [
                    'user_id' => $users[1]->id,
                    'peran' => 'Organik',
                    'volume' => 3,
                    'satuan' => 'Hari',
                    'mulai' => '2026-11-05',
                    'selesai' => '2026-11-20',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.assignments')
            ->assertJsonPath('data.assignments.0.peran', 'PIC');

        $activityId = (int) $response->json('data.id');

        $this->assertDatabaseHas('assignments', [
            'activity_id' => $activityId,
            'user_id' => $users[0]->id,
            'peran' => 'PIC',
            'satuan' => 'Dokumen',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $users[0]->id,
            'tipe' => 'penugasan_baru',
        ]);
        $this->assertTrue(
            AuditLog::where('objek', 'Assignment')->where('aksi', 'create')->exists()
        );

        Activity::find($activityId)?->forceDelete();
    }

    /**
     * Uji penugasan awal di luar periode kegiatan ditolak dan tidak meninggalkan data setengah jadi.
     */
    public function test_create_activity_rejects_assignment_outside_activity_period(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        $user = User::where('aktif', true)->first();

        Sanctum::actingAs($admin);

        $before = Activity::withTrashed()->count();

        $response = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Penugasan Tidak Valid',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-05',
            'selesai' => '2026-11-10',
            'assignments' => [
                [
                    'user_id' => $user->id,
                    'peran' => 'PIC',
                    'volume' => 1,
                    'satuan' => 'Hari',
                    'mulai' => '2026-11-01',
                    'selesai' => '2026-11-08',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('assignments.0.mulai');

        $this->assertSame($before, Activity::withTrashed()->count());
    }

    /**
     * Uji Ketua Tim hanya dapat membuat kegiatan pada tim yang dipimpinnya.
     */
    public function test_ketua_tim_can_only_create_activity_for_led_team(): void
    {
        $ketua1 = User::where('email', 'ketua1@contoh.test')->first();
        $ledTeam = $ketua1->ledTeams()->first();
        $otherTeam = Team::where('id', '!=', $ledTeam->id)->first();

        // Siapkan user ketua tanpa must_change_password
        $tempKetua = User::create([
            'nama' => 'Ketua Tester Kegiatan',
            'email' => 'ketua_tester_kegiatan@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);
        $ledTeam->update(['ketua_id' => $tempKetua->id]);

        try {
            Sanctum::actingAs($tempKetua);

            // 1. Berhasil membuat pada tim yang dipimpin
            $response = $this->postJson('/api/activities', [
                'nama' => 'Kegiatan Tim Sendiri',
                'jenis' => 'Survei',
                'team_id' => $ledTeam->id,
                'mulai' => '2026-11-01',
                'selesai' => '2026-11-10',
            ]);
            $response->assertStatus(201);
            $activityId = $response->json('data.id');
            Activity::find($activityId)?->forceDelete();

            // 2. Ditolak (403) membuat pada tim lain
            $failResponse = $this->postJson('/api/activities', [
                'nama' => 'Kegiatan Tim Lain',
                'jenis' => 'Survei',
                'team_id' => $otherTeam->id,
                'mulai' => '2026-11-01',
                'selesai' => '2026-11-10',
            ]);
            $failResponse->assertStatus(403);
        } finally {
            $ledTeam->update(['ketua_id' => $ketua1->id]);
            $tempKetua->forceDelete();
        }
    }

    /**
     * Uji pegawai biasa tidak dapat membuat kegiatan (403).
     */
    public function test_regular_pegawai_cannot_create_activity(): void
    {
        $tempPegawai = User::create([
            'nama' => 'Pegawai Biasa Non Ketua',
            'email' => 'pegawai_non_ketua@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        $team = Team::first();

        try {
            Sanctum::actingAs($tempPegawai);

            $response = $this->postJson('/api/activities', [
                'nama' => 'Kegiatan Liar',
                'jenis' => 'Survei',
                'team_id' => $team->id,
                'mulai' => '2026-11-01',
                'selesai' => '2026-11-10',
            ]);

            $response->assertStatus(403);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji validasi tanggal selesai tidak boleh sebelum tanggal mulai kegiatan (PRD Bagian 8.4).
     */
    public function test_activity_date_validation(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Tanggal Salah',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-20',
            'selesai' => '2026-11-10', // Selesai lebih awal dari mulai
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['selesai'])
            ->assertJsonPath('errors.selesai.0', 'Tanggal selesai tidak boleh sebelum tanggal mulai kegiatan.');
    }

    /**
     * Uji penandaan kegiatan selesai (complete) dan buka kembali (reopen) serta pencatatan audit log (PRD Bagian 8.3).
     */
    public function test_complete_and_reopen_activity(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Siklus Selesai',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-30',
            'bobot' => 1.0,
            'selesai_manual' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            // 1. Tandai selesai
            $completeResponse = $this->patchJson("/api/activities/{$activity->id}/complete");
            $completeResponse->assertStatus(200)
                ->assertJsonPath('data.selesai_manual', true)
                ->assertJsonPath('data.status', 'Selesai');

            $activity->refresh();
            $this->assertTrue((bool) $activity->selesai_manual);
            $this->assertNotNull($activity->selesai_pada);

            // 2. Buka kembali
            $reopenResponse = $this->patchJson("/api/activities/{$activity->id}/reopen");
            $reopenResponse->assertStatus(200)
                ->assertJsonPath('data.selesai_manual', false);

            $activity->refresh();
            $this->assertFalse((bool) $activity->selesai_manual);
            $this->assertNull($activity->selesai_pada);
        } finally {
            $activity->forceDelete();
        }
    }

    /**
     * Uji pembaruan jadwal kegiatan memicu notifikasi ke pegawai yang ditugaskan (PRD Bagian 8.6).
     */
    public function test_activity_date_change_triggers_notification(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Survei Uji Notifikasi Jadwal',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-20',
            'bobot' => 1.0,
            'selesai_manual' => false,
        ]);

        $assignment = Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $pegawai->id,
            'peran' => 'Pencacah',
            'volume' => 10,
            'satuan' => 'Dokumen',
        ]);

        try {
            Sanctum::actingAs($admin);

            // Ubah tanggal kegiatan
            $response = $this->putJson("/api/activities/{$activity->id}", [
                'selesai' => '2026-11-25',
            ]);
            $response->assertStatus(200);

            // Verifikasi notifikasi terkirim ke pegawai.
            // Urutkan berdasarkan id agar tidak bergantung pada presisi detik created_at.
            $notification = Notification::where('user_id', $pegawai->id)
                ->where('tipe', 'jadwal_berubah')
                ->latest('id')
                ->first();

            $this->assertNotNull($notification);
            $this->assertStringContainsString('Survei Uji Notifikasi Jadwal', $notification->pesan);
        } finally {
            $assignment->forceDelete();
            $activity->forceDelete();
        }
    }

    /**
     * Uji batas bobot kegiatan 0,50 sampai 3,00 pada pembuatan kegiatan.
     */
    public function test_activity_weight_must_be_between_half_and_three_on_create(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        $this->assertNotNull($admin);
        $this->assertNotNull($team);

        Sanctum::actingAs($admin);

        $basePayload = [
            'nama' => 'Kegiatan Uji Batas Bobot',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-10',
        ];

        $this->postJson('/api/activities', $basePayload + ['bobot' => 0.49])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bobot');

        $this->postJson('/api/activities', $basePayload + ['bobot' => 3.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bobot');

        $lower = $this->postJson('/api/activities', $basePayload + ['bobot' => 0.5]);
        $lower->assertStatus(201)->assertJsonPath('data.bobot', 0.5);

        $upper = $this->postJson('/api/activities', $basePayload + ['bobot' => 3.0]);
        $upper->assertStatus(201)->assertJsonPath('data.bobot', 3);

        Activity::find($lower->json('data.id'))?->forceDelete();
        Activity::find($upper->json('data.id'))?->forceDelete();
    }

    /**
     * Uji batas bobot kegiatan 0,50 sampai 3,00 pada pembaruan kegiatan.
     */
    public function test_activity_weight_must_be_between_half_and_three_on_update(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        $this->assertNotNull($admin);
        $this->assertNotNull($team);

        $activity = Activity::create([
            'nama' => 'Kegiatan Uji Batas Bobot Update',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-10',
            'bobot' => 1.0,
            'selesai_manual' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            $this->putJson("/api/activities/{$activity->id}", ['bobot' => 0.49])
                ->assertStatus(422)
                ->assertJsonValidationErrors('bobot');

            $this->putJson("/api/activities/{$activity->id}", ['bobot' => 3.01])
                ->assertStatus(422)
                ->assertJsonValidationErrors('bobot');

            $this->putJson("/api/activities/{$activity->id}", ['bobot' => 0.5])
                ->assertOk()
                ->assertJsonPath('data.bobot', 0.5);

            $this->putJson("/api/activities/{$activity->id}", ['bobot' => 3.0])
                ->assertOk()
                ->assertJsonPath('data.bobot', 3);
        } finally {
            $activity->forceDelete();
        }
    }
}
