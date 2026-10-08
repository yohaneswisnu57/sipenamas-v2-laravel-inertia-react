<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel aditif baru - anggota tim eksternal (mitra) tidak punya
     * KODEPERSON di sistem, jadi tidak bisa disimpan lewat `penelitian_tim`
     * legacy (NIKNIDN mengacu ke person.KODEPERSON). Tanpa FK constraint ke
     * penelitian (tabel legacy), sama seperti tabel v2_ lain.
     */
    public function up(): void
    {
        Schema::create('v2_penelitian_mitra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_id')->index();
            $table->string('nama');
            $table->string('instansi');
            $table->text('tugas')->nullable();
            $table->unsignedInteger('urutan')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_penelitian_mitra');
    }
};
