<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class DokumenProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('legacy_res');
        SpatiePermission::findOrCreate('create penelitian', 'sanctum');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');

        $this->ketua = User::factory()->create();
        $this->ketua->givePermissionTo('create penelitian', 'view penelitian');
        $this->actingAs($this->ketua, 'web');
    }

    private function usulan(bool $anggotaSetuju, array $overrides = []): Penelitian
    {
        $penelitian = Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Usulan Saya',
            'PERMOHONANDIBUAT_KDPERSON' => $this->ketua->kodeperson,
            'ISPENGAJUANFINAL' => 1,
            'ISDOKUMENPROPOSALFINAL' => 0,
            'LBRPENGESAHANPROPOSAL_ISFINAL' => 1,
        ], $overrides));
        $penelitian->tim()->create(['NIKNIDN' => $this->ketua->kodeperson, 'PERAN' => 'KETUA', 'ISAPPROVED' => 1]);
        $penelitian->tim()->create(['NIKNIDN' => 'P00001', 'PERAN' => 'ANGGOTA', 'ISAPPROVED' => $anggotaSetuju ? 1 : 0]);

        return $penelitian;
    }

    private function unggah(Penelitian $penelitian)
    {
        return $this->post("/pen/penelitian/{$penelitian->id}/dokumen-proposal", [
            'dokumenProposal' => UploadedFile::fake()->create('proposal.pdf', 500, 'application/pdf'),
        ]);
    }

    public function test_ketua_can_upload_proposal_after_all_anggota_approve(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: true);

        $this->unggah($penelitian)->assertAksiBerhasil('Dokumen proposal berhasil diunggah. Set dokumen final agar diteruskan ke Dekan');

        $this->get("/pen/penelitian/{$penelitian->id}")
            ->assertOk()
            ->assertProp('proposal.adaDokumenProposal', true)
            ->assertProp('proposal.isDokumenProposalFinal', false);

        $penelitian->refresh();
        $this->assertFalse($penelitian->ISDOKUMENPROPOSALFINAL);
        Storage::disk('legacy_res')->assertExists('proposal/'.$penelitian->FILE_DOKUMENPROPOSAL_FINAL);
    }

    public function test_upload_is_blocked_until_lembar_pengesahan_is_final(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: true, overrides: ['LBRPENGESAHANPROPOSAL_ISFINAL' => 0]);

        $this->unggah($penelitian)->assertAksiDitolak('Dokumen proposal baru bisa diunggah setelah lembar pengesahan di-set final');

        $this->assertNull($penelitian->fresh()->FILE_DOKUMENPROPOSAL_FINAL);
    }

    public function test_set_final_needs_an_uploaded_proposal_and_then_locks_it(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: true);
        $url = "/pen/penelitian/{$penelitian->id}/dokumen-proposal/final";

        $this->post($url)->assertAksiDitolak();

        $this->unggah($penelitian)->assertAksiBerhasil();
        $this->post($url)->assertAksiBerhasil('Dokumen proposal final dan diteruskan ke Dekan');

        $this->assertTrue($penelitian->fresh()->ISDOKUMENPROPOSALFINAL);
        $this->unggah($penelitian)->assertAksiDitolak();
        $this->put("/pen/penelitian/{$penelitian->id}/rencana-target", ['targetIds' => []])->assertAksiDitolak();
    }

    public function test_upload_is_blocked_while_an_anggota_has_not_approved(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: false);

        $this->unggah($penelitian)->assertAksiDitolak('Dokumen proposal baru bisa diunggah setelah semua anggota menyetujui keanggotaan');

        $this->assertFalse($penelitian->fresh()->ISDOKUMENPROPOSALFINAL);
    }

    public function test_upload_is_blocked_after_dekan_approval(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: true, overrides: ['APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1]);

        $this->unggah($penelitian)->assertAksiDitolak('Usulan sudah disetujui Dekan dan tidak dapat diubah');
    }

    public function test_anggota_cannot_upload_on_behalf_of_ketua(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: true, overrides: ['PERMOHONANDIBUAT_KDPERSON' => 'P77777']);

        $this->unggah($penelitian)->assertAksiDitolak('Data tidak ditemukan.');
    }

    public function test_only_pdf_is_accepted(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: true);

        $this->post("/pen/penelitian/{$penelitian->id}/dokumen-proposal", [
            'dokumenProposal' => UploadedFile::fake()->create('proposal.docx', 100),
        ])->assertSessionHasErrors('dokumenProposal');
    }

    public function test_ketua_can_preview_uploaded_draft_before_set_final(): void
    {
        $penelitian = $this->usulan(anggotaSetuju: true);
        $url = "/pen/penelitian/{$penelitian->id}/dokumen-proposal";

        $this->getJson($url)->assertNotFound();

        $this->unggah($penelitian)->assertAksiBerhasil();

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertFalse($penelitian->fresh()->ISDOKUMENPROPOSALFINAL);
    }
}
