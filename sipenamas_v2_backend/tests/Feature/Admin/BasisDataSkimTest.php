<?php

namespace Tests\Feature\Admin;

use App\Models\SkimPenelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class BasisDataSkimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view basis data', 'sanctum');
        SpatiePermission::findOrCreate('manage basis data', 'sanctum');

        $admin = User::factory()->create();
        $admin->givePermissionTo(['view basis data', 'manage basis data']);
        Sanctum::actingAs($admin);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'KODESKIM' => 'INT01',
            'NAMASKIM' => 'Penelitian Dasar',
            'MINANGGOTA' => 1,
            'MAXANGGOTA' => 3,
            'ISABDIMAS' => false,
            'ISSTATUSPEMAPARAN' => false,
            'ISOPENBUDGET' => false,
            'ISAKTIF' => true,
        ], $overrides);
    }

    public function test_store_saves_anggaran_per_penelitian(): void
    {
        $this->postJson('/api/v1/adm/basisdata/skim-penelitian', $this->payload([
            'ANGGARANPERPENELITIAN' => 25000000,
        ]))->assertCreated();

        $this->assertEquals(25000000, SkimPenelitian::first()->ANGGARANPERPENELITIAN);
    }

    public function test_update_changes_anggaran_per_penelitian(): void
    {
        $skim = SkimPenelitian::create($this->payload(['ANGGARANPERPENELITIAN' => 10000000]));

        $this->putJson("/api/v1/adm/basisdata/skim-penelitian/{$skim->id}", $this->payload([
            'ANGGARANPERPENELITIAN' => 40000000,
        ]))->assertOk();

        $this->assertEquals(40000000, $skim->fresh()->ANGGARANPERPENELITIAN);
    }

    public function test_anggaran_per_penelitian_rejects_negative_value(): void
    {
        $this->postJson('/api/v1/adm/basisdata/skim-penelitian', $this->payload([
            'ANGGARANPERPENELITIAN' => -1,
        ]))->assertStatus(422)->assertJsonValidationErrors('ANGGARANPERPENELITIAN');
    }
}
