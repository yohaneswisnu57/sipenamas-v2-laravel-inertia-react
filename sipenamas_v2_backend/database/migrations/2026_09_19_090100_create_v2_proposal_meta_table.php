<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel aditif baru - metadata usulan yang tidak punya kolom legacy:
     * apakah ini lanjutan dari penelitian/abdimas sebelumnya. Satu baris
     * per penelitian (unique), dibuat sekali saat submit
     * (App\Services\Proposal\ProposalSubmissionService).
     */
    public function up(): void
    {
        Schema::create('v2_proposal_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_id')->unique();
            $table->boolean('is_lanjutan_penelitian')->default(false);
            $table->boolean('is_lanjutan_abdimas')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_proposal_meta');
    }
};
