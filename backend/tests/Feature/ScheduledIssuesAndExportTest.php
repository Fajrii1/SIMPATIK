<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Enums\SatuanPenugasan;
use App\Enums\StatusKendala;
use App\Models\Activity;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyIssue;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kontrak Fase 5: perintah notify:due-issues, notifikasi peringatan otomatis,
 * dan ekspor kendala mingguan.
 */
class ScheduledIssuesAndExportTest extends TestCase
{
    protected User $admin;

    protected User $pimpinan;

    protected User $ketuaA;

    protected User $ketuaB;

    protected User $pegawaiA;

    protected User $pegawaiB;

    protected Team $teamA;

    protected Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_fase5@contoh.test'],
            [
                'nama' => 'Admin Fase 5',
                'nip' => '198701012010011201',
                'jabatan' => 'Pranata Komputer',
                'password' => 'password',
                'peran' => PeranPengguna::ADMIN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pimpinan = User::firstOrCreate(
            ['email' => 'pimpinan_fase5@contoh.test'],
            [
                'nama' => 'Pimpinan Fase 5',
                'nip' => '198701012010011202',
                'jabatan' => 'Kepala BPS',
                'password' => 'password',
                'peran' => PeranPengguna::PIMPINAN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->ketuaA = User::firstOrCreate(
            ['email' => 'ketua_a_fase5@contoh.test'],
            [
                'nama' => 'Ketua A Fase 5',
                'nip' => '198701012010011203',
                'jabatan' => 'Statistisi Ahli Muda',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->ketuaB = User::firstOrCreate(
            ['email' => 'ketua_b_fase5@contoh.test'],
            [
                'nama' => 'Ketua B Fase 5',
                'nip' => '198701012010011204',
                'jabatan' => 'Statistisi Ahli Muda',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawaiA = User::firstOrCreate(
            ['email' => 'pegawai_a_fase5@contoh.test'],
            [
                'nama' => 'Pegawai A Fase 5',
                'nip' => '198701012010011205',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawaiB = User::firstOrCreate(
            ['email' => 'pegawai_b_fase5@contoh.test'],
            [
                'nama' => 'Pegawai B Fase 5',
                'nip' => '198701012010011206',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->teamA = Team::firstOrCreate(
            ['nama' => 'Tim Fase 5 A'],
            ['ketua_id' => $this->ketuaA->id]
        );
        $this->teamA->members()->syncWithoutDetaching([$this->ketuaA->id, $this->pegawaiA->id]);

        $this->teamB = Team::firstOrCreate(
            ['nama' => 'Tim Fase 5 B'],
            ['ketua_id' => $this->ketuaB->id]
        );
        $this->teamB->members()->syncWithoutDetaching([$this->ketuaB->id, $this->pegawaiB->id]);
    }

    // ------------------------------------------------------------------
    // 5.1 Perintah notify:due-issues
    // ------------------------------------------------------------------

    public function test_due_issues_command_is_registered(): void
    {
        // Perintah terdaftar dan bisa dipanggil tanpa error.
        Artisan::call('notify:due-issues');

        $this->assertStringContainsString('Notifikasi kendala jatuh tempo', Artisan::output());
    }

    public function test_due_issues_command_notifies_pic_and_ketua_once_per_day(): void
    {
        $this->clearDueNotifications();

        // Kendala terlambat dengan PIC dan ketua tim.
        $issue = $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2026-01-05',
            'kendala' => 'Kendala terlambat Fase 5.',
            'pic_id' => $this->pegawaiA->id,
            'tenggat' => '2026-01-20',
            'status' => StatusKendala::TERBUKA,
        ]);

        // Kendala terlambat tanpa PIC (hanya ketua).
        $issueWithoutPic = $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2026-01-05',
            'kendala' => 'Kendala tanpa PIC Fase 5.',
            'pic_id' => null,
            'tenggat' => '2026-01-20',
            'status' => StatusKendala::DIPROSES,
        ]);

        // Kendala terlambat yang sudah selesai: tidak dinotifikasi.
        $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2026-01-05',
            'kendala' => 'Kendala selesai Fase 5.',
            'pic_id' => $this->pegawaiA->id,
            'tenggat' => '2026-01-20',
            'status' => StatusKendala::SELESAI,
            'catatan_penutupan' => 'Selesai.',
        ]);

        // Kendala tanpa tenggat: diabaikan.
        $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2026-01-05',
            'kendala' => 'Kendala tanpa tenggat Fase 5.',
            'tenggat' => null,
            'status' => StatusKendala::TERBUKA,
        ]);

        // Kendala dengan tenggat di masa depan: tidak dianggap terlambat.
        $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2033-01-05',
            'kendala' => 'Kendala masa depan Fase 5.',
            'tenggat' => '2033-12-31',
            'status' => StatusKendala::TERBUKA,
        ]);

        Artisan::call('notify:due-issues');

        // PIC dan ketua menerima masing-masing satu notifikasi jatuh tempo.
        $this->assertDueNotificationCount($this->pegawaiA->id, $issue->id, 1);
        $this->assertDueNotificationCount($this->ketuaA->id, $issue->id, 1);
        $this->assertDueNotificationCount($this->ketuaA->id, $issueWithoutPic->id, 1);

        // Tidak ada penerima ganda untuk kendala tanpa PIC (hanya ketua).
        $this->assertSame(
            0,
            Notification::where('user_id', $this->pegawaiA->id)
                ->where('tipe', 'kendala_jatuh_tempo')
                ->where('tautan', "/issues?id={$issueWithoutPic->id}")
                ->count()
        );

        // Jalankan lagi: tidak ada notifikasi tambahan hari yang sama.
        Artisan::call('notify:due-issues');

        $this->assertDueNotificationCount($this->pegawaiA->id, $issue->id, 1);
        $this->assertDueNotificationCount($this->ketuaA->id, $issue->id, 1);
        $this->assertDueNotificationCount($this->ketuaA->id, $issueWithoutPic->id, 1);
    }

