<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periode', function (Blueprint $table) {
            $table->string('DESKRIPSI')->nullable();
        });

        Schema::table('periodegelombang', function (Blueprint $table) {
            $table->date('TGLPROPOSAL_FROM')->nullable();
            $table->date('TGLPROPOSAL_TO')->nullable();
            $table->date('TGLREVIEW_FROM')->nullable();
            $table->date('TGLREVIEW_TO')->nullable();
            $table->date('TGLREVISI_FROM')->nullable();
            $table->date('TGLLAPORAN_FROM')->nullable();
            $table->date('TGLLAPORAN_TO')->nullable();
            $table->date('TGLWAKTU_FROM')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('periodegelombang', function (Blueprint $table) {
            $table->dropColumn([
                'TGLPROPOSAL_FROM', 'TGLPROPOSAL_TO', 'TGLREVIEW_FROM', 'TGLREVIEW_TO',
                'TGLREVISI_FROM', 'TGLLAPORAN_FROM', 'TGLLAPORAN_TO', 'TGLWAKTU_FROM',
            ]);
        });

        Schema::table('periode', function (Blueprint $table) {
            $table->dropColumn('DESKRIPSI');
        });
    }
};
