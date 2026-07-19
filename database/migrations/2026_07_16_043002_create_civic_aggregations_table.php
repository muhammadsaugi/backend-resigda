<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('civic_aggregations', function (Blueprint $table) {
            $table->id();
            $table->date('periode'); // disimpan sebagai tanggal 1 di bulan tsb, mis. 2026-07-01 mewakili "Juli 2026"
            $table->string('topic');
            $table->string('sentiment');
            $table->unsignedInteger('jumlah_interaksi')->default(0);
            $table->json('regulation_ids')->nullable();
            $table->string('kecamatan')->nullable();
            $table->timestamps();

            // Satu baris unik per kombinasi periode+topic+sentiment+kecamatan,
            // supaya job agregasi harian bisa "upsert" tanpa duplikat.
            $table->unique(['periode', 'topic', 'sentiment', 'kecamatan'], 'civic_aggregations_unique_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civic_aggregations');
    }
};