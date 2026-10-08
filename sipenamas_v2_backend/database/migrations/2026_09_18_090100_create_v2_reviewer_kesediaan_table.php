<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel aditif baru - sumber kebenaran eksplisit untuk membedakan
     * "reviewer belum merespon" vs "reviewer menolak" per baris
     * `penelitian_reviewer`, karena legacy ISAPPROVED (0/1) tidak bisa
     * dijamin membedakan dua kondisi itu tanpa validasi data produksi
     * (lihat App\Domain\Proposal\ProposalStatusResolver). Tanpa FK
     * constraint ke penelitian_reviewer (tabel legacy).
     */
    public function up(): void
    {
        Schema::create('v2_reviewer_kesediaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penelitian_reviewer_id')->unique();
            $table->boolean('bersedia')->nullable();
            $table->text('alasan')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_reviewer_kesediaan');
    }
};
