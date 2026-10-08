<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in untuk kolom/tabel legacy yang dipakai tahap pengajuan
 * penelitian (cekal, kuota, batas anggaran, rencana target, dokumen
 * proposal) - di DB produksi semuanya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penelitian', function (Blueprint $table) {
            $table->unsignedSmallInteger('PERIODEKEGIATAN_TAHUN')->nullable();
            $table->boolean('ISDOKUMENPROPOSALFINAL')->nullable();
        });

        Schema::table('skimpenelitian', function (Blueprint $table) {
            $table->unsignedInteger('MINANGGOTA')->nullable();
            $table->unsignedInteger('MAXANGGOTA')->nullable();
            $table->decimal('ANGGARANPERPENELITIAN', 15, 2)->nullable();
            $table->boolean('ISOPENBUDGET')->nullable();
            $table->string('DEFKDSUMBERDANA')->nullable();
        });

        Schema::table('sumberdana', function (Blueprint $table) {
            $table->boolean('ISDANALPPM')->nullable();
        });

        Schema::create('settingan', function (Blueprint $table) {
            $table->id();
            $table->string('THISISIT')->nullable();
            $table->unsignedInteger('KUOTA_PENELITIAN_KETUA')->nullable();
            $table->unsignedInteger('KUOTA_PENELITIAN_ANGGOTA')->nullable();
            $table->unsignedInteger('KUOTA_PENGABDIAN_KETUA')->nullable();
            $table->unsignedInteger('KUOTA_PENGABDIAN_ANGGOTA')->nullable();
        });

        Schema::create('prodi_anggaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('KDPERIODE')->nullable();
            $table->decimal('ANGGARANPERPENELITIAN', 15, 2)->nullable();
            $table->boolean('ISOPENBUDGET')->nullable();
        });

        Schema::create('tabelrencanatarget', function (Blueprint $table) {
            $table->id();
            $table->string('KDSKIM')->nullable();
            $table->string('KATEGORI')->nullable();
            $table->string('SUBKATEGORI')->nullable();
            $table->boolean('ISWAJIB')->nullable();
            $table->string('INDIKATORNYA')->nullable();
            $table->unsignedInteger('URUTAN')->nullable();
        });

        Schema::create('penelitian_rencanatarget', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('KATEGORI')->nullable();
            $table->string('SUBKATEGORI')->nullable();
            $table->boolean('ISWAJIB')->nullable();
            $table->string('INDIKATORNYA')->nullable();
            $table->unsignedInteger('URUTAN')->nullable();
            $table->boolean('ISCHKTARGET')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penelitian_rencanatarget');
        Schema::dropIfExists('tabelrencanatarget');
        Schema::dropIfExists('prodi_anggaran');
        Schema::dropIfExists('settingan');
    }
};
