<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_verifications', function (Blueprint $table) {
            $table->string('kategori_laporan')->default('pungli_petugas')->after('layanan'); // 'pungli_petugas' vs 'usul_regulasi'
        });
    }

    public function down(): void
    {
        Schema::table('claim_verifications', function (Blueprint $table) {
            $table->dropColumn('kategori_laporan');
        });
    }
};
