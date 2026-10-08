<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in untuk kolom/tabel legacy yang dipakai tahap Dekan &
 * penilaian reviewer (borang per skim, detail skor, penanda reviewer
 * ketiga) - di DB produksi semuanya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penelitian', function (Blueprint $table) {
            $table->timestamp('APPROVALPERMOHONAN_TIMESTAMP')->nullable();
            $table->string('APPROVALPERMOHONAN_KDDEKAN')->nullable();
            $table->text('APPROVALPERMOHONAN_CATATANDEKAN')->nullable();
            $table->boolean('ISBUTUHREVIEWERKETIGA')->nullable();
            $table->boolean('ISDOKUMENPROPOSALREVISIFINAL')->default(0);
            $table->string('JUDULPENELITIAN_YGLAMA')->nullable();
        });

        Schema::table('skimpenelitian', function (Blueprint $table) {
            $table->string('KDSOALPENILAIANPROPOSAL')->nullable();
        });

        Schema::table('penelitian_reviewer', function (Blueprint $table) {
            $table->timestamp('TSAPPROVED')->nullable();
            $table->boolean('ISREVIEWERREVISI')->default(0);
            $table->string('REVISI_HASILPENILAIAN')->default('-');
            $table->text('REVISI_KOMENTAR')->nullable();
            $table->string('STATUSPENILAIANREVISI')->nullable();
        });

        Schema::create('soalpenilaianproposal', function (Blueprint $table) {
            $table->id();
            $table->string('KODESOAL')->nullable();
            $table->string('DESKRIPSI')->nullable();
        });

        Schema::create('soalpenilaianproposal_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->unsignedInteger('NOMOR')->nullable();
            $table->text('KRITERIAPENILAIAN')->nullable();
            $table->unsignedInteger('BOBOTPERSEN')->nullable();
        });

        Schema::create('penelitian_penilaianproposal', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->unsignedBigInteger('IDREVIEWER')->nullable();
        });

        Schema::create('penelitian_penilaianproposal_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->unsignedInteger('NOMORSOAL')->nullable();
            $table->unsignedInteger('SKOR')->nullable();
            $table->unsignedInteger('NILAI')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penelitian_penilaianproposal_detail');
        Schema::dropIfExists('penelitian_penilaianproposal');
        Schema::dropIfExists('soalpenilaianproposal_detail');
        Schema::dropIfExists('soalpenilaianproposal');
    }
};
