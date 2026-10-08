<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel aditif baru - tidak ada padanan di skema legacy dbsipenamas.
     * Menyimpan catatan keputusan Dekan (approve maupun reject), karena
     * legacy hanya punya `_MSG_PENOLAKANDEKAN` (khusus reject).
     * penelitian_id sengaja tanpa FK constraint (lihat catatan yang sama
     * di create_v2_pencairan_termin_table).
     */
    public function up(): void
    {
        Schema::create('v2_dekan_keputusan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_id')->index();
            $table->string('keputusan', 10);
            $table->text('catatan');
            $table->string('kodeperson_pemutus')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_dekan_keputusan');
    }
};
