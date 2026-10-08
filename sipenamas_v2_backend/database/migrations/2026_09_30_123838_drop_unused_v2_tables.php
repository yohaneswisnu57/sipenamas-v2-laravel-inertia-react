<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel aditif V2 yang diganti kolom legacy (lihat docs/legacy-flow/penelitian.md §9):
 * - v2_dekan_keputusan    -> penelitian.APPROVALPERMOHONAN_* / _MSG_PENOLAKANDEKAN
 * - v2_reviewer_kesediaan -> penelitian_reviewer.ISAPPROVED / TSAPPROVED / KOMENTAR
 * - v2_proposal_meta      -> fitur "lanjutan" dihapus
 * - v2_pencairan_termin   -> diganti Surat Pencairan Dana legacy (CETAKSURATDANA_*)
 * - v2_proposal_revisi    -> penelitian_reviewer.ISREVIEWERREVISI/REVISI_*, penelitian.ISDOKUMENPROPOSALREVISIFINAL/TINDAKLANJUT
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('v2_pencairan_termin');
        Schema::dropIfExists('v2_dekan_keputusan');
        Schema::dropIfExists('v2_reviewer_kesediaan');
        Schema::dropIfExists('v2_proposal_meta');
        Schema::dropIfExists('v2_proposal_revisi');
    }

    public function down(): void
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

        Schema::create('v2_dekan_keputusan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_id')->index();
            $table->string('keputusan', 10);
            $table->text('catatan');
            $table->string('kodeperson_pemutus')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        Schema::create('v2_reviewer_kesediaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_reviewer_id')->unique();
            $table->boolean('bersedia')->nullable();
            $table->text('alasan')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('v2_proposal_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_id')->unique();
            $table->boolean('is_lanjutan_penelitian')->default(false);
            $table->boolean('is_lanjutan_abdimas')->default(false);
            $table->timestamps();
        });
    }
};
