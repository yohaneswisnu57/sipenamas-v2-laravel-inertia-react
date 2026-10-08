<?php

namespace Tests\Feature\Peneliti;

use App\Models\IndexJurnal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

/**
 * Legacy pen insentivejurnal: "Terindeks Dalam" dipilih dari master
 * `indexjurnal` (cmbindexjurnal.php); GETJENISPUBLIKASI mengisi jenis
 * publikasi dan hanya index ber-ADAINSENTIF yang boleh mengajukan insentif.
 * Tidak ada nominal reward di tabel legacy.
 */
class InsentifJurnalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view insentif', 'create insentif'] as $izin) {
            SpatiePermission::findOrCreate($izin, 'sanctum');
        }
        $user = User::factory()->create();
        $user->givePermissionTo('view insentif', 'create insentif');
        $this->actingAs($user, 'web');

        IndexJurnal::create(['KODEINDEXJURNAL' => 'SQ1', 'NAMAINDEXJURNAL' => 'Scopus Q1', 'KDJENISPUBLIKASI' => 'JI', 'ADAINSENTIF' => 1, 'URUTAN' => 1]);
        IndexJurnal::create(['KODEINDEXJURNAL' => 'S6', 'NAMAINDEXJURNAL' => 'SINTA 6', 'KDJENISPUBLIKASI' => 'JN', 'ADAINSENTIF' => 0, 'URUTAN' => 2]);
    }

    private function payload(string $index): array
    {
        return ['judulArtikel' => 'Artikel Uji', 'namaJurnal' => 'Jurnal Uji', 'tingkatJurnal' => $index, 'tahunTerbit' => '2026'];
    }

    public function test_index_jurnal_options_come_from_master_table(): void
    {
        $this->getJson('/api/v1/index-jurnal')
            ->assertOk()
            ->assertJsonPath('data.0.kode', 'SQ1')
            ->assertJsonPath('data.0.nama', 'Scopus Q1')
            ->assertJsonPath('data.1.adaInsentif', false);
    }

    public function test_store_saves_index_code_and_jenis_publikasi_from_master(): void
    {
        $this->post('/pen/insentif-jurnal', $this->payload('SQ1'))
            ->assertAksiBerhasil('Pengajuan insentif publikasi berhasil disimpan');

        $insentif = $this->get('/pen/insentif-jurnal')->assertOk()->inertiaProp('insentifList.0');
        $this->assertSame('SQ1', $insentif['tingkatJurnal']);
        $this->assertSame('Scopus Q1', $insentif['tingkatJurnalNama']);
        $this->assertArrayNotHasKey('nominalReward', $insentif);

        $this->assertDatabaseHas('insentif', [
            'INFOJURNAL_TERINDEKDALAM' => 'SQ1',
            'KDJENISPUBLIKASI' => 'JI',
            'ISMENGAJUKANINSENTIF' => 1,
        ]);
    }

    public function test_index_without_insentif_does_not_request_insentif(): void
    {
        $this->post('/pen/insentif-jurnal', $this->payload('S6'))->assertAksiBerhasil();

        $this->assertDatabaseHas('insentif', ['INFOJURNAL_TERINDEKDALAM' => 'S6', 'ISMENGAJUKANINSENTIF' => 0]);
    }

    public function test_unknown_index_is_rejected(): void
    {
        $this->post('/pen/insentif-jurnal', $this->payload('Scopus Q1'))
            ->assertSessionHasErrors('tingkatJurnal');
    }

    public function test_list_shows_the_index_name(): void
    {
        $this->post('/pen/insentif-jurnal', $this->payload('SQ1'))->assertAksiBerhasil();

        $this->get('/pen/insentif-jurnal')
            ->assertOk()
            ->assertProp('insentifList.0.tingkatJurnalNama', 'Scopus Q1');
    }
}
