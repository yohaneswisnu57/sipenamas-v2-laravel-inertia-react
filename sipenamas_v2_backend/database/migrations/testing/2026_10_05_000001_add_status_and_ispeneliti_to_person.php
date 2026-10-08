<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person', function (Blueprint $table) {
            $table->string('STATUSNYA')->nullable();
            $table->boolean('ISPENELITI')->nullable();
            $table->boolean('ISSUPERUSER')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn(['STATUSNYA', 'ISPENELITI', 'ISSUPERUSER']);
        });
    }
};
