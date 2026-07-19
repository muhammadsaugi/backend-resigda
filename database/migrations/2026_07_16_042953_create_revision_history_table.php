<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_tracking_id')->constrained('revision_tracking')->cascadeOnDelete();
            $table->string('status'); // snapshot status pada saat itu (histori, bukan enum FK)
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('revision_tracking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_history');
    }
};