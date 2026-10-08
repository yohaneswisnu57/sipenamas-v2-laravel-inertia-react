<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person', function (Blueprint $table) {
            $table->boolean('ISREVIEWERPENELITIAN')->nullable();
            $table->boolean('ISREVIEWERABDIMAS')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn(['ISREVIEWERPENELITIAN', 'ISREVIEWERABDIMAS']);
        });
    }
};
