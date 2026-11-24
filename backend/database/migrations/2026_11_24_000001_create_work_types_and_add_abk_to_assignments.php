<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan master jenis pekerjaan (norma waktu) dan kolom ABK pada penugasan.
     */
    public function up(): void
    {
        Schema::create('work_types', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120)->unique();
            $table->string('satuan', 30);
            $table->unsignedSmallInteger('norma_menit');
            $table->enum('jenis_norma', ['estimasi', 'standar'])->default('estimasi');
            $table->string('sumber')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['aktif', 'nama'], 'idx_work_types_aktif_nama');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('work_type_id')
                ->nullable()
                ->after('peran')
                ->constrained('work_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('norma_menit')->nullable()->after('work_type_id');
            $table->unsignedInteger('total_menit')->nullable()->after('norma_menit');
        });

        $this->seedContohWorkTypes();
        $this->backfillTotalMenit();
    }

    /**
     * Mengisi contoh jenis pekerjaan hanya bila tabel masih kosong.
     */
    private function seedContohWorkTypes(): void
    {
        if (DB::table('work_types')->exists()) {
            return;
        }

        $now = now();
        $sumber = 'Contoh untuk demonstrasi, belum divalidasi BPS';

        DB::table('work_types')->insert([
            [
                'nama' => 'Pemeriksaan dokumen',
                'satuan' => 'dokumen',
                'norma_menit' => 15,
                'jenis_norma' => 'estimasi',
                'sumber' => $sumber,
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nama' => 'Pengolahan data',
                'satuan' => 'dataset',
                'norma_menit' => 30,
                'jenis_norma' => 'estimasi',
                'sumber' => $sumber,
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nama' => 'Penyusunan laporan',
                'satuan' => 'laporan',
                'norma_menit' => 120,
                'jenis_norma' => 'estimasi',
                'sumber' => $sumber,
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nama' => 'Verifikasi data',
                'satuan' => 'data',
                'norma_menit' => 10,
                'jenis_norma' => 'estimasi',
                'sumber' => $sumber,
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Data lama dipertahankan: total menit = jam per hari x hari kerja periode x 60.
     */
    private function backfillTotalMenit(): void
    {
        $holidays = $this->holidayDates();

        $rows = DB::table('assignments as a')
            ->leftJoin('activities as act', 'act.id', '=', 'a.activity_id')
            ->select([
                'a.id',
                'a.jam_per_hari',
                'a.mulai',
                'a.selesai',
                'act.mulai as activity_mulai',
                'act.selesai as activity_selesai',
            ])
            ->get();

        foreach ($rows as $row) {
            $mulai = $row->mulai ?? $row->activity_mulai;
            $selesai = $row->selesai ?? $row->activity_selesai;

            if ($mulai === null || $selesai === null) {
                continue;
            }

            $workdays = $this->countWorkdays((string) $mulai, (string) $selesai, $holidays);
            $total = (int) round((float) $row->jam_per_hari * $workdays * 60);

            DB::table('assignments')->where('id', $row->id)->update(['total_menit' => $total]);
        }
    }

    /**
     * @return list<string>
     */
    private function holidayDates(): array
    {
        $setting = DB::table('settings')->where('kunci', 'hari_libur')->first();

        if (! $setting) {
            return [];
        }

        $decoded = json_decode((string) $setting->nilai, true);

        if (! is_array($decoded)) {
            return [];
        }

        $dates = [];

        foreach ($decoded as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            try {
                $dates[] = Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                // Abaikan nilai yang bukan tanggal valid.
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * @param  list<string>  $holidays
     */
    private function countWorkdays(string $mulai, string $selesai, array $holidays): int
    {
        $start = Carbon::parse($mulai)->startOfDay();
        $end = Carbon::parse($selesai)->startOfDay();

        if ($start->gt($end)) {
            return 0;
        }

        $count = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if ($cursor->isWeekday() && ! in_array($cursor->format('Y-m-d'), $holidays, true)) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    /**
     * Mengembalikan struktur ke kondisi sebelum ABK.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['work_type_id']);
            $table->dropColumn(['work_type_id', 'norma_menit', 'total_menit']);
        });

        Schema::dropIfExists('work_types');
    }
};
