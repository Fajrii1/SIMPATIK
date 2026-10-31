<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 200);
            $table->string('jenis', 60);
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('mulai');
            $table->date('selesai');
            $table->decimal('bobot', 5, 2)->default(1.00);
            $table->boolean('kegiatan_besar')->default(false);
            $table->boolean('selesai_manual')->default(false);
            $table->dateTime('selesai_pada')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'mulai', 'selesai'], 'idx_activities_team_timeline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
