<?php

namespace Tests\Feature\Peneliti;

use App\Models\Person;
use App\Models\User;
use App\Services\Proposal\KetuaLppmResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KetuaLppmResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_nik_and_nama_from_person_when_configured(): void
    {
        config(['pengesahan.ketua_lppm_kodeperson' => '521970284']);
        Person::create(['KODEPERSON' => '521970284', 'NAMALENGKAP' => 'Wenny Irawaty']);

        $this->assertSame(['nik' => '521970284', 'nama' => 'Wenny Irawaty'], (new KetuaLppmResolver)->resolve());
    }

    public function test_falls_back_to_users_table_when_person_missing(): void
    {
        config(['pengesahan.ketua_lppm_kodeperson' => '521970284']);
        User::factory()->create(['kodeperson' => '521970284', 'nama' => 'Wenny Irawaty']);

        $this->assertSame('Wenny Irawaty', (new KetuaLppmResolver)->resolve()['nama']);
    }

    public function test_returns_null_when_not_configured_or_unknown(): void
    {
        config(['pengesahan.ketua_lppm_kodeperson' => null]);
        $this->assertNull((new KetuaLppmResolver)->resolve());

        config(['pengesahan.ketua_lppm_kodeperson' => '999']);
        $this->assertNull((new KetuaLppmResolver)->resolve());
    }
}
