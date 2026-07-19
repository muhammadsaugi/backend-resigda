<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom-kolom khusus ASN ke tabel users bawaan Laravel.
 * Role dibatasi ke 3 nilai sesuai hak akses REGSIDA:
 * staf_opd, bagian_hukum, inspektorat
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->unique()->after('name');
            $table->enum('role', ['staf_opd', 'bagian_hukum', 'inspektorat'])
                ->default('staf_opd')
                ->after('nip');
            $table->string('opd')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('opd');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip', 'role', 'opd', 'is_active']);
        });
    }
};