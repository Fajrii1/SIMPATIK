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
        Schema::create('weekly_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('pelapor_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('minggu_mulai');
            $table->text('kendala');
            $table->text('solusi')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->date('tenggat')->nullable();
            $table->enum('status', ['terbuka', 'diproses', 'selesai'])->default('terbuka');
            $table->text('catatan_penutupan')->nullable();
            $table->dateTime('ditutup_pada')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'minggu_mulai', 'status'], 'idx_weekly_issues_team_week_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_issues');
    }
};
