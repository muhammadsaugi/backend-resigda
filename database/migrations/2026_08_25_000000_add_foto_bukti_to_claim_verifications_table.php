<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('claim_verifications', function (Blueprint $table) {
            if (! Schema::hasColumn('claim_verifications', 'foto_bukti')) {
                $table->string('foto_bukti')->nullable()->after('klaim_text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_verifications', function (Blueprint $table) {
            if (Schema::hasColumn('claim_verifications', 'foto_bukti')) {
                $table->dropColumn('foto_bukti');
            }
        });
    }
};
