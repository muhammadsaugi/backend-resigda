<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')
                ->constrained('regulations')
                ->cascadeOnDelete();
            $table->string('nomor_pasal', 20);
            $table->text('isi');
            $table->timestamps();

            $table->index('regulation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_articles');
    }
};