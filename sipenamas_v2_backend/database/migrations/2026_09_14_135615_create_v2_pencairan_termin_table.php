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
        // Tabel aditif baru - tidak ada padanan di skema legacy dbsipenamas.
        // Prefix v2_ menandai ini bukan tabel warisan, aman dari tooling/report legacy.
        // penelitian_id sengaja tanpa FK constraint: tabel `penelitian` legacy tidak
        // dijamin punya index/engine yang kompatibel untuk constraint lintas skema baru-lama.
        Schema::create('v2_pencairan_termin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_id')->index();
            $table->unsignedTinyInteger('termin');
            $table->unsignedTinyInteger('persentase');
            $table->double('nominal');
            $table->string('status', 30)->default('MENUNGGU_VERIFIKASI');
            $table->date('tgl_pengajuan');
            $table->date('tgl_pencairan')->nullable();
            $table->string('no_kuitansi', 50)->nullable();
            $table->string('bank', 50)->nullable();
            $table->string('no_rekening', 30)->nullable();
            $table->string('atas_nama', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_pencairan_termin');
    }
};
