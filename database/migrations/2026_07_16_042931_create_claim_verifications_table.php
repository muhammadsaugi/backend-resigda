<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRIVACY BY DESIGN: sama seperti civic_interactions, tabel ini TIDAK
 * menyimpan nama pelapor atau kontak apapun. klaim_text disimpan karena
 * ini fitur verifikasi aktif (bukan chat pasif) — warga sengaja mengetik
 * klaim spesifik untuk dicek, beda konteks dengan civic_interactions
 * yang memang dirancang tanpa teks asli untuk agregasi sentimen masif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->text('klaim_text');
            $table->enum('hasil_verifikasi', ['ditemukan', 'tidak_ditemukan', 'sebagian_sesuai']);
            $table->json('regulation_ids')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('layanan')->nullable(); // jenis layanan yang diklaim (mis. "IMB", "KTP")
            $table->timestamps();

            $table->index('session_id');
            $table->index('hasil_verifikasi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_verifications');
    }
};