<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Enums\SatuanPenugasan;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regresi stabilisasi pasca-implementasi:
 * detail beban kerja, poin dashboard, peringatan saat edit kegiatan,
 * riwayat perubahan kendala, dan visibilitas metrik penugasan.
 */
class StabilizationRegressionTest extends TestCase
{
    protected User $admin;

    protected User $pimpinan;

    protected User $ketua;

    protected User $pegawai;

    protected User $rekan;

    protected Team $team;

    protected int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('Admin Stabilisasi', PeranPengguna::ADMIN);
        $this->pimpinan = $this->makeUser('Pimpinan Stabilisasi', PeranPengguna::PIMPINAN);
        $this->ketua = $this->makeUser('Ketua Stabilisasi', PeranPengguna::PEGAWAI);
        $this->pegawai = $this->makeUser('Pegawai Stabilisasi', PeranPengguna::PEGAWAI);
        $this->rekan = $this->makeUser('Rekan Stabilisasi', PeranPengguna::PEGAWAI);

        $this->team = Team::create([
            'nama' => 'Tim Stabilisasi',
            'ketua_id' => $this->ketua->id,
        ]);

        $this->team->members()->syncWithoutDetaching([
            $this->ketua->id,
            $this->pegawai->id,
            $this->rekan->id,
        ]);
    }

    /**
     * Penugasan yang membentang melewati seluruh jendela tetap muncul pada detail beban kerja.
     */
    public function test_user_detail_includes_assignment_spanning_the_whole_window(): void
    {
        $activity = $this->makeActivity('Membentang Jendela', '2033-12-01', '2034-04-30');
        $assignment = $this->makeAssignment($activity, $this->pegawai, 2);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/workload/users/{$this->pegawai->id}?month=2034-02&per_page=50");

        $response->assertOk();

        $row = collect($response->json('penugasan'))->firstWhere('assignment_id', $assignment->id);
        $this->assertNotNull($row, 'Penugasan yang membentang melewati jendela harus tetap muncul.');
        $this->assertSame('2033-12-01', $row['mulai']);
        $this->assertSame('2034-04-30', $row['selesai']);
        $this->assertSame(2.0, (float) $row['jam_per_hari']);

        // Beban bulan yang dipilih ikut menghitung penugasan yang membentang.
        $this->assertGreaterThan(0.0, (float) $response->json('jam_terpakai'));
    }

    /**
     * Kegiatan yang di-soft-delete tidak ditampilkan dan tidak menyebabkan error 500.
     */
    public function test_user_detail_skips_assignments_from_soft_deleted_activity(): void
    {
        $activity = $this->makeActivity('Dihapus', '2034-02-01', '2034-02-28');
        $assignment = $this->makeAssignment($activity, $this->pegawai, 2, '2034-02-05', '2034-02-10');
        $activity->delete();

        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/workload/users/{$this->pegawai->id}?month=2034-02");

        $response->assertOk();
        $this->assertNull(
            collect($response->json('penugasan'))->firstWhere('assignment_id', $assignment->id),
            'Penugasan dari kegiatan terhapus tidak boleh tampil.'
        );
    }

    /**
     * Jam per hari pada agenda dashboard sama dengan data penugasan.
     */
    public function test_dashboard_active_assignment_hours_match_assignment(): void
    {
        $today = Carbon::today();
        $activity = $this->makeActivity(
            'Agenda Dashboard',
            $today->copy()->subDays(3)->toDateString(),
            $today->copy()->addDays(3)->toDateString()
        );
        $assignment = $this->makeAssignment($activity, $this->pegawai, 4);

        Sanctum::actingAs($this->pegawai);
        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $row = collect($response->json('data.penugasan_aktif'))->firstWhere('id', $assignment->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(4.0, (float) $row['jam_per_hari'], 0.001);
    }

    /**
     * Edit tanggal kegiatan yang memicu tiga penugasan bersamaan mengirim peringatan ke ketua tim.
     */
    public function test_activity_date_update_triggers_concurrency_warning_once(): void
    {
        $activityA = $this->makeActivity('Bersamaan A', '2035-03-01', '2035-03-31');
        $activityB = $this->makeActivity('Bersamaan B', '2035-03-01', '2035-03-10');
        $activityD = $this->makeActivity('Bersamaan D', '2035-04-01', '2035-04-10');

        $this->makeAssignment($activityA, $this->pegawai, 1);
        $this->makeAssignment($activityB, $this->pegawai, 1);
        $this->makeAssignment($activityD, $this->pegawai, 1);

        Sanctum::actingAs($this->admin);

        $notificationCount = Notification::where('user_id', $this->ketua->id)
            ->where('tipe', 'peringatan_jadwal')
            ->count();

        $this->putJson("/api/activities/{$activityD->id}", [
            'mulai' => '2035-03-05',
            'selesai' => '2035-03-15',
        ])->assertOk();

        $warnings = Notification::where('user_id', $this->ketua->id)
            ->where('tipe', 'peringatan_jadwal')
            ->get();

        $this->assertGreaterThan($notificationCount, $warnings->count(), 'Peringatan baru harus dikirim ke ketua tim.');
        $this->assertTrue(
            $warnings->contains(fn (Notification $n) => str_contains((string) $n->tautan, '2035-03')),
            'Tautan peringatan harus menunjuk bulan yang memicu tumpang tindih.'
        );

        // Perubahan nama tidak memicu peringatan baru.
        $afterMove = Notification::where('user_id', $this->ketua->id)
            ->where('tipe', 'peringatan_jadwal')
            ->count();

        $this->putJson("/api/activities/{$activityD->id}", [
            'nama' => 'Bersamaan D Diubah Nama',
        ])->assertOk();

        $this->assertSame(
            $afterMove,
            Notification::where('user_id', $this->ketua->id)->where('tipe', 'peringatan_jadwal')->count()
        );

        // Menyimpan tanggal yang sama dua kali tidak menduplikasi notifikasi.
        $this->putJson("/api/activities/{$activityD->id}", [
            'mulai' => '2035-03-05',
            'selesai' => '2035-03-15',
        ])->assertOk();

        $this->assertSame(
            $afterMove,
            Notification::where('user_id', $this->ketua->id)->where('tipe', 'peringatan_jadwal')->count()
        );
    }

    /**
     * Metrik penugasan hanya terlihat oleh peran yang berhak, pada detail dan daftar kegiatan.
     */
    public function test_activity_assignment_metrics_visibility_by_role(): void
    {
        $activity = $this->makeActivity('Visibilitas Metrik', '2035-06-01', '2035-06-30');
        $this->makeAssignment($activity, $this->pegawai, 2);

        $expectedVisible = [
            'admin' => $this->admin,
            'ketua' => $this->ketua,
            'pemilik' => $this->pegawai,
        ];

        foreach ($expectedVisible as $label => $viewer) {
            Sanctum::actingAs($viewer);
            $response = $this->getJson("/api/activities/{$activity->id}");
            $response->assertOk();

            $assignment = $response->json('data.assignments.0');
            $this->assertNotNull($assignment, "Penugasan harus tampil untuk {$label}.");
            $this->assertTrue($assignment['metrics_visible'], "Metrik harus terlihat oleh {$label}.");
            $this->assertEqualsWithDelta(2.0, (float) $assignment['jam_per_hari'], 0.001);
            $this->assertEqualsWithDelta(10.0, (float) $assignment['volume'], 0.001);
            $this->assertSame('Dokumen', $assignment['satuan']);
        }

        // Pimpinan hanya memantau dashboard dan monitoring, tidak membuka kegiatan.
        Sanctum::actingAs($this->pimpinan);
        $this->getJson("/api/activities/{$activity->id}")->assertForbidden();

        // Pegawai lain (bukan pemilik, bukan ketua tim kegiatan) tidak melihat metrik.
        Sanctum::actingAs($this->rekan);

        $detail = $this->getJson("/api/activities/{$activity->id}");
        $detail->assertOk();
        $assignment = $detail->json('data.assignments.0');
        $this->assertFalse($assignment['metrics_visible']);
        $this->assertNull($assignment['jam_per_hari']);
        $this->assertNull($assignment['volume']);
        $this->assertNull($assignment['satuan']);

        $list = $this->getJson('/api/activities');
        $list->assertOk();
        $listActivity = collect($list->json('data'))->firstWhere('id', $activity->id);
        $this->assertNotNull($listActivity);
        $this->assertNull($listActivity['assignments'][0]['volume']);
    }

    private function makeUser(string $label, PeranPengguna $peran): User
    {
        $this->sequence++;

        return User::create([
            'nama' => $label,
            'email' => 'stabilisasi_'.$this->sequence.'@contoh.test',
            'nip' => '19990101202002'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'jabatan' => 'Statistisi',
            'password' => 'password',
            'peran' => $peran,
            'aktif' => true,
            'must_change_password' => false,
        ]);
    }

    private function makeActivity(
        string $label,
        string $mulai,
        string $selesai
    ): Activity {
        return Activity::create([
            'nama' => "Kegiatan {$label}",
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $this->team->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'kegiatan_besar' => false,
        ]);
    }

    private function makeAssignment(
        Activity $activity,
        User $user,
        float $jamPerHari = 2.0,
        ?string $mulai = null,
        ?string $selesai = null
    ): Assignment {
        return Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Anggota',
            'jam_per_hari' => $jamPerHari,
            'volume' => 10,
            'satuan' => SatuanPenugasan::DOKUMEN->value,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }
}
