<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in untuk kolom/tabel legacy lembar pengesahan proposal,
 * komentar revisi per butir, dan masa revisi gelombang - di DB produksi
 * semuanya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penelitian', function (Blueprint $table) {
            $table->string('LBRPENGESAHANPROPOSAL_NAMAFILE')->nullable();
            $table->string('LBRPENGESAHANPROPOSAL_QRCODE')->nullable();
            $table->boolean('LBRPENGESAHANPROPOSAL_ISFINAL')->nullable();
            $table->double('LBRPENGESAHANPROPOSAL_DANAMITRA')->nullable();
            $table->double('LBRPENGESAHANPROPOSAL_DANAINKIND')->nullable();
        });

        Schema::create('penelitian_penilaianproposal_revisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->text('KOMENREVISI')->nullable();
            $table->text('KOMENRESPON')->nullable();
        });

        Schema::create('periodegelombang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('GELOMBANG')->nullable();
            $table->boolean('GELAKTIF')->nullable();
            $table->date('TGLREVISI_TO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodegelombang');
        Schema::dropIfExists('penelitian_penilaianproposal_revisi');
    }
};
