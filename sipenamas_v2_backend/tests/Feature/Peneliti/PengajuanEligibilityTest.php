<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Person;
use App\Models\Settingan;
use App\Models\SkimPenelitian;
use App\Models\SumberDana;
use App\Models\TabelRencanaTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class PengajuanEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('create penelitian', 'sanctum');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');

        SumberDana::create(['KODESUMBERDANA' => 'SD02', 'NAMASUMBERDANA' => 'Dana LPPM', 'ISDANALPPM' => 1]);
        SkimPenelitian::create([
            'KODESKIM' => 'INT01',
            'NAMASKIM' => 'Penelitian Dasar',
            'ISAKTIF' => 1,
            'ISABDIMAS' => 0,
            'MINANGGOTA' => 1,
            'MAXANGGOTA' => 2,
            'ANGGARANPERPENELITIAN' => 15000000,
            'DEFKDSUMBERDANA' => 'SD02',
        ]);
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        Settingan::create([
            'THISISIT' => 'arief@nuriman.id',
            'KUOTA_PENELITIAN_KETUA' => 1,
            'KUOTA_PENELITIAN_ANGGOTA' => 5,
        ]);
        Person::create(['KODEPERSON' => 'P00001', 'NAMALENGKAP' => 'Dosen Anggota']);

        $this->ketua = User::factory()->create();
        $this->ketua->givePermissionTo('create penelitian', 'view penelitian');
        $this->actingAs($this->ketua, 'web');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'judul' => 'Usulan Uji Kelayakan',
            'skimKode' => 'INT01',
            'sumberDanaKode' => 'SD02',
            'biayaUsulan' => 10000000,
            'komposisiBahanPeralatan' => 50, 'komposisiPerjalanan' => 15, 'komposisiLaporan' => 5,
            'anggotaDosen' => [['npp' => 'P00001']],
        ], $overrides);
    }

    private function penelitianLama(string $nik, string $peran, array $overrides = []): Penelitian
    {
        $penelitian = Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Penelitian Lama',
            'JENIS_PA' => 'PENELITIAN',
            'KDSKIMPENELITIAN' => 'INT01',
            'PERIODEKEGIATAN_TAHUN' => 2025,
            'STATUSFINALAPPROVAL' => 'LOLOS',
            'STATUSKETUNTASANPENELITIAN' => 'BELUM TUNTAS',
        ], $overrides));
        $penelitian->tim()->create(['NIKNIDN' => $nik, 'PERAN' => $peran, 'ISAPPROVED' => 1]);

        return $penelitian;
    }

    public function test_submission_fills_legacy_periode_year_and_default_sumber_dana(): void
    {
        $this->post('/pen/penelitian', $this->payload())->assertAksiBerhasil();

        $this->assertDatabaseHas('penelitian', [
            'id' => Penelitian::max('id'),
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'KDSUMBERDANA' => 'SD02',
            'ISDOKUMENPROPOSALFINAL' => 0,
        ]);
    }

    public function test_submission_is_rejected_without_an_active_periode(): void
    {
        Periode::query()->update(['ISAKTIF' => 0]);

        $this->post('/pen/penelitian', $this->payload())
            ->assertSessionHasErrors('periode');
    }

    public function test_ketua_with_unfinished_previous_research_is_blocked(): void
    {
        $this->penelitianLama($this->ketua->kodeperson, 'ANGGOTA');

        $this->post('/pen/penelitian', $this->payload())
            ->assertSessionHasErrors('skimKode');
    }

    public function test_finished_previous_research_does_not_block(): void
    {
        $this->penelitianLama($this->ketua->kodeperson, 'KETUA', ['STATUSKETUNTASANPENELITIAN' => 'TUNTAS BERSYARAT']);

        $this->post('/pen/penelitian', $this->payload())->assertAksiBerhasil();
    }

    public function test_anggota_with_unfinished_previous_research_is_blocked(): void
    {
        $this->penelitianLama('P00001', 'KETUA');

        $this->post('/pen/penelitian', $this->payload())
            ->assertSessionHasErrors('anggotaDosen.0.npp');
    }

    public function test_ketua_quota_per_periode_is_enforced(): void
    {
        $this->penelitianLama($this->ketua->kodeperson, 'KETUA', [
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'STATUSFINALAPPROVAL' => '-',
        ]);

        $this->post('/pen/penelitian', $this->payload())
            ->assertSessionHasErrors('skimKode');
    }

    public function test_jumlah_anggota_must_follow_skim_limits(): void
    {
        $this->post('/pen/penelitian', $this->payload(['anggotaDosen' => []]))
            ->assertSessionHasErrors('anggotaDosen');
    }

    public function test_ketua_cannot_be_listed_as_anggota(): void
    {
        Person::create(['KODEPERSON' => $this->ketua->kodeperson, 'NAMALENGKAP' => 'Ketua']);

        $this->post('/pen/penelitian', $this->payload(['anggotaDosen' => [['npp' => $this->ketua->kodeperson]]]))
            ->assertSessionHasErrors('anggotaDosen.0.npp');
    }

    public function test_duplicate_anggota_is_rejected(): void
    {
        $this->post('/pen/penelitian', $this->payload(['anggotaDosen' => [['npp' => 'P00001'], ['npp' => 'P00001']]]))
            ->assertSessionHasErrors('anggotaDosen.0.npp');
    }

    public function test_biaya_above_skim_budget_is_rejected(): void
    {
        $this->post('/pen/penelitian', $this->payload(['biayaUsulan' => 15000001]))
            ->assertSessionHasErrors('biayaUsulan');
    }

    public function test_rencana_target_is_generated_from_skim_master_with_wajib_items_checked(): void
    {
        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'Publikasi', 'SUBKATEGORI' => 'Jurnal', 'ISWAJIB' => 1, 'URUTAN' => 1]);
        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'HKI', 'SUBKATEGORI' => 'Hak Cipta', 'ISWAJIB' => 0, 'URUTAN' => 2]);

        $this->post('/pen/penelitian', $this->payload())->assertAksiBerhasil();
        $id = (int) Penelitian::max('id');

        $target = $this->get("/pen/penelitian/{$id}")->assertOk()->inertiaProp('rencanaTarget');
        $this->assertSame([true, false], array_column($target, 'isDipilih'));

        $this->put("/pen/penelitian/{$id}/rencana-target", ['targetIds' => [$target[1]['id']]])
            ->assertAksiBerhasil('Rencana target luaran disimpan');

        $this->get("/pen/penelitian/{$id}")
            ->assertProp('rencanaTarget.0.isDipilih', true)
            ->assertProp('rencanaTarget.1.isDipilih', true);
    }

    public function test_rencana_target_skips_duplicates_with_same_kategori_and_subkategori(): void
    {
        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'Publikasi', 'SUBKATEGORI' => 'Jurnal Internasional', 'ISWAJIB' => 1, 'URUTAN' => 1]);
        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'Publikasi', 'SUBKATEGORI' => 'Jurnal Internasional', 'ISWAJIB' => 1, 'URUTAN' => 2]);
        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'HKI', 'SUBKATEGORI' => 'Paten', 'ISWAJIB' => 0, 'URUTAN' => 3]);

        $this->post('/pen/penelitian', $this->payload())->assertAksiBerhasil();
        $id = (int) Penelitian::max('id');

        $target = $this->get("/pen/penelitian/{$id}")->assertOk()->inertiaProp('rencanaTarget');
        $this->assertCount(2, $target);
        $this->assertSame('Publikasi', $target[0]['kategori']);
        $this->assertSame('Jurnal Internasional', $target[0]['subkategori']);
        $this->assertSame('HKI', $target[1]['kategori']);
        $this->assertSame('Paten', $target[1]['subkategori']);
    }
}
