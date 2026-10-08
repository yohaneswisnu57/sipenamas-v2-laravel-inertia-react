<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in untuk kolom/tabel legacy tahap laporan akhir
 * (kuesioner peneliti, kelengkapan laporan, capaian & luaran) - di DB
 * produksi semuanya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penelitian', function (Blueprint $table) {
            $table->string('LBRPENGESAHANLAPHASIL_NAMAFILE')->nullable();
            $table->string('LBRPENGESAHANLAPHASIL_QRCODE')->nullable();
            $table->boolean('LBRPENGESAHANLAPHASIL_ISFINAL')->nullable();
            $table->double('LBRPENGESAHANLAPHASIL_DANAMITRA')->nullable();
            $table->double('LBRPENGESAHANLAPHASIL_DANAINKIND')->nullable();
            $table->boolean('ISDEKANAPPROVELAPORANAKHIR')->nullable();
            $table->timestamp('TSDEKANAPPROVELAPORANAKHIR')->nullable();
        });

        Schema::table('penelitian_rencanatarget', function (Blueprint $table) {
            $table->boolean('ISADAINSENTIF')->nullable();
            $table->boolean('ISCHKREALISASI')->nullable();
            $table->string('FILE_DOC')->nullable();
            $table->string('FILE_EXT')->nullable();
            $table->string('KETHASIL')->nullable();
            $table->string('STATUSTAYANG')->nullable();
        });

        Schema::table('insentif', function (Blueprint $table) {
            $table->unsignedBigInteger('IDPENELITIANREFF')->nullable();
            $table->boolean('ISPENGAJUANFINAL')->nullable();
        });

        Schema::table('periode', function (Blueprint $table) {
            $table->date('TGLBEGIN')->nullable();
            $table->date('TGLEND')->nullable();
        });

        Schema::table('person', function (Blueprint $table) {
            $table->string('JABATAN')->nullable();
            $table->string('HP')->nullable();
        });

        Schema::table('skimpenelitian', function (Blueprint $table) {
            $table->string('STATUSPEN')->nullable();
        });

        Schema::create('soalkuesionerpeneliti', function (Blueprint $table) {
            $table->id();
            $table->string('KODESOAL')->nullable();
            $table->boolean('ISAKTIF')->nullable();
            $table->string('KELOMPOK_A')->nullable();
            $table->string('KELOMPOK_B')->nullable();
            $table->string('KELOMPOK_C')->nullable();
            $table->string('SKOR_1')->nullable();
            $table->string('SKOR_2')->nullable();
            $table->string('SKOR_3')->nullable();
            $table->string('SKOR_4')->nullable();
        });

        Schema::create('soalkuesionerpeneliti_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->unsignedInteger('NOMOR')->nullable();
            $table->string('KELOMPOK')->nullable();
            $table->string('URAIAN')->nullable();
        });

        Schema::create('pengisiankuesionerpeneliti', function (Blueprint $table) {
            $table->id();
            $table->string('KDPERIODE')->nullable();
            $table->string('GELOMBANG')->nullable();
            $table->string('NIK')->nullable();
            $table->string('KDSOAL')->nullable();
            $table->boolean('ISDONE')->nullable();
            $table->string('JENIS_PA')->nullable();
        });

        Schema::create('pengisiankuesionerpeneliti_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->unsignedInteger('NOMORSOAL')->nullable();
            $table->string('JAWAB')->nullable();
            $table->unsignedInteger('SKOR')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengisiankuesionerpeneliti_detail');
        Schema::dropIfExists('pengisiankuesionerpeneliti');
        Schema::dropIfExists('soalkuesionerpeneliti_detail');
        Schema::dropIfExists('soalkuesionerpeneliti');
    }
};
