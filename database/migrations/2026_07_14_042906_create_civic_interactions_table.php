<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRIVACY BY DESIGN — lihat master prompt REGSIDA.
 * Tabel ini SENGAJA TIDAK memiliki kolom:
 *   - query_text (teks pertanyaan asli)
 *   - ip_address
 *   - nama / identitas apapun
 * Jangan tambahkan kolom tersebut di migration lanjutan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('civic_interactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id'); // dibuat di localStorage sisi frontend
            $table->string('topic')->nullable();       // hasil klasifikasi Gemini (Fase 5)
            $table->string('sentiment')->nullable();    // hasil klasifikasi Gemini (Fase 5)
            $table->json('regulation_ids')->nullable(); // ID regulasi yang relevan dgn jawaban
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('kecamatan')->nullable();     // hanya jika terinfer dari konteks
            $table->timestamp('interacted_at')->useCurrent();
            $table->timestamps();

            $table->index('session_id');
            $table->index(['topic', 'sentiment']);
            $table->index('interacted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civic_interactions');
    }
};