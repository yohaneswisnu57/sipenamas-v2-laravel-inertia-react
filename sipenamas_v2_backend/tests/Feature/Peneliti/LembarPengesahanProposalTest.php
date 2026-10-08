<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;
use ZipArchive;

class LembarPengesahanProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class) || ! extension_loaded('gd')) {
            $this->markTestSkipped('Butuh ekstensi zip dan gd.');
        }

        Storage::fake('legacy_res');
        SpatiePermission::findOrCreate('create penelitian', 'sanctum');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');

        $this->ketua = User::factory()->create();
        $this->ketua->givePermissionTo('create penelitian', 'view penelitian');
        Person::create(['KODEPERSON' => $this->ketua->kodeperson, 'NAMALENGKAP' => 'Dr. Ketua']);
        $this->actingAs($this->ketua, 'web');
    }

    private function usulan(array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Usulan Saya',
            'PERMOHONANDIBUAT_KDPERSON' => $this->ketua->kodeperson,
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ], $overrides));
    }

    /** Jumlah gambar QR tanda tangan di dalam docx tersimpan. */
    private function jumlahGambar(string $namaFile): int
    {
        $zip = new ZipArchive;
        $zip->open(Storage::disk('legacy_res')->path('proposal/'.$namaFile));
        $jumlah = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $jumlah += str_starts_with($zip->getNameIndex($i), 'word/media/') ? 1 : 0;
        }
        $zip->close();

        return $jumlah;
    }

    public function test_generate_needs_dana_penyertaan_first(): void
    {
        $penelitian = $this->usulan();

        $this->post("/pen/penelitian/{$penelitian->id}/lembar-pengesahan")
            ->assertAksiDitolak('Silahkan melengkapi isian Dana Penyertaan terlebih dahulu.');
    }

    public function test_dana_penyertaan_then_generate_then_final(): void
    {
        $penelitian = $this->usulan();
        $base = "/pen/penelitian/{$penelitian->id}";

        $this->post("{$base}/lembar-pengesahan/final")->assertAksiDitolak('Dokumen belum digenerate!');

        $this->put("{$base}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 250000])
            ->assertAksiBerhasil('Dana Penyertaan sudah diupdate');

        $this->post("{$base}/lembar-pengesahan")->assertAksiBerhasil('Lembar pengesahan proposal dibuat');

        $this->get($base)
            ->assertOk()
            ->assertProp('proposal.danaInkind', 250000)
            ->assertProp('proposal.adaLembarPengesahan', true)
            ->assertProp('proposal.isLembarPengesahanFinal', false);

        $namaFile = "lbrpengesahan_proposal_{$penelitian->id}.docx";
        $this->assertSame($namaFile, $penelitian->fresh()->LBRPENGESAHANPROPOSAL_NAMAFILE);
        $sebelumFinal = $this->jumlahGambar($namaFile);

        $this->post("{$base}/lembar-pengesahan/final")->assertAksiBerhasil('Lembar pengesahan berstatus final');
        $this->get($base)->assertProp('proposal.isLembarPengesahanFinal', true);

        $this->assertDatabaseHas('penelitian', ['id' => $penelitian->id, 'LBRPENGESAHANPROPOSAL_ISFINAL' => 1, 'LBRPENGESAHANPROPOSAL_DANAINKIND' => 250000]);
        $this->assertGreaterThan($sebelumFinal, $this->jumlahGambar($namaFile), 'Tanda tangan ketua baru muncul setelah SET FINAL');
    }

    public function test_final_lembar_is_locked(): void
    {
        $penelitian = $this->usulan(['LBRPENGESAHANPROPOSAL_NAMAFILE' => 'x.docx', 'LBRPENGESAHANPROPOSAL_ISFINAL' => 1]);
        $base = "/pen/penelitian/{$penelitian->id}";

        $this->put("{$base}/dana-penyertaan", ['danaMitra' => 1, 'danaInkind' => 1])->assertAksiDitolak();
        $this->post("{$base}/lembar-pengesahan")->assertAksiDitolak();
        $this->post("{$base}/lembar-pengesahan/final")->assertAksiDitolak();
    }

    public function test_draft_usulan_cannot_generate_lembar(): void
    {
        $penelitian = $this->usulan(['ISPENGAJUANFINAL' => 0]);

        $this->put("/pen/penelitian/{$penelitian->id}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 0])->assertAksiDitolak();
    }

    public function test_only_ketua_can_manage_the_lembar(): void
    {
        $penelitian = $this->usulan(['PERMOHONANDIBUAT_KDPERSON' => 'P77777']);

        $this->put("/pen/penelitian/{$penelitian->id}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 0])->assertAksiDitolak('Data tidak ditemukan.');
        $this->post("/pen/penelitian/{$penelitian->id}/lembar-pengesahan")->assertAksiDitolak('Data tidak ditemukan.');
    }
}
