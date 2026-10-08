<?php

namespace Tests\Feature\Peneliti;

use App\Enums\RevisiStatus;
use App\Models\Penelitian;
use App\Models\PenelitianPenilaianproposal;
use App\Models\PenelitianPenilaianproposalRevisi;
use App\Models\PenelitianReviewer;
use App\Models\Periode;
use App\Models\PeriodeGelombang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class SubmitRevisiTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('legacy_res');
        SpatiePermission::findOrCreate('submit revisi penelitian', 'sanctum');

        $this->ketua = User::factory()->create();
        $this->ketua->givePermissionTo('submit revisi penelitian');
        $this->actingAs($this->ketua, 'web');
    }

    private function usulan(bool $withVerifikator = true, string $statusPenilaian = 'FINAL'): Penelitian
    {
        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Perlu Revisi',
            'PERMOHONANDIBUAT_KDPERSON' => $this->ketua->kodeperson,
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
            'STATUSPENILAIANREVIEWER' => 'PERBAIKAN',
        ]);

        $penelitian->reviewers()->create(['NIK' => 'REV001', 'STATUSPENILAIAN' => $statusPenilaian]);
        $penelitian->reviewers()->create([
            'NIK' => 'REV009',
            'STATUSPENILAIAN' => 'FINAL',
            'ISREVIEWERREVISI' => $withVerifikator ? 1 : 0,
            'STATUSPENILAIANREVISI' => 'DRAFT',
        ]);

        return $penelitian;
    }

    private function komentar(Penelitian $penelitian, string $nikReviewer, string $teks): PenelitianPenilaianproposalRevisi
    {
        $reviewer = PenelitianReviewer::where('IDPARENT', $penelitian->id)->where('NIK', $nikReviewer)->firstOrFail();
        $penilaian = PenelitianPenilaianproposal::firstOrCreate(['IDPARENT' => $penelitian->id, 'IDREVIEWER' => $reviewer->id]);

        return PenelitianPenilaianproposalRevisi::create(['IDPARENT' => $penilaian->id, 'KOMENREVISI' => $teks]);
    }

    public function test_ketua_sees_comments_of_all_reviewers_as_a_thread(): void
    {
        $penelitian = $this->usulan();
        $this->komentar($penelitian, 'REV001', 'Perjelas metodologi');
        $this->komentar($penelitian, 'REV009', 'Rasionalisasi anggaran');
        $lain = $this->usulan();
        $this->komentar($lain, 'REV001', 'Komentar usulan lain');

        $this->get("/pen/revisi/{$penelitian->id}")
            ->assertOk()
            ->assertProp('revisi.komentar.*.komentar', ['Perjelas metodologi', 'Rasionalisasi anggaran'])
            ->assertProp('revisi.komentar.*.reviewerKe', [1, 2])
            ->assertProp('revisi.komentar.0.respon', null)
            ->assertProp('revisi.alasanTertutup', null)
            ->assertProp('revisi.revisiStatus', RevisiStatus::MENUNGGU_UPLOAD->value)
            ->assertProp('revisi.isFinal', false);
    }

    public function test_ketua_replies_to_a_comment_and_can_edit_the_reply(): void
    {
        $penelitian = $this->usulan();
        $komentar = $this->komentar($penelitian, 'REV001', 'Perjelas metodologi');
        $url = "/pen/penelitian/{$penelitian->id}/revisi/komentar/{$komentar->id}";

        $this->put($url, ['respon' => 'Sudah ditambah'])->assertAksiBerhasil('Tanggapan disimpan');
        $this->get("/pen/revisi/{$penelitian->id}")->assertProp('revisi.komentar.0.respon', 'Sudah ditambah');
        $this->put($url, ['respon' => 'Sudah ditambah di bab 3'])->assertAksiBerhasil();

        $this->assertDatabaseHas('penelitian_penilaianproposal_revisi', ['id' => $komentar->id, 'KOMENRESPON' => 'Sudah ditambah di bab 3']);
    }

    public function test_reply_is_refused_for_a_comment_of_another_usulan(): void
    {
        $penelitian = $this->usulan();
        $komentarLain = $this->komentar($this->usulan(), 'REV001', 'Komentar usulan lain');

        $this->put("/pen/penelitian/{$penelitian->id}/revisi/komentar/{$komentarLain->id}", ['respon' => 'x'])
            ->assertAksiDitolak('Data tidak ditemukan.');
    }

    public function test_revisi_stays_closed_until_every_reviewer_is_final(): void
    {
        $penelitian = $this->usulan(statusPenilaian: 'DRAFT');
        $komentar = $this->komentar($penelitian, 'REV009', 'Perjelas metodologi');

        $this->get("/pen/revisi/{$penelitian->id}")
            ->assertOk()
            ->assertProp('revisi.alasanTertutup', 'MAAF, PROSES REVIEW BELUM SELESAI.');
        $this->put("/pen/penelitian/{$penelitian->id}/revisi/komentar/{$komentar->id}", ['respon' => 'x'])->assertAksiDitolak();
        $this->unggahDraft($penelitian)->assertAksiDitolak();
        $this->post("/pen/penelitian/{$penelitian->id}/revisi/final")->assertAksiDitolak();
    }

    public function test_revisi_is_closed_after_the_revision_period_ends(): void
    {
        $periode = Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        PeriodeGelombang::create(['IDPARENT' => $periode->id, 'GELAKTIF' => 1, 'TGLREVISI_TO' => now()->subDay()->toDateString()]);
        $penelitian = $this->usulan();

        $this->unggahDraft($penelitian)->assertAksiDitolak('MASA REVISI SUDAH BERAKHIR.');
    }

    private function unggahDraft(Penelitian $penelitian, string $nama = 'revisi.pdf'): TestResponse
    {
        return $this->post("/pen/penelitian/{$penelitian->id}/revisi/dokumen", [
            'dokumenRevisi' => UploadedFile::fake()->create($nama, 500, 'application/pdf'),
        ]);
    }

    public function test_uploading_a_draft_stores_the_document_without_sending_it_to_the_verifikator(): void
    {
        $penelitian = $this->usulan();

        $this->unggahDraft($penelitian)->assertAksiBerhasil('Draft naskah revisi disimpan');

        $penelitian->refresh();
        Storage::disk('legacy_res')->assertExists('proposal/'.$penelitian->FILE_DOKUMENPROPOSAL_REV);
        $this->assertSame($penelitian->FILE_DOKUMENPROPOSAL_REV, $penelitian->FILE_DOKUMENPROPOSAL_FINAL);
        $this->assertNotNull($penelitian->TS_UPLOADPROPOSALREVISI);
        $this->assertSame(RevisiStatus::MENUNGGU_UPLOAD, $penelitian->activeRevisiCycle()->status);
    }

    public function test_uploading_again_replaces_the_previous_draft(): void
    {
        $penelitian = $this->usulan();
        $this->unggahDraft($penelitian, 'pertama.pdf')->assertAksiBerhasil();
        $lama = $penelitian->refresh()->FILE_DOKUMENPROPOSAL_REV;

        $this->unggahDraft($penelitian, 'kedua.pdf')->assertAksiBerhasil();

        $baru = $penelitian->refresh()->FILE_DOKUMENPROPOSAL_REV;
        $this->assertNotSame($lama, $baru);
        Storage::disk('legacy_res')->assertMissing('proposal/'.$lama);
        Storage::disk('legacy_res')->assertExists('proposal/'.$baru);
    }

    public function test_draft_can_be_uploaded_before_a_verifikator_is_assigned(): void
    {
        $penelitian = $this->usulan(withVerifikator: false);

        $this->unggahDraft($penelitian)->assertAksiBerhasil();
    }

    public function test_ketua_can_preview_the_uploaded_draft(): void
    {
        $penelitian = $this->usulan();
        $this->getJson("/pen/penelitian/{$penelitian->id}/revisi/dokumen")->assertNotFound();

        $this->unggahDraft($penelitian)->assertAksiBerhasil();

        $this->get("/pen/penelitian/{$penelitian->id}/revisi/dokumen")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_setting_final_moves_the_cycle_to_menunggu_verifikasi(): void
    {
        $penelitian = $this->usulan();
        $this->unggahDraft($penelitian)->assertAksiBerhasil();

        $this->post("/pen/penelitian/{$penelitian->id}/revisi/final")
            ->assertAksiBerhasil('Revisi proposal final dan dikirim ke reviewer');

        $penelitian->refresh();
        $this->assertSame(RevisiStatus::MENUNGGU_VERIFIKASI, $penelitian->activeRevisiCycle()->status);
        $this->assertSame('REV009', $penelitian->activeRevisiCycle()->verifikator->NIK);
    }

    public function test_setting_final_requires_an_uploaded_draft(): void
    {
        $penelitian = $this->usulan();

        $this->post("/pen/penelitian/{$penelitian->id}/revisi/final")
            ->assertAksiDitolak('Naskah revisi belum diunggah');
        $this->assertSame(0, (int) $penelitian->refresh()->ISDOKUMENPROPOSALREVISIFINAL);
    }

    public function test_replies_and_document_are_locked_after_revisi_is_final(): void
    {
        $penelitian = $this->usulan();
        $komentar = $this->komentar($penelitian, 'REV001', 'Perjelas metodologi');
        $this->unggahDraft($penelitian)->assertAksiBerhasil();

        $this->post("/pen/penelitian/{$penelitian->id}/revisi/final")->assertAksiBerhasil();

        $this->put("/pen/penelitian/{$penelitian->id}/revisi/komentar/{$komentar->id}", ['respon' => 'telat'])
            ->assertAksiDitolak();
        $this->unggahDraft($penelitian)->assertAksiDitolak('Revisi sudah dikirim dan tidak dapat diubah lagi');
        $this->get("/pen/revisi/{$penelitian->id}")->assertProp('revisi.isFinal', true);
    }

    public function test_setting_final_fails_when_no_verifikator_has_been_assigned_yet(): void
    {
        $penelitian = $this->usulan(withVerifikator: false);
        $this->unggahDraft($penelitian)->assertAksiBerhasil();

        $this->post("/pen/penelitian/{$penelitian->id}/revisi/final")
            ->assertAksiDitolak();
    }

    public function test_non_pdf_revision_document_is_rejected(): void
    {
        $penelitian = $this->usulan();

        $this->post("/pen/penelitian/{$penelitian->id}/revisi/dokumen", [
            'dokumenRevisi' => UploadedFile::fake()->create('revisi.docx', 500, 'application/msword'),
        ])->assertSessionHasErrors(['dokumenRevisi']);
    }

    public function test_upload_without_a_document_is_rejected(): void
    {
        $penelitian = $this->usulan();

        $this->post("/pen/penelitian/{$penelitian->id}/revisi/dokumen")
            ->assertSessionHasErrors(['dokumenRevisi']);
    }

    public function test_only_the_ketua_can_open_the_revisi(): void
    {
        $lain = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Orang Lain', 'PERMOHONANDIBUAT_KDPERSON' => 'P00098', 'ISPENGAJUANFINAL' => 1,
        ]);

        $this->get("/pen/revisi/{$lain->id}")->assertNotFound();
        $this->getJson("/pen/penelitian/{$lain->id}/revisi/dokumen")->assertNotFound();
        $this->unggahDraft($lain)->assertAksiDitolak('Data tidak ditemukan.');
        $this->post("/pen/penelitian/{$lain->id}/revisi/final")->assertAksiDitolak('Data tidak ditemukan.');
    }

    public function test_show_exposes_revisi_status_so_frontend_can_distinguish_all_states(): void
    {
        // State: no verifikator yet
        $tanpaVerifikator = $this->usulan(withVerifikator: false);
        $this->get("/pen/revisi/{$tanpaVerifikator->id}")
            ->assertOk()
            ->assertProp('revisi.revisiStatus', null)
            ->assertProp('revisi.adaVerifikator', false);

        // State: verifikator assigned, waiting for upload
        $menungguUpload = $this->usulan();
        $this->get("/pen/revisi/{$menungguUpload->id}")
            ->assertOk()
            ->assertProp('revisi.revisiStatus', RevisiStatus::MENUNGGU_UPLOAD->value)
            ->assertProp('revisi.adaVerifikator', true)
            ->assertProp('revisi.isFinal', false)
            ->assertProp('revisi.catatanVerifikator', null);

        // State: submitted, waiting for verifikasi
        $menungguUpload->update(['ISDOKUMENPROPOSALREVISIFINAL' => 1]);
        $this->get("/pen/revisi/{$menungguUpload->id}")
            ->assertOk()
            ->assertProp('revisi.revisiStatus', RevisiStatus::MENUNGGU_VERIFIKASI->value)
            ->assertProp('revisi.isFinal', true)
            ->assertProp('revisi.catatanVerifikator', null);

        // State: verifikator rejected
        $ditolak = $this->usulan();
        $ditolak->update(['ISDOKUMENPROPOSALREVISIFINAL' => 1]);
        $ditolak->reviewers()->where('ISREVIEWERREVISI', 1)->update([
            'STATUSPENILAIANREVISI' => 'FINAL',
            'REVISI_HASILPENILAIAN' => 'BELUM',
            'REVISI_KOMENTAR' => 'Perlu diperbaiki lebih lanjut',
        ]);
        $this->get("/pen/revisi/{$ditolak->id}")
            ->assertOk()
            ->assertProp('revisi.revisiStatus', RevisiStatus::DITOLAK->value)
            ->assertProp('revisi.isFinal', true)
            ->assertProp('revisi.catatanVerifikator', 'Perlu diperbaiki lebih lanjut');

        // State: verifikator approved
        $disetujui = $this->usulan();
        $disetujui->update(['ISDOKUMENPROPOSALREVISIFINAL' => 1]);
        $disetujui->reviewers()->where('ISREVIEWERREVISI', 1)->update([
            'STATUSPENILAIANREVISI' => 'FINAL',
            'REVISI_HASILPENILAIAN' => 'SUDAH',
            'REVISI_KOMENTAR' => 'Revisi sudah sesuai',
        ]);
        $this->get("/pen/revisi/{$disetujui->id}")
            ->assertOk()
            ->assertProp('revisi.revisiStatus', RevisiStatus::DISETUJUI->value)
            ->assertProp('revisi.isFinal', true)
            ->assertProp('revisi.catatanVerifikator', 'Revisi sudah sesuai');
    }

    public function test_upload_is_blocked_when_verifikator_has_rejected_previous_submission(): void
    {
        $penelitian = $this->usulan();
        $penelitian->update(['ISDOKUMENPROPOSALREVISIFINAL' => 1]);
        $penelitian->reviewers()->where('ISREVIEWERREVISI', 1)->update([
            'STATUSPENILAIANREVISI' => 'FINAL',
            'REVISI_HASILPENILAIAN' => 'BELUM',
        ]);

        // Peneliti cannot re-upload until admin assigns a new verifikator
        $this->unggahDraft($penelitian)->assertAksiDitolak('Revisi sudah dikirim dan tidak dapat diubah lagi');
    }
}
