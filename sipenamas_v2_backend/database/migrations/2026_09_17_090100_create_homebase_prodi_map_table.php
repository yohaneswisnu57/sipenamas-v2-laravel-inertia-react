<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homebase_prodi_map', function (Blueprint $table) {
            $table->string('idhomebase')->primary();
            $table->string('kodeprodi')->nullable();
            $table->string('namasatker_snapshot')->nullable();
            $table->enum('matched_by', ['auto', 'manual'])->default('auto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homebase_prodi_map');
    }
};
