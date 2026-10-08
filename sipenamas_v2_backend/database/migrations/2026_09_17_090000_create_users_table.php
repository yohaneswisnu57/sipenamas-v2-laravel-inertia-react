<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('kodeperson')->nullable()->unique();
            $table->string('nidn')->nullable()->index();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->boolean('is_external')->default(false);
            $table->string('paswet')->nullable();
            $table->string('kodeprodi')->nullable();
            $table->string('status_aktif')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
