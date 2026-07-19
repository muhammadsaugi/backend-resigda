<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('regulations')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('regulations')->cascadeOnDelete();
            $table->enum('jenis_relasi', ['mencabut', 'dicabut_oleh', 'mengubah', 'diubah_oleh', 'merujuk', 'dirujuk_oleh', 'konflik']);
            $table->decimal('confidence', 5, 2)->default(0); // skor keyakinan AI saat deteksi otomatis relasi
            $table->text('alasan')->nullable(); // penjelasan kenapa relasi ini terdeteksi
            $table->enum('status_tinjau', ['belum_ditinjau', 'divalidasi', 'ditolak'])->default('belum_ditinjau');
            $table->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_id', 'target_id']);
            $table->index('status_tinjau');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_relations');
    }
};