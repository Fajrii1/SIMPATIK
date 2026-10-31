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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('peran', 60);
            $table->decimal('volume', 10, 2);
            $table->string('satuan', 30);
            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'mulai', 'selesai'], 'idx_assignments_user_timeline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
