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
        $assignment = $this->makeAssignment($activity, $this->pegawai, 20);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/workload/users/{$this->pegawai->id}?from=2034-01&to=2034-03");

        $response->assertOk();

        $row = collect($response->json('penugasan'))->firstWhere('id', $assignment->id);
        $this->assertNotNull($row, 'Penugasan yang membentang melewati jendela harus tetap muncul.');
        $this->assertSame('2033-12-01', $row['tanggal_mulai']);
        $this->assertSame('2034-04-30', $row['tanggal_selesai']);

        // Konsisten dengan tren bulanan: setiap bulan jendela memuat poin.
        foreach (['2034-01', '2034-02', '2034-03'] as $month) {
            $history = collect($response->json('tren_bulanan'))->firstWhere('bulan', $month);
            $this->assertNotNull($history);
            $this->assertGreaterThan(0, (float) $history['poin']);
            $this->assertSame(1, $history['jumlah_penugasan']);
        }
    }

    /**
     * Kegiatan yang di-soft-delete tidak ditampilkan dan tidak menyebabkan error 500.
     */
    public function test_user_detail_skips_assignments_from_soft_deleted_activity(): void
    {
        $activity = $this->makeActivity('Dihapus', '2034-02-01', '2034-02-28');
        $assignment = $this->makeAssignment($activity, $this->pegawai, 10, '2034-02-05', '2034-02-10');
        $activity->delete();

        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/workload/users/{$this->pegawai->id}?from=2034-01&to=2034-03");

        $response->assertOk();
        $this->assertNull(
            collect($response->json('penugasan'))->firstWhere('id', $assignment->id),
            'Penugasan dari kegiatan terhapus tidak boleh tampil.'
        );
    }

    /**
     * Poin pada agenda dashboard sama dengan volume x bobot kegiatan.
     */
    public function test_dashboard_active_assignment_points_equal_volume_times_weight(): void
    {
        $today = Carbon::today();
        $activity = $this->makeActivity(
            'Agenda Dashboard',
            $today->copy()->subDays(3)->toDateString(),
            $today->copy()->addDays(3)->toDateString(),
            2.0
        );
        $assignment = $this->makeAssignment($activity, $this->pegawai, 12.5);

        Sanctum::actingAs($this->pegawai);
        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $row = collect($response->json('data.penugasan_aktif'))->firstWhere('id', $assignment->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(25.0, (float) $row['poin'], 0.001);
        $this->assertNotEquals(0.0, (float) $row['poin']);
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
        $activity = $this->makeActivity('Visibilitas Metrik', '2035-06-01', '2035-06-30', 1.5);
        $this->makeAssignment($activity, $this->pegawai, 10);

        $expectedVisible = [
            'admin' => $this->admin,
            'pimpinan' => $this->pimpinan,
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
            $this->assertEqualsWithDelta(10.0, (float) $assignment['volume'], 0.001);
            $this->assertSame('Dokumen', $assignment['satuan']);
            $this->assertEqualsWithDelta(15.0, (float) $assignment['poin_total'], 0.001);
        }

        // Pegawai lain (bukan pemilik, bukan ketua tim kegiatan) tidak melihat metrik.
        Sanctum::actingAs($this->rekan);

        $detail = $this->getJson("/api/activities/{$activity->id}");
        $detail->assertOk();
        $assignment = $detail->json('data.assignments.0');
        $this->assertFalse($assignment['metrics_visible']);
        $this->assertNull($assignment['volume']);
        $this->assertNull($assignment['satuan']);
        $this->assertNull($assignment['poin_total']);

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
        string $selesai,
        float $bobot = 1.0
    ): Activity {
        return Activity::create([
            'nama' => "Kegiatan {$label}",
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $this->team->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'bobot' => $bobot,
            'kegiatan_besar' => false,
        ]);
    }

    private function makeAssignment(
        Activity $activity,
        User $user,
        float $volume,
        ?string $mulai = null,
        ?string $selesai = null
    ): Assignment {
        return Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Anggota',
            'volume' => $volume,
            'satuan' => SatuanPenugasan::DOKUMEN->value,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }
}
