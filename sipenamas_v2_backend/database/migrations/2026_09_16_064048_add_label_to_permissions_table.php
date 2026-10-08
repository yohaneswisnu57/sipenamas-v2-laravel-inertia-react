<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Label & deskripsi tampilan untuk permission, dikelola admin lewat halaman
 * Manajemen Menu Permission (edit teks saja - nama/slug permission itu
 * sendiri tetap didefinisikan developer di App\Support\Rbac\PermissionCatalog,
 * karena slug itu yang dicek middleware `permission:` di routes/api.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('description')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
