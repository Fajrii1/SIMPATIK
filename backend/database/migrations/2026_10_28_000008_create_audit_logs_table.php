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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('aksi', 40);
            $table->string('objek', 60);
            $table->unsignedBigInteger('objek_id');
            $table->json('sebelum')->nullable();
            $table->json('sesudah')->nullable();
            $table->timestamps();

            $table->index(['objek', 'objek_id'], 'idx_audit_logs_object');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
