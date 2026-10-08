<?php

namespace Tests\Feature\Peneliti;

use App\Models\IndexJurnal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

/**
 * Legacy pen subsidiapc: "Terindeks Dalam" dari cmbindexjurnalapc.php,
 * yaitu master `indexjurnal` dengan BOLEHAPC = 1.
 */
class SubsidiApcIndexJurnalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view subsidi apc', 'create subsidi apc'] as $izin) {
            SpatiePermission::findOrCreate($izin, 'sanctum');
        }
        $user = User::factory()->create();
        $user->givePermissionTo('view subsidi apc', 'create subsidi apc');
        $this->actingAs($user, 'web');

        IndexJurnal::create(['KODEINDEXJURNAL' => 'SQ1', 'NAMAINDEXJURNAL' => 'Scopus Q1', 'BOLEHAPC' => 1, 'URUTAN' => 1]);
        IndexJurnal::create(['KODEINDEXJURNAL' => 'S6', 'NAMAINDEXJURNAL' => 'SINTA 6', 'BOLEHAPC' => 0, 'URUTAN' => 2]);
    }

    private function payload(string $index): array
    {
        return ['judulArtikel' => 'Artikel APC', 'namaJurnal' => 'Jurnal APC', 'kategoriJurnal' => $index, 'nominalPengajuan' => 7500000];
    }

    public function test_apc_options_only_contain_bolehapc_indexes(): void
    {
        $kode = collect($this->getJson('/api/v1/index-jurnal?apc=1')->assertOk()->json('data'))->pluck('kode');

        $this->assertSame(['SQ1'], $kode->all());
    }

    public function test_store_saves_index_code_and_returns_index_name(): void
    {
        $this->post('/pen/subsidi-apc', $this->payload('SQ1'))
            ->assertAksiBerhasil('Permohonan subsidi APC berhasil dikirim');

        $this->get('/pen/subsidi-apc')
            ->assertOk()
            ->assertProp('apcList.0.kategoriJurnal', 'SQ1')
            ->assertProp('apcList.0.kategoriJurnalNama', 'Scopus Q1');

        $this->assertDatabaseHas('subsidiapc', ['INFOJURNAL_TERINDEKDALAM' => 'SQ1', 'NOMINALPENGAJUANAPC' => 7500000]);
    }

    public function test_index_not_allowed_for_apc_is_rejected(): void
    {
        $this->post('/pen/subsidi-apc', $this->payload('S6'))
            ->assertSessionHasErrors('kategoriJurnal');
    }
}
