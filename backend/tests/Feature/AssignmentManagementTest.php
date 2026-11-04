<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Support\PeranPenugasan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentManagementTest extends TestCase
{
    /**
     * Uji akses tanpa autentikasi ke endpoint penugasan ditolak (401).
     */
    public function test_unauthenticated_cannot_access_assignment_endpoints(): void
    {
        $this->getJson('/api/activities/1/assignments')->assertStatus(401);
        $this->getJson('/api/activities/1/assignment-candidates')->assertStatus(401);
        $this->postJson('/api/activities/1/assignments', [])->assertStatus(401);
        $this->getJson('/api/assignments/1')->assertStatus(401);
        $this->putJson('/api/assignments/1', [])->assertStatus(401);
        $this->deleteJson('/api/assignments/1')->assertStatus(401);
        $this->postJson('/api/assignments/preview', [])->assertStatus(401);
    }

    /**
     * Uji seluruh pengguna terautentikasi dapat melihat penugasan pada suatu kegiatan.
     */
    public function test_all_authenticated_users_can_view_activity_assignments(): void
    {
        $tempPegawai = User::create([
            'nama' => 'Pegawai Viewer Penugasan',
            'email' => 'viewer_penugasan@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        $activity = Activity::first();
        $this->assertNotNull($activity);

        try {
            Sanctum::actingAs($tempPegawai);

            $response = $this->getJson("/api/activities/{$activity->id}/assignments");
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'activity_id',
                            'user_id',
                            'peran',
                            'volume',
                            'satuan',
                            'poin_total',
                        ],
                    ],
                ]);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji Admin dan Ketua Tim dapat membuat penugasan dengan validasi periode dan pegawai aktif.
     */
    public function test_admin_and_ketua_can_create_assignment(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Penugasan Test',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-30',
            'bobot' => 1.5,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->postJson("/api/activities/{$activity->id}/assignments", [
                'user_id' => $pegawai->id,
                'peran' => 'Pemeriksa',
                'volume' => 40,
                'satuan' => 'Dokumen',
                'mulai' => '2026-11-05',
                'selesai' => '2026-11-25',
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.peran', 'Pemeriksa')
                ->assertJsonPath('data.volume', 40)
                ->assertJsonPath('data.poin_total', 60); // 40 * 1.5 = 60.0

            $assignmentId = $response->json('data.id');
            $assignment = Assignment::find($assignmentId);
            $this->assertNotNull($assignment);

            // Verifikasi tercatat di audit_logs (PRD Bagian 8.4)

            // Verifikasi notifikasi terkirim ke pegawai yang ditugaskan (PRD Bagian 8.6)
            $this->assertTrue(Notification::where('user_id', $pegawai->id)->where('tipe', 'penugasan_baru')->exists());

            $assignment->forceDelete();
        } finally {
            $activity->forceDelete();
        }
    }

    /**
     * Uji penugasan ditolak jika pegawai berstatus nonaktif (PRD Bagian 8.4).
     */
    public function test_assignment_rejected_for_inactive_pegawai(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $activity = Activity::first();

        $inactivePegawai = User::create([
            'nama' => 'Pegawai Nonaktif Penugasan',
            'email' => 'pegawai_nonaktif_penugasan@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => false,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->postJson("/api/activities/{$activity->id}/assignments", [
                'user_id' => $inactivePegawai->id,
                'peran' => 'Pencacah',
                'volume' => 10,
                'satuan' => 'Dokumen',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id'])
                ->assertJsonPath('errors.user_id.0', 'Pegawai yang ditugaskan harus berstatus aktif.');
        } finally {
            $inactivePegawai->forceDelete();
        }
    }

    /**
     * Uji penugasan di luar periode kegiatan ditolak (PRD Bagian 8.4).
     */
    public function test_assignment_outside_activity_period_is_rejected(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Batas Tanggal',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-05',
            'selesai' => '2026-11-20',
        ]);

        try {
            Sanctum::actingAs($admin);

            // Tanggal penugasan sebelum tanggal mulai kegiatan
            $response = $this->postJson("/api/activities/{$activity->id}/assignments", [
                'user_id' => $pegawai->id,
                'peran' => 'Pencacah',
                'volume' => 10,
                'satuan' => 'Dokumen',
                'mulai' => '2026-11-01', // Di luar batas (kegiatan mulai 2026-11-05)
                'selesai' => '2026-11-15',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['mulai']);
        } finally {
            $activity->forceDelete();
        }
    }

    /**
     * Uji fitur pratinjau penugasan (POST /api/assignments/preview):
     * - Mengembalikan simulasi dampak beban dan peringatan secara akurat
     * - TIDAK menyimpan apa pun ke tabel assignments (database tetap murni).
     */
    public function test_preview_assignment_impact_without_modifying_database(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Pratinjau Test',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-10-01',
            'selesai' => '2026-11-15',
            'bobot' => 1.5,
        ]);

        $initialAssignmentsCount = Assignment::count();

        try {
            Sanctum::actingAs($admin);

            $response = $this->postJson('/api/assignments/preview', [
                'activity_id' => $activity->id,
                'user_id' => $pegawai->id,
                'peran' => 'Pencacah',
                'volume' => 60,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-01',
                'selesai' => '2026-11-15',
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'pegawai',
                        'kegiatan',
                        'rencana' => [
                            'total_poin',
                        ],
                        'dampak_bulanan' => [
                            '*' => [
                                'bulan',
                                'tambahan_poin',
                                'beban_sebelum',
                                'beban_sesudah',
                                'indikator_sesudah',
                            ],
                        ],
                        'peringatan',
                        'melebihi_ambang',
                    ],
                ])
                ->assertJsonPath('data.rencana.total_poin', 90);

            // Verifikasi mutlak: jumlah baris penugasan di database TIDAK berubah
            $this->assertEquals($initialAssignmentsCount, Assignment::count());
        } finally {
            $activity->forceDelete();
        }
    }

    /**
     * Kandidat petugas berasal dari seluruh pegawai aktif pada sistem,
     * bukan hanya anggota tim kegiatan tersebut.
     */
    public function test_assignment_candidates_lists_all_active_users(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Kandidat Petugas Test',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-30',
            'bobot' => 1.0,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->getJson("/api/activities/{$activity->id}/assignment-candidates");

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'nama', 'nip', 'jabatan'],
                    ],
                ]);

            $candidateIds = collect($response->json('data'))->pluck('id');
            $expectedActiveIds = User::where('aktif', true)->pluck('id');

            $this->assertEqualsCanonicalizing(
                $expectedActiveIds->all(),
                $candidateIds->all()
            );
        } finally {
            $activity->forceDelete();
        }
    }

    /**
     * Edit penugasan memperbarui baris yang sama (bukan membuat duplikat)
     * dan menyimpan perubahan pegawai, peran, volume, serta periode.
     */
    public function test_update_assignment_changes_existing_row(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $p1 = User::where('email', 'pegawai01@contoh.test')->first();
        $p2 = User::where('email', 'pegawai02@contoh.test')->first();
        $p3 = User::where('email', 'pegawai03@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Edit Penugasan Test',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-30',
            'bobot' => 1.0,
        ]);

        $activity->assignments()->create([
            'user_id' => $p1->id,
            'peran' => PeranPenugasan::PENANGGUNG_JAWAB,
            'volume' => 5,
            'satuan' => 'Dokumen',
        ]);

        $target = $activity->assignments()->create([
            'user_id' => $p2->id,
            'peran' => 'Anggota',
            'volume' => 10,
            'satuan' => 'Dokumen',
        ]);

        $countBefore = Assignment::where('activity_id', $activity->id)->count();

        try {
            Sanctum::actingAs($admin);

            $response = $this->putJson("/api/assignments/{$target->id}", [
                'user_id' => $p3->id,
                'peran' => 'Pengawas',
                'volume' => 25,
                'satuan' => 'Blok Sensus',
                'mulai' => '2026-11-05',
                'selesai' => '2026-11-20',
            ]);

            $response->assertOk()
                ->assertJsonPath('data.user_id', $p3->id)
                ->assertJsonPath('data.peran', 'Pengawas')
                ->assertJsonPath('data.volume', 25)
                ->assertJsonPath('data.satuan', 'Blok Sensus');

            $this->assertEquals(
                $countBefore,
                Assignment::where('activity_id', $activity->id)->count()
            );
            $this->assertDatabaseHas('assignments', [
                'id' => $target->id,
                'user_id' => $p3->id,
                'peran' => 'Pengawas',
                'volume' => 25,
            ]);
        } finally {
            Assignment::where('activity_id', $activity->id)->forceDelete();
            $activity->forceDelete();
        }
    }

    /**
     * Edit ditolak bila menghasilkan pegawai ganda atau lebih dari satu PJ.
     */
    public function test_update_assignment_rejects_duplicate_user_and_second_pj(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $p1 = User::where('email', 'pegawai01@contoh.test')->first();
        $p2 = User::where('email', 'pegawai02@contoh.test')->first();
        $team = Team::first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Validasi Edit Test',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-11-01',
            'selesai' => '2026-11-30',
            'bobot' => 1.0,
        ]);

        $pj = $activity->assignments()->create([
            'user_id' => $p1->id,
            'peran' => PeranPenugasan::PENANGGUNG_JAWAB,
            'volume' => 5,
            'satuan' => 'Dokumen',
        ]);

        $target = $activity->assignments()->create([
            'user_id' => $p2->id,
            'peran' => 'Anggota',
            'volume' => 10,
            'satuan' => 'Dokumen',
        ]);

        try {
            Sanctum::actingAs($admin);

            // Ganti pegawai menjadi pegawai yang sudah ditugaskan (PJ).
            $this->putJson("/api/assignments/{$target->id}", [
                'user_id' => $p1->id,
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);

            // Jadikan penugasan kedua sebagai PJ -> akan ada dua PJ.
            $this->putJson("/api/assignments/{$target->id}", [
                'peran' => PeranPenugasan::PENANGGUNG_JAWAB,
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['peran']);

            // Ubah PJ menjadi bukan PJ -> kegiatan tidak punya PJ.
            $this->putJson("/api/assignments/{$pj->id}", [
                'peran' => 'Anggota',
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['peran']);
        } finally {
            Assignment::where('activity_id', $activity->id)->forceDelete();
            $activity->forceDelete();
        }
    }
}
