<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Kolom `embedding` bertipe vector(768) tidak didukung langsung oleh
 * Blueprint Laravel, jadi ditambahkan lewat raw SQL setelah tabel dibuat.
 * 768 dimensi mengikuti model Gemini text-embedding-004.
 *
 * PENTING: extension pgvector wajib sudah aktif (CREATE EXTENSION vector;)
 * sebelum migration ini dijalankan — lihat Fase 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')
                ->constrained('regulations')
                ->cascadeOnDelete();
            $table->foreignId('article_id')
                ->nullable()
                ->constrained('regulation_articles')
                ->nullOnDelete();
            $table->text('content_chunk');
            $table->timestamps();
        });

        // Tambah kolom vector via raw SQL
        DB::statement('ALTER TABLE regulation_embeddings ADD COLUMN embedding vector(768)');

    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_embeddings');
    }
};