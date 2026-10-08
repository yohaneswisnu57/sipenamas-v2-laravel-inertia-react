<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in untuk kolom/tabel legacy tahap Monev hasil (penunjukan
 * reviewer monev oleh Dekan dan borang `soalmonevpenelitian`/`soalmonevabdimas`) - di DB
 * produksi semuanya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penelitian', function (Blueprint $table) {
            $table->string('MONEVHASILBY')->nullable();
            $table->date('TGLMULAI')->nullable();
        });

        Schema::table('person', function (Blueprint $table) {
            $table->boolean('ISGJM')->nullable();
        });

        Schema::table('penelitian_monevhasil', function (Blueprint $table) {
            foreach (range(1, 10) as $nomor) {
                $table->string(sprintf('JAWAB%02d', $nomor), 1)->nullable();
            }
        });

        foreach (['soalmonevpenelitian', 'soalmonevabdimas'] as $tabel) {
            Schema::create($tabel, function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('NOMOR')->nullable();
                $table->text('ASPEKPENILAIAN')->nullable();
                foreach (range(1, 6) as $nomor) {
                    $table->string(sprintf('PIL%02d', $nomor), 200)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('soalmonevabdimas');
        Schema::dropIfExists('soalmonevpenelitian');
    }
};
