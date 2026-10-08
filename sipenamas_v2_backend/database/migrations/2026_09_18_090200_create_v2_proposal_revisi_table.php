<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel aditif baru - siklus revisi eksplisit (siapa verifikator yang
     * ditunjuk LPPM, status siklus, dokumen/tanggapan peneliti). Legacy
     * cuma punya TS_UPLOADPROPOSALREVISI/TINDAKLANJUT/FILE_DOKUMENPROPOSAL_REV
     * (dipertahankan apa adanya untuk kompatibilitas tampilan existing) -
     * tabel ini menambah state machine yang legacy tidak punya. Satu
     * penelitian bisa punya beberapa baris (siklus berulang); baris
     * TERBARU (id tertinggi) yang dipakai sebagai siklus aktif. Tanpa FK
     * constraint ke penelitian (tabel legacy).
     */
    public function up(): void
    {
        Schema::create('v2_proposal_revisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_id')->index();
            $table->string('verifikator_nik')->nullable();
            $table->string('status', 30);
            $table->text('tanggapan_peneliti')->nullable();
            $table->string('dokumen_revisi')->nullable();
            $table->text('catatan_verifikator')->nullable();
            $table->timestamp('diajukan_at')->nullable();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_proposal_revisi');
    }
};
