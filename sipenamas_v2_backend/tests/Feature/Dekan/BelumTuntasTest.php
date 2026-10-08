<?php

namespace Tests\Feature\Dekan;

use App\Models\Fakultas;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class BelumTuntasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        Periode::create(['KODEPERIODE' => '2025', 'TAHUN' => 2025, 'ISAKTIF' => 0]);

        $dekan = User::factory()->create();
        Fakultas::create(['KODEFAKULTAS' => 'FT', 'NAMAFAKULTAS' => 'Fakultas Teknik', 'KDDEKAN' => $dekan->kodeperson]);
        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT']);
        $dekan->givePermissionTo('view penelitian');
        Sanctum::actingAs($dekan);
    }

    private function penelitian(array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Penelitian', 'JENIS_PA' => 'PENELITIAN',
            'PERIODEKEGIATAN_TAHUN' => 2026, 'KDPRODI' => 'TI',
            'STATUSFINALAPPROVAL' => 'LOLOS', 'STATUSKETUNTASANPENELITIAN' => '-',
        ], $overrides));
    }

    public function test_list_excludes_tuntas_and_tuntas_bersyarat_and_tidak_lolos(): void
    {
        $belum = $this->penelitian();
        $this->penelitian(['STATUSKETUNTASANPENELITIAN' => 'TUNTAS']);
        $this->penelitian(['STATUSKETUNTASANPENELITIAN' => 'TUNTAS BERSYARAT']);
        $this->penelitian(['STATUSFINALAPPROVAL' => 'TIDAK LOLOS']);

        $ids = $this->getJson('/api/v1/dkn/belum-tuntas')->assertOk()->json('data.*.id');

        $this->assertEquals([$belum->id], $ids);
    }

    public function test_period_filter_narrows_the_list_and_all_keeps_every_year(): void
    {
        $tahunIni = $this->penelitian();
        $tahunLalu = $this->penelitian(['PERIODEKEGIATAN_TAHUN' => 2025]);

        $ids = $this->getJson('/api/v1/dkn/belum-tuntas?kdperiode=2026')->assertOk()->json('data.*.id');
        $this->assertEquals([$tahunIni->id], $ids);

        $semua = $this->getJson('/api/v1/dkn/belum-tuntas?kdperiode=ALL')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$tahunIni->id, $tahunLalu->id], $semua);
    }

    public function test_other_fakultas_proposal_is_hidden(): void
    {
        $lain = User::factory()->create();
        Fakultas::create(['KODEFAKULTAS' => 'FB', 'NAMAFAKULTAS' => 'Fakultas Bisnis', 'KDDEKAN' => $lain->kodeperson]);
        Prodi::create(['KODEPRODI' => 'MN', 'NAMAPRODI' => 'Manajemen', 'KDFAKULTAS' => 'FB']);

        $milik = $this->penelitian();
        $this->penelitian(['KDPRODI' => 'MN']);

        $ids = $this->getJson('/api/v1/dkn/belum-tuntas')->assertOk()->json('data.*.id');

        $this->assertEquals([$milik->id], $ids);
    }
}
