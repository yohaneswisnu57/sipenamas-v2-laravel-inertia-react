<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in for the legacy `z_log_login` table (see
 * 2026_09_17_000001_create_legacy_person_tables.php for why this exists).
 * AuthController::login() writes to it unconditionally on every successful
 * login, so without this table every login test errors with "no such table".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_log_login', function (Blueprint $table) {
            $table->id();
            $table->string('USR')->nullable();
            $table->timestamp('TS')->nullable();
            $table->string('IP')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_log_login');
    }
};