    public function test_due_issues_command_does_not_duplicate_when_pic_is_ketua(): void
    {
        $this->clearDueNotifications();

        $issue = $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2026-01-05',
            'kendala' => 'PIC ketua Fase 5.',
            'pic_id' => $this->ketuaA->id,
            'tenggat' => '2026-01-20',
            'status' => StatusKendala::TERBUKA,
        ]);

        Artisan::call('notify:due-issues');

        $this->assertDueNotificationCount($this->ketuaA->id, $issue->id, 1);
    }

    // ------------------------------------------------------------------
    // 5.2 Notifikasi peringatan baru setelah perubahan penugasan/kegiatan
    // ------------------------------------------------------------------

    public function test_assignment_creation_notifies_ketua_when_new_beban_warning_appears(): void
    {
        Setting::setVal('ambang_beban_bulanan', 50);

        Notification::whereIn('user_id', [$this->ketuaA->id, $this->ketuaB->id, $this->pegawaiA->id])
            ->where('tipe', 'peringatan_jadwal')
            ->delete();

        $activity = $this->createActivity('Kegiatan Peringatan Fase 5 A', '2034-01-01', '2034-01-31');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $this->pegawaiA->id,
            'peran' => 'Pencacah',
            'volume' => 50,
            'satuan' => SatuanPenugasan::RESPONDEN->value,
        ])->assertCreated();

        // Ketua tim menerima peringatan beban baru untuk pegawaiA pada 2034-01.
        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-01&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );

        // Pegawai tidak menerima notifikasi peringatan.
        $this->assertReminderNotification(
            $this->pegawaiA->id,
            "/workload?month=2034-01&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            0
        );

        // Perubahan berikutnya yang tidak menambah beban baru tidak membuat notifikasi baru.
        $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $this->pegawaiA->id,
            'peran' => 'Pengawas',
            'volume' => 1,
            'satuan' => SatuanPenugasan::RESPONDEN->value,
        ])->assertCreated();

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-01&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );
    }

    public function test_concurrency_warning_notifies_ketua_and_dedupes(): void
    {
        Setting::setVal('maks_penugasan_bersamaan', 2);
        // Pastikan beban tidak ikut memicu peringatan.
        Setting::setVal('ambang_beban_bulanan', 10000);

        Notification::where('tipe', 'peringatan_jadwal')->delete();

        $first = $this->createActivity('Kegiatan Konkir Fase 5 1', '2034-02-01', '2034-02-28');
        $second = $this->createActivity('Kegiatan Konkir Fase 5 2', '2034-02-01', '2034-02-28');
        $third = $this->createActivity('Kegiatan Konkir Fase 5 3', '2034-02-01', '2034-02-28');

        Sanctum::actingAs($this->admin);

        foreach ([$first, $second] as $index => $activity) {
            $this->postJson("/api/activities/{$activity->id}/assignments", [
                'user_id' => $this->pegawaiA->id,
                'peran' => 'Pencacah',
                'volume' => 1,
                'satuan' => SatuanPenugasan::RESPONDEN->value,
            ])->assertCreated();
        }

        // Setelah penugasan kedua, puncak bersamaan mencapai batas: ketua diberi tahu.
        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-02&pegawai_id={$this->pegawaiA->id}&jenis=penugasan_bersamaan",
            1
        );

        // Penugasan ketiga tidak menambah notifikasi baru untuk jenis yang sama.
        $this->postJson("/api/activities/{$third->id}/assignments", [
            'user_id' => $this->pegawaiA->id,
            'peran' => 'Pencacah',
            'volume' => 1,
            'satuan' => SatuanPenugasan::RESPONDEN->value,
        ])->assertCreated();

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-02&pegawai_id={$this->pegawaiA->id}&jenis=penugasan_bersamaan",
            1
        );
    }

    public function test_existing_unread_notification_suppresses_duplicate_notification(): void
    {
        Setting::setVal('ambang_beban_bulanan', 50);

        Notification::where('tipe', 'peringatan_jadwal')->delete();

        // Notifikasi serupa yang belum dibaca sudah ada untuk ketuaA.
        Notification::create([
            'user_id' => $this->ketuaA->id,
            'tipe' => 'peringatan_jadwal',
            'pesan' => 'Notifikasi peringatan yang belum dibaca.',
            'tautan' => "/workload?month=2034-03&pegawai_id={$this->pegawaiA->id}&jenis=beban",
        ]);

        $activity = $this->createActivity('Kegiatan Supresi Fase 5', '2034-03-01', '2034-03-31');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $this->pegawaiA->id,
            'peran' => 'Pencacah',
            'volume' => 50,
            'satuan' => SatuanPenugasan::RESPONDEN->value,
        ])->assertCreated();

        // Tetap satu notifikasi untuk kombinasi pegawai + bulan + jenis.
        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-03&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );
    }

    public function test_both_ketua_are_notified_when_pegawai_belongs_to_two_teams(): void
    {
        Setting::setVal('ambang_beban_bulanan', 50);

        Notification::whereIn('user_id', [$this->ketuaA->id, $this->ketuaB->id])
            ->where('tipe', 'peringatan_jadwal')
            ->delete();

        // pegawaiA juga anggota timB.
        $this->teamB->members()->syncWithoutDetaching([$this->pegawaiA->id]);

        $activity = $this->createActivity('Kegiatan Dua Tim Fase 5', '2034-04-01', '2034-04-30');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $this->pegawaiA->id,
            'peran' => 'Pencacah',
            'volume' => 50,
            'satuan' => SatuanPenugasan::RESPONDEN->value,
        ])->assertCreated();

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-04&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );

        $this->assertReminderNotification(
            $this->ketuaB->id,
            "/workload?month=2034-04&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );
    }

    public function test_activity_creation_with_assignments_notifies_each_teams_ketua(): void
    {
        Setting::setVal('ambang_beban_bulanan', 50);

        Notification::whereIn('user_id', [$this->ketuaA->id, $this->ketuaB->id])
            ->where('tipe', 'peringatan_jadwal')
            ->delete();

        // Kedua tim berbagi kegiatan; masing-masing pegawai menerima beban 50 >= ambang.
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Dua Pegawai Fase 5',
            'jenis' => JenisKegiatan::SURVEI->value,
            'team_id' => $this->teamA->id,
            'mulai' => '2034-05-01',
            'selesai' => '2034-05-31',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Penanggung Jawab',
                    'volume' => 50,
                    'satuan' => SatuanPenugasan::RESPONDEN->value,
                ],
                [
                    'user_id' => $this->pegawaiB->id,
                    'peran' => 'Pencacah',
                    'volume' => 50,
                    'satuan' => SatuanPenugasan::RESPONDEN->value,
                ],
            ],
        ])->assertCreated();

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-05&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );

        $this->assertReminderNotification(
            $this->ketuaB->id,
            "/workload?month=2034-05&pegawai_id={$this->pegawaiB->id}&jenis=beban",
            1
        );
    }

    public function test_assignment_update_that_pushes_over_threshold_notifies_ketua_once(): void
    {
        Setting::setVal('ambang_beban_bulanan', 50);

        Notification::where('tipe', 'peringatan_jadwal')->delete();

        $activity = $this->createActivity('Kegiatan Ubah Fase 5', '2034-06-01', '2034-06-30');

        Sanctum::actingAs($this->admin);

        $created = $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $this->pegawaiA->id,
            'peran' => 'Pencacah',
            'volume' => 20,
            'satuan' => SatuanPenugasan::RESPONDEN->value,
        ]);

        $created->assertCreated();
        $assignmentId = $created->json('data.id');

        Notification::where('tipe', 'peringatan_jadwal')->delete();

        // Perubahan volume menaikkan beban melewati ambang: ketua diberi tahu.
        $this->putJson("/api/assignments/{$assignmentId}", ['volume' => 50])->assertOk();

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-06&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );

        // Perubahan berikutnya tidak menambah notifikasi karena peringatannya sudah ada.
        $this->putJson("/api/assignments/{$assignmentId}", ['volume' => 60])->assertOk();

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-06&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );
    }

    public function test_assignment_deletion_does_not_create_new_notification(): void
    {
        Setting::setVal('ambang_beban_bulanan', 50);

        Notification::where('tipe', 'peringatan_jadwal')->delete();

        $activity = $this->createActivity('Kegiatan Hapus Fase 5', '2034-07-01', '2034-07-31');

        Sanctum::actingAs($this->admin);

        $created = $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $this->pegawaiA->id,
            'peran' => 'Pencacah',
            'volume' => 60,
            'satuan' => SatuanPenugasan::RESPONDEN->value,
        ]);

        $created->assertCreated();
        $assignmentId = $created->json('data.id');

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-07&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );

        // Penghapusan menurunkan beban: tidak ada peringatan baru.
        $this->deleteJson("/api/assignments/{$assignmentId}")->assertOk();

        $this->assertReminderNotification(
            $this->ketuaA->id,
            "/workload?month=2034-07&pegawai_id={$this->pegawaiA->id}&jenis=beban",
            1
        );
    }

    // ------------------------------------------------------------------
    // 5.3 Ekspor kendala
    // ------------------------------------------------------------------

    public function test_export_issues_enforces_role_based_authorization(): void
    {
        $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Ekspor tim A Fase 5.',
            'status' => StatusKendala::TERBUKA,
        ]);

        $this->createIssue([
            'team_id' => $this->teamB->id,
            'pelapor_id' => $this->ketuaB->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Ekspor tim B Fase 5.',
            'status' => StatusKendala::TERBUKA,
        ]);

        // Pegawai biasa (bukan ketua/anggota ekspor) ditolak.
        Sanctum::actingAs($this->pegawaiA);
        $this->get('/api/reports/issues?format=csv')->assertForbidden();

        // Ketua tim: boleh untuk timnya sendiri.
        Sanctum::actingAs($this->ketuaA);
        $this->get('/api/reports/issues?format=csv')->assertOk();
        $this->get('/api/reports/issues?format=csv&team_id='.$this->teamA->id)->assertOk();

        // Ketua tim: ditolak untuk tim yang tidak dipimpinnya.
        $this->get('/api/reports/issues?format=csv&team_id='.$this->teamB->id)->assertForbidden();

        // Admin dan pimpinan: boleh seluruhnya.
        Sanctum::actingAs($this->admin);
        $this->get('/api/reports/issues?format=csv&team_id='.$this->teamB->id)->assertOk();

        Sanctum::actingAs($this->pimpinan);
        $this->get('/api/reports/issues?format=csv&team_id='.$this->teamB->id)->assertOk();
    }

    public function test_export_issues_filters_week_team_and_status(): void
    {
        $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Kendala awal Januari Fase 5.',
            'status' => StatusKendala::TERBUKA,
        ]);

        $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2032-03-02',
            'kendala' => 'Kendala Maret selesai Fase 5.',
            'status' => StatusKendala::SELESAI,
            'catatan_penutupan' => 'Selesai.',
        ]);

        $this->createIssue([
            'team_id' => $this->teamB->id,
            'pelapor_id' => $this->ketuaB->id,
            'minggu_mulai' => '2032-03-02',
            'kendala' => 'Kendala Maret tim B Fase 5.',
            'status' => StatusKendala::SELESAI,
            'catatan_penutupan' => 'Selesai.',
        ]);

        Sanctum::actingAs($this->admin);

        // Filter rentang minggu: hanya Maret.
        $content = $this->get('/api/reports/issues?format=csv&week_from=2032-02-01&week_to=2032-04-30')
            ->streamedContent();

        $this->assertStringContainsString('Kendala Maret selesai Fase 5.', $content);
        $this->assertStringContainsString('Kendala Maret tim B Fase 5.', $content);
        $this->assertStringNotContainsString('Kendala awal Januari Fase 5.', $content);

        // Filter tim: hanya timA.
        $teamContent = $this->get("/api/reports/issues?format=csv&team_id={$this->teamA->id}")
            ->streamedContent();

        $this->assertStringContainsString('Kendala awal Januari Fase 5.', $teamContent);
        $this->assertStringContainsString('Kendala Maret selesai Fase 5.', $teamContent);
        $this->assertStringNotContainsString('Kendala Maret tim B Fase 5.', $teamContent);

        // Filter status: hanya selesai.
        $statusContent = $this->get('/api/reports/issues?format=csv&status=selesai')
            ->streamedContent();

        $this->assertStringContainsString('Kendala Maret selesai Fase 5.', $statusContent);
        $this->assertStringNotContainsString('Kendala awal Januari Fase 5.', $statusContent);

        // Status tidak valid ditolak.
        $this->get('/api/reports/issues?format=csv&status=salah')->assertStatus(422);
    }

    public function test_export_issues_supports_all_formats(): void
    {
        $this->createIssue([
            'team_id' => $this->teamA->id,
            'pelapor_id' => $this->admin->id,
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Format ekspor Fase 5.',
            'status' => StatusKendala::TERBUKA,
        ]);

        Sanctum::actingAs($this->admin);

        $csv = $this->get('/api/reports/issues?format=csv');
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $xlsx = $this->get('/api/reports/issues?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $xlsx->headers->get('content-type')
        );

        $pdf = $this->get('/api/reports/issues?format=pdf');
        $pdf->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $pdf->headers->get('content-type')
        );
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function createIssue(array $attributes): WeeklyIssue
    {
        return WeeklyIssue::create(array_merge([
            'minggu_mulai' => '2032-01-05',
            'kendala' => 'Kendala uji Fase 5.',
            'status' => StatusKendala::TERBUKA,
        ], $attributes));
    }

    private function createActivity(string $nama, string $mulai, string $selesai): Activity
    {
        return Activity::create([
            'nama' => $nama,
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $this->teamA->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'bobot' => 1.0,
        ]);
    }

    private function clearDueNotifications(): void
    {
        Notification::where('tipe', 'kendala_jatuh_tempo')->delete();
    }

    private function assertDueNotificationCount(int $userId, int $issueId, int $expected): void
    {
        $this->assertSame(
            $expected,
            Notification::where('user_id', $userId)
                ->where('tipe', 'kendala_jatuh_tempo')
                ->where('tautan', "/issues?id={$issueId}")
                ->count(),
            "Jumlah notifikasi kendala_jatuh_tempo tidak sesuai (user {$userId}, issue {$issueId})."
        );
    }

    private function assertReminderNotification(int $userId, string $tautan, int $expected): void
    {
        $this->assertSame(
            $expected,
            Notification::where('user_id', $userId)
                ->where('tipe', 'peringatan_jadwal')
                ->where('tautan', $tautan)
                ->count(),
            "Jumlah notifikasi peringatan_jadwal tidak sesuai (user {$userId}, {$tautan})."
        );
    }
}
