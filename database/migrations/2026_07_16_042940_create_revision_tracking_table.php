<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained('regulations')->cascadeOnDelete();
            // 5 tahap closed-loop sesuai master prompt:
            // Terdeteksi -> Ditinjau -> Direkomendasikan -> Diproses DPRD -> Selesai
            $table->enum('status', ['terdeteksi', 'ditinjau', 'direkomendasikan', 'diproses_dprd', 'selesai'])
                ->default('terdeteksi');
            $table->text('catatan')->nullable();
            $table->foreignId('ditugaskan_ke')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_tracking');
    }
};