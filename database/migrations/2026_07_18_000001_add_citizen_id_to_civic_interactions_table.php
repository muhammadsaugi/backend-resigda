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
        Schema::table('civic_interactions', function (Blueprint $table) {
            $table->foreignId('citizen_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('civic_interactions', function (Blueprint $table) {
            $table->dropForeign(['citizen_id']);
            $table->dropColumn('citizen_id');
        });
    }
};
