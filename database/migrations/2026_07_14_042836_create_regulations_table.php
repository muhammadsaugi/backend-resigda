<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulations', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['perda', 'perbup', 'se', 'instruksi_bupati']);
            $table->string('nomor', 50);
            $table->unsignedSmallInteger('tahun');
            $table->string('judul');
            $table->string('opd')->nullable(); // OPD pengampu / terkait
            $table->date('tanggal_terbit')->nullable();
            $table->enum('status', ['berlaku', 'dicabut', 'diubah'])->default('berlaku');
            $table->json('tags')->nullable();
            $table->text('ringkasan')->nullable();

            // Decay & popularitas — dipakai Regulatory Decay Tracker (Fase 6)
            $table->decimal('decay_score', 5, 2)->default(0);
            $table->unsignedInteger('jumlah_dilihat')->default(0);
            $table->unsignedInteger('jumlah_ditanyakan')->default(0);

            // Path file PDF asli, untuk fitur upload & re-embed
            $table->string('file_path')->nullable();

            $table->timestamps();

            $table->index(['jenis', 'status']);
            $table->fullText(['judul', 'ringkasan']); // butuh MySQL/PG full-text; di PG akan pakai tsvector jika perlu
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulations');
    }
};