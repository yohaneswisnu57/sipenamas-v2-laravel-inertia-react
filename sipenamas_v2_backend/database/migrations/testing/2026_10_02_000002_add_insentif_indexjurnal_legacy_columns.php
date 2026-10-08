<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `indexjurnal` dan kolom `insentif`/`subsidiapc` untuk pengajuan insentif
 * jurnal & subsidi APC (pen/myphp/insentivejurnal.php, subsidiapc.php) - hanya dipakai di test (sqlite),
 * database produksi sudah punya semuanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexjurnal', function (Blueprint $table) {
            $table->id();
            $table->string('KODEINDEXJURNAL')->nullable();
            $table->string('NAMAINDEXJURNAL')->nullable();
            $table->string('KDJENISPUBLIKASI')->nullable();
            $table->boolean('ADAINSENTIF')->nullable();
            $table->boolean('BOLEHAPC')->nullable();
            $table->unsignedInteger('URUTAN')->nullable();
        });

        Schema::table('insentif', function (Blueprint $table) {
            $table->string('KDPRODI')->nullable();
            $table->string('JENISREFF')->nullable();
            $table->dateTime('TANGGALPENGAJUAN')->nullable();
            $table->string('JUDULARTIKEL')->nullable();
            $table->string('INFOJURNAL_NAMAJURNAL')->nullable();
            $table->string('INFOJURNAL_TERINDEKDALAM')->nullable();
            $table->string('KDJENISPUBLIKASI')->nullable();
            $table->unsignedSmallInteger('TAHUN')->nullable();
            $table->string('VOLUME')->nullable();
            $table->string('NOMOR')->nullable();
            $table->string('URL')->nullable();
            $table->string('DOI')->nullable();
            $table->boolean('ISMENGAJUKANINSENTIF')->nullable();
            $table->string('RES_STATUSINSENTIF')->nullable();
            $table->string('_STATUSINSENTIF')->nullable();
            $table->string('RES_NOMORAGENDA')->nullable();
        });

        Schema::table('subsidiapc', function (Blueprint $table) {
            $table->string('KDPRODI')->nullable();
            $table->dateTime('TANGGALPENGAJUAN')->nullable();
            $table->string('JUDULARTIKEL')->nullable();
            $table->string('INFOJURNAL_NAMAJURNAL')->nullable();
            $table->string('PUBLISHER')->nullable();
            $table->string('INFOJURNAL_TERINDEKDALAM')->nullable();
            $table->decimal('NOMINALPENGAJUANAPC', 15, 2)->nullable();
            $table->string('URL')->nullable();
            $table->string('DOI')->nullable();
            $table->boolean('ISPENGAJUANFINAL')->nullable();
            $table->string('RES_STATUSAPC')->nullable();
            $table->string('RES_NOMORAGENDA')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexjurnal');
    }
};
