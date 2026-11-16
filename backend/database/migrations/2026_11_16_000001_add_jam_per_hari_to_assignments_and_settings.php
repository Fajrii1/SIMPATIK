<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan jam per hari pada penugasan dan pengaturan kapasitas kerja harian.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->decimal('jam_per_hari', 4, 2)->nullable()->after('peran');
        });

        // Nilai awal untuk baris lama adalah 2 jam per hari (data dummy).
        DB::table('assignments')->update(['jam_per_hari' => 2.00]);

        Schema::table('assignments', function (Blueprint $table) {
            $table->decimal('jam_per_hari', 4, 2)->default(2.00)->nullable(false)->change();
        });

        Schema::table('assignments', function (Blueprint $table) {
            // Volume dan satuan kini hanya keterangan target pekerjaan, tidak dihitung.
            $table->decimal('volume', 10, 2)->nullable()->change();
            $table->string('satuan', 30)->nullable()->change();
        });

        // Pengaturan baru hanya dimasukkan bila belum ada agar nilai lama tidak tertimpa.
        $defaults = [
            'jam_kerja_per_hari' => '7',
            'hari_libur' => '[]',
        ];

        foreach ($defaults as $kunci => $nilai) {
            $exists = DB::table('settings')->where('kunci', $kunci)->exists();

            if (! $exists) {
                DB::table('settings')->insert([
                    'kunci' => $kunci,
                    'nilai' => $nilai,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Mengembalikan struktur penugasan dan pengaturan ke kondisi sebelumnya.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('jam_per_hari');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->decimal('volume', 10, 2)->nullable(false)->change();
            $table->string('satuan', 30)->nullable(false)->change();
        });

        DB::table('settings')->whereIn('kunci', ['jam_kerja_per_hari', 'hari_libur'])->delete();
    }
};
