<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_verifications', function (Blueprint $table) {
            $table->boolean('dilaporkan_ke_inspektorat')->default(false)->after('layanan');
            $table->text('catatan_laporan')->nullable()->after('dilaporkan_ke_inspektorat');
            $table->string('status_audit')->default('baru')->after('catatan_laporan'); // baru, proses_audit, selesai
        });
    }

    public function down(): void
    {
        Schema::table('claim_verifications', function (Blueprint $table) {
            $table->dropColumn(['dilaporkan_ke_inspektorat', 'catatan_laporan', 'status_audit']);
        });
    }
};
