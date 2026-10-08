<?php

namespace Tests\Feature\Dekan;

use App\Models\Fakultas;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\ProdiAnggaran;
use App\Models\SkimPenelitian;
use App\Models\SumberDana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class AnggaranPaguTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
    }

    private function dekanFor(string $kodeFakultas): User
    {
        $dekan = User::factory()->create();

        Fakultas::create([
            'KODEFAKULTAS' => $kodeFakultas,
            'NAMAFAKULTAS' => "Fakultas {$kodeFakultas}",
            'KDDEKAN' => $dekan->kodeperson,
        ]);

        $dekan->givePermissionTo('view penelitian');
        Sanctum::actingAs($dekan);

        return $dekan;
    }

    private function prodiIn(string $kodeFakultas, string $kodeProdi): Prodi
    {
        return Prodi::create([
            'KODEPRODI' => $kodeProdi,
            'NAMAPRODI' => "Prodi {$kodeProdi}",
            'KDFAKULTAS' => $kodeFakultas,
        ]);
    }

    private function penelitian(string $kodeProdi, string $kodeSkim, array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Penelitian', 'JENIS_PA' => 'PENELITIAN',
            'PERIODEKEGIATAN_TAHUN' => 2026, 'KDPRODI' => $kodeProdi,
            'KDSKIMPENELITIAN' => $kodeSkim,
        ], $overrides));
    }

    public function test_sisa_anggaran_only_subtracts_approved_non_lppm_funding(): void
    {
        $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT', 'TI');
        ProdiAnggaran::create([
            'IDPARENT' => $prodi->id, 'KDPERIODE' => '2026', 'ALOKASIANGGARAN' => 100000000,
        ]);

        SumberDana::create(['KODESUMBERDANA' => 'FAK', 'NAMASUMBERDANA' => 'Dana Fakultas', 'ISDANALPPM' => 0]);
        SumberDana::create(['KODESUMBERDANA' => 'LPPM', 'NAMASUMBERDANA' => 'Dana LPPM', 'ISDANALPPM' => 1]);
        SkimPenelitian::create(['KODESKIM' => 'FAK01', 'NAMASKIM' => 'Skim Fakultas', 'DEFKDSUMBERDANA' => 'FAK']);
        SkimPenelitian::create(['KODESKIM' => 'LPPM01', 'NAMASKIM' => 'Skim LPPM', 'DEFKDSUMBERDANA' => 'LPPM']);

        $this->penelitian('TI', 'FAK01', [
            'NOMINALDANA' => 30000000, 'NOMINALDANA_FINAL' => 25000000, 'STATUSFINALAPPROVAL' => 'LOLOS',
        ]);
        $this->penelitian('TI', 'LPPM01', [
            'NOMINALDANA' => 40000000, 'NOMINALDANA_FINAL' => 40000000, 'STATUSFINALAPPROVAL' => 'LOLOS',
        ]);
        $this->penelitian('TI', 'FAK01', [
            'NOMINALDANA' => 10000000, 'STATUSFINALAPPROVAL' => 'TIDAK LOLOS',
        ]);

        $row = $this->getJson('/api/v1/dkn/anggaran-penelitian')
            ->assertOk()
            ->json('data.prodi.0');

        $this->assertEquals(80000000, $row['proposalPengajuan']);
        $this->assertEquals(25000000, $row['proposalDisetujui']);
        $this->assertEquals(40000000, $row['danaLppm']);
        $this->assertEquals(75000000, $row['sisaAnggaran']);
    }

    public function test_prodi_without_anggaran_row_is_listed_with_zero(): void
    {
        $this->dekanFor('FT');
        $this->prodiIn('FT', 'TI');

        $row = $this->getJson('/api/v1/dkn/anggaran-penelitian')
            ->assertOk()
            ->json('data.prodi.0');

        $this->assertSame('TI', $row['kodeProdi']);
        $this->assertEquals(0, $row['alokasiAnggaran']);
        $this->assertEquals(0, $row['sisaAnggaran']);
    }

    public function test_only_prodi_of_own_fakultas_is_returned(): void
    {
        $this->dekanFor('FT');
        $this->prodiIn('FT', 'TI');

        $lain = User::factory()->create();
        Fakultas::create(['KODEFAKULTAS' => 'FB', 'NAMAFAKULTAS' => 'Fakultas Bisnis', 'KDDEKAN' => $lain->kodeperson]);
        $this->prodiIn('FB', 'MN');

        $kode = $this->getJson('/api/v1/dkn/anggaran-penelitian')
            ->assertOk()
            ->json('data.prodi.*.kodeProdi');

        $this->assertSame(['TI'], $kode);
    }
}
