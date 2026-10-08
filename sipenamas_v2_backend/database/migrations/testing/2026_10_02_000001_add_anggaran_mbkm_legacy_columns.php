<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom & tabel legacy untuk pagu anggaran prodi (dkn/myphp/anggaranpenelitian.php)
 * dan laporan MBKM (rkt/myphp/mbkm*.php) - hanya dipakai di test (sqlite),
 * database produksi sudah punya semuanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prodi_anggaran', function (Blueprint $table) {
            $table->decimal('ALOKASIANGGARAN', 15, 2)->default(0);
            $table->unsignedInteger('JUMLAHPENELITIAN')->default(0);
            $table->text('CATATAN')->nullable();
            $table->decimal('ABDIMAS_ALOKASIANGGARAN', 15, 2)->default(0);
            $table->unsignedInteger('ABDIMAS_JUMLAHPENELITIAN')->default(0);
        });

        Schema::create('mbkm_mhs', function (Blueprint $table) {
            $table->id();
            $table->string('NIM')->nullable();
            $table->string('NAMAMAHASISWA')->nullable();
            $table->string('HP')->nullable();
            $table->string('EMAILNYA')->nullable();
            $table->string('SURVEY_STS')->nullable();
            $table->timestamp('SURVEY_TIMESTAMP')->nullable();
            $table->double('RWD100K')->nullable();
            $table->double('RWD50K')->nullable();
            $table->double('RWD20K')->nullable();
            $table->timestamp('TS_REQUEST')->nullable();
            $table->timestamp('TS_REWARD')->nullable();
        });

        foreach (['mbkm_datahasil_dosen', 'mbkm_datahasil_mahasiswa', 'mbkm_datahasil_tendik'] as $nama) {
            Schema::create($nama, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('NOMOR')->nullable();
                $table->string('PROPINSI')->nullable();
                $table->string('PERGURUANTINGGI')->nullable();
                $table->string('PROGRAMSTUDI')->nullable();
                $table->string('IDENTITAS')->nullable();
                $table->string('NAMA')->nullable();
                $table->string('MASAKERJA')->nullable();
                $table->string('SEMESTER')->nullable();
                $table->text('PERTANYAAN')->nullable();
                $table->text('JAWABAN')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mbkm_datahasil_tendik');
        Schema::dropIfExists('mbkm_datahasil_mahasiswa');
        Schema::dropIfExists('mbkm_datahasil_dosen');
        Schema::dropIfExists('mbkm_mhs');

        Schema::table('prodi_anggaran', function (Blueprint $table) {
            $table->dropColumn([
                'ALOKASIANGGARAN', 'JUMLAHPENELITIAN', 'CATATAN',
                'ABDIMAS_ALOKASIANGGARAN', 'ABDIMAS_JUMLAHPENELITIAN',
            ]);
        });
    }
};
