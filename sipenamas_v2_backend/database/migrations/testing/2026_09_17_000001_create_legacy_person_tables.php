<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-ins for legacy tables (`person`, `prodi`, `fakultas`,
 * and the 9 historical business-relation tables anchored to
 * `person.KODEPERSON` - see the docblock on App\Models\Person) that
 * exist in the real dbsipenamas MySQL schema but have no Laravel
 * migration (see database/seeders/DatabaseSeeder.php note). Only the
 * columns actually read by app code are included - without these, any
 * endpoint/command touching them errors with "no such table" on the
 * sqlite test database. Loaded via tests/TestCase.php (through
 * AppServiceProvider::boot()), never run against the real database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fakultas', function (Blueprint $table) {
            $table->string('KODEFAKULTAS')->primary();
            $table->string('NAMAFAKULTAS');
            $table->string('KDDEKAN')->nullable();
        });

        Schema::create('prodi', function (Blueprint $table) {
            $table->id();
            $table->string('KODEPRODI')->unique();
            $table->string('NAMAPRODI');
            $table->string('KDFAKULTAS')->nullable();
            $table->string('PREFIXNIK')->nullable();
            $table->boolean('ISMADIUN')->default(0);
        });

        Schema::create('person', function (Blueprint $table) {
            $table->id();
            $table->string('KODEPERSON')->unique();
            $table->string('URL_FOTO')->nullable();
            $table->string('KDPRODI')->nullable();
            $table->string('KDFAKULTAS')->nullable();
            $table->string('NIDN')->nullable();
            $table->string('NAMALENGKAP')->nullable();
            $table->string('EMAIL')->nullable();
            $table->boolean('ISEXTERNAL')->default(false);
            $table->string('PASWET')->nullable();
        });

        // 9 relasi bisnis historis (lihat catatan Person.php) - hanya
        // kolom kode KODEPERSON-nya yang dibutuhkan oleh
        // AuditPersonUserLinks.
        Schema::create('hki_peserta', function (Blueprint $table) {
            $table->id();
            $table->string('NIP')->nullable();
        });

        Schema::create('kegiatanabdimas', function (Blueprint $table) {
            $table->id();
            $table->string('KDPERSONPENGAJU')->nullable();
        });

        Schema::create('penelitian_tim', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('NIKNIDN')->nullable();
            $table->string('PERAN')->nullable();
            $table->unsignedInteger('URUTAN')->nullable();
            $table->boolean('ISAPPROVED')->nullable();
            $table->timestamp('TSAPPROVED')->nullable();
            $table->string('URAIANTUGAS')->nullable();
        });

        Schema::create('kegiatanabdimas_tim', function (Blueprint $table) {
            $table->id();
            $table->string('NIKNIDN')->nullable();
        });

        Schema::create('insentif', function (Blueprint $table) {
            $table->id();
            $table->string('KDPERSONPENGAJU')->nullable();
        });

        Schema::create('penelitian_reviewer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('NIK')->nullable();
            $table->boolean('ISAPPROVED')->nullable();
            $table->string('STATUSPENILAIAN')->nullable();
            $table->string('HASILPENILAIAN')->nullable();
            $table->decimal('TOTALSKOR', 8, 2)->nullable();
            $table->text('KOMENTAR')->nullable();
            $table->decimal('REKOMENDASIBIAYA', 15, 2)->nullable();
            $table->boolean('ISREVIEWERPEMBANDING')->nullable();
        });

        Schema::create('penelitian', function (Blueprint $table) {
            $table->id();
            $table->string('KDPERIODE')->nullable();
            $table->unsignedSmallInteger('TAHUNUSULAN')->nullable();
            $table->string('JENIS_PA')->nullable();
            $table->string('KDSKIMPENELITIAN')->nullable();
            $table->string('JUDULPENELITIAN')->nullable();
            $table->string('JUDULPENELITIANX')->nullable();
            $table->string('BIDANGPENELITIAN')->nullable();
            $table->string('ABDIMAS_TEMPATLOKASI')->nullable();
            $table->string('PERMOHONANDIBUAT_KDPERSON')->nullable();
            $table->string('PERMOHONANDIBUAT_STATUS')->nullable();
            $table->timestamp('PERMOHONANDIBUAT_TIMESTAMP')->nullable();
            $table->string('KDSUMBERDANA')->nullable();
            $table->decimal('NOMINALDANA', 15, 2)->nullable();
            $table->decimal('NOMINALDANA_FINAL', 15, 2)->nullable();
            $table->string('KDPRODI')->nullable();
            $table->text('TARGETLUARAN')->nullable();
            $table->decimal('KOMPOSISIDANA_HONORARIUM', 5, 2)->nullable();
            $table->decimal('KOMPOSISIDANA_BAHANPERALATAN', 5, 2)->nullable();
            $table->decimal('KOMPOSISIDANA_BIAYAPERJALANAN', 5, 2)->nullable();
            $table->decimal('KOMPOSISIDANA_LAPORAN', 5, 2)->nullable();
            $table->text('__ABSTRAK')->nullable();
            $table->boolean('ISPENGAJUANFINAL')->nullable();
            $table->string('STATUSPENUNJUKANREVIEWER')->nullable();
            $table->string('STATUSFINALAPPROVAL')->nullable();
            $table->string('STATUSKETUNTASANPENELITIAN')->nullable();
            $table->boolean('APPROVALPERMOHONAN_ISAPPROVEBYDEKAN')->nullable();
            $table->boolean('ISAPPROVEDBYLPPM')->nullable();
            $table->string('_MSG_PENOLAKANDEKAN')->nullable();
            $table->string('STATUSPENILAIANREVIEWER')->nullable();
            $table->string('FILE_DOKUMENHASILPENELITIAN')->nullable();
            $table->string('FILE_DOKUMENPROPOSAL_REV')->nullable();
            $table->string('FILE_DOKUMENPROPOSAL_INIT')->nullable();
            $table->string('FILE_DOKUMENPROPOSAL_FINAL')->nullable();
            $table->text('TINDAKLANJUT')->nullable();
            $table->decimal('SKORAKHIR', 8, 2)->nullable();
            $table->string('SURATTUGAS_NOMORSK')->nullable();
            $table->date('SURATTUGAS_TANGGALSK')->nullable();
            $table->string('CETAKSURATTUGAS_STATUS')->nullable();
            $table->string('CETAKSURATTUGAS_NOMORSURAT')->nullable();
            $table->date('CETAKSURATTUGAS_TANGGALSURAT')->nullable();
            $table->string('CETAKSURATTUGAS_NAMAFILE')->nullable();
            $table->string('CETAKSURATTUGAS_QRCODE')->nullable();
            $table->string('CETAKSURATDANA_STATUS')->nullable();
            $table->string('CETAKSURATDANA_NOMORSURAT')->nullable();
            $table->date('CETAKSURATDANA_TANGGALSURAT')->nullable();
            $table->string('CETAKSURATDANA_NAMAFILE')->nullable();
            $table->string('CETAKSURATDANA_QRCODE')->nullable();
            $table->timestamp('TS_UPLOADPROPOSALREVISI')->nullable();
        });

        Schema::create('penelitian_mhs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('NIM')->nullable();
            $table->string('_KETERANGAN')->nullable();
        });

        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('NIM')->nullable();
            $table->string('NAMAMAHASISWA')->nullable();
            $table->string('NAMAPRODI')->nullable();
            $table->string('STATUSNYA')->nullable();
        });

        Schema::create('penelitian_monevhasil', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->text('KESIMPULAN')->nullable();
            $table->boolean('ISFINAL')->nullable();
        });

        Schema::create('skimpenelitian', function (Blueprint $table) {
            $table->id();
            $table->string('KODESKIM')->unique();
            $table->string('NAMASKIM')->nullable();
            $table->text('KETERANGAN')->nullable();
            $table->string('KDSOALPENILAIANPOSTER')->nullable();
            $table->boolean('ISSTATUSPEMAPARAN')->default(0);
            $table->decimal('MAXDANA', 15, 2)->nullable();
            $table->boolean('ISAKTIF')->default(1);
            $table->boolean('ISABDIMAS')->default(0);
            $table->unsignedInteger('URUTAN')->nullable();
            $table->string('NOMORKODEANGGARAN')->nullable();
        });

        Schema::create('tabelkodeanggaran', function (Blueprint $table) {
            $table->id();
            $table->string('NOMORKODE')->nullable();
            $table->string('KETERANGAN')->nullable();
        });

        Schema::create('sumberdana', function (Blueprint $table) {
            $table->string('KODESUMBERDANA')->primary();
            $table->string('NAMASUMBERDANA')->nullable();
            $table->unsignedInteger('URUTAN')->nullable();
        });

        Schema::create('periode', function (Blueprint $table) {
            $table->id();
            $table->string('KODEPERIODE')->nullable();
            $table->unsignedSmallInteger('TAHUN')->nullable();
            $table->boolean('ISAKTIF')->default(0);
            $table->date('TGLPELAKSANAANBEGIN')->nullable();
            $table->date('TGLPELAKSANAANEND')->nullable();
        });

        Schema::create('penelitian_janggaran_1honorarium', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('NAMAPELAKSANA')->nullable();
            $table->decimal('NOMINALHONOR', 15, 2)->nullable();
        });

        Schema::create('penelitian_janggaran_2pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('NAMAMATERIAL')->nullable();
            $table->decimal('NOMINALPEMBELIAN', 15, 2)->nullable();
        });

        Schema::create('penelitian_janggaran_3perjalanan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('NAMAMATERIAL')->nullable();
            $table->decimal('BIAYAPERTAHUN', 15, 2)->nullable();
        });

        Schema::create('penelitian_janggaran_4sewa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPARENT')->nullable();
            $table->string('NAMAMATERIAL')->nullable();
            $table->decimal('BIAYAPERTAHUN', 15, 2)->nullable();
        });

        Schema::create('subsidiapc', function (Blueprint $table) {
            $table->id();
            $table->string('KDPERSONPENGAJU')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidiapc');
        Schema::dropIfExists('penelitian_janggaran_4sewa');
        Schema::dropIfExists('penelitian_janggaran_3perjalanan');
        Schema::dropIfExists('penelitian_janggaran_2pembelian');
        Schema::dropIfExists('penelitian_janggaran_1honorarium');
        Schema::dropIfExists('periode');
        Schema::dropIfExists('tabelkodeanggaran');
        Schema::dropIfExists('sumberdana');
        Schema::dropIfExists('skimpenelitian');
        Schema::dropIfExists('penelitian_monevhasil');
        Schema::dropIfExists('mahasiswa');
        Schema::dropIfExists('penelitian_mhs');
        Schema::dropIfExists('penelitian');
        Schema::dropIfExists('penelitian_reviewer');
        Schema::dropIfExists('insentif');
        Schema::dropIfExists('kegiatanabdimas_tim');
        Schema::dropIfExists('penelitian_tim');
        Schema::dropIfExists('kegiatanabdimas');
        Schema::dropIfExists('hki_peserta');
        Schema::dropIfExists('person');
        Schema::dropIfExists('prodi');
        Schema::dropIfExists('fakultas');
    }
};
