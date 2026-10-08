<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncUsersFromUwmsdmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // `uwmsdm` is a real read-only pgsql connection in production; for
        // tests we point it at its own in-memory sqlite database and build
        // just the two tables/columns the command actually selects from.
        Config::set('database.connections.uwmsdm', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('uwmsdm');

        Schema::connection('uwmsdm')->create('sc_user', function (Blueprint $table) {
            $table->string('userid');
            $table->string('statususer');
        });

        Schema::connection('uwmsdm')->create('ms_pegawai', function (Blueprint $table) {
            $table->string('nip')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nama')->nullable();
            $table->string('email_inst')->nullable();
            $table->string('email')->nullable();
            $table->string('idhomebase')->nullable();
            $table->string('idstatusaktif')->nullable();
        });
    }

    public function test_matches_existing_user_by_email_when_kodeperson_and_nidn_both_changed(): void
    {
        // Staff (non-dosen) tanpa NIDN, NIP-nya berubah sejak akun lokal
        // terakhir dibuat/disync. Tanpa fallback email, ini akan membuat
        // User baru tanpa role, memisahkannya dari akun lama yang punya
        // role.
        $user = User::factory()->create([
            'kodeperson' => 'P_OLD',
            'nidn' => null,
            'email' => 'staff@ukwms.ac.id',
        ]);

        DB::connection('uwmsdm')->table('sc_user')->insert([
            'userid' => 'P_NEW',
            'statususer' => '1',
        ]);
        DB::connection('uwmsdm')->table('ms_pegawai')->insert([
            'nip' => 'P_NEW',
            'nidn' => null,
            'nama' => 'Staff Pindah Unit',
            'email_inst' => 'staff@ukwms.ac.id',
            'email' => null,
        ]);

        $this->artisan('rbac:sync-users-from-uwmsdm')->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'kodeperson' => 'P_NEW',
            'email' => 'staff@ukwms.ac.id',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_creates_new_user_when_no_kodeperson_nidn_or_email_matches(): void
    {
        DB::connection('uwmsdm')->table('sc_user')->insert([
            'userid' => 'P_BRAND_NEW',
            'statususer' => '1',
        ]);
        DB::connection('uwmsdm')->table('ms_pegawai')->insert([
            'nip' => 'P_BRAND_NEW',
            'nidn' => null,
            'nama' => 'Orang Baru',
            'email_inst' => 'baru@ukwms.ac.id',
            'email' => null,
        ]);

        $this->artisan('rbac:sync-users-from-uwmsdm')->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'kodeperson' => 'P_BRAND_NEW',
            'email' => 'baru@ukwms.ac.id',
        ]);
    }
}
