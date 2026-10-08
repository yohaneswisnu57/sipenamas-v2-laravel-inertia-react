<?php

namespace Tests\Feature\Dekan;

use App\Http\Resources\PenelitianResource;
use App\Models\Fakultas;
use App\Models\Penelitian;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        SpatiePermission::findOrCreate('approve penelitian', 'sanctum');
        SpatiePermission::findOrCreate('reject penelitian', 'sanctum');
    }

    private function dekanFor(string $kodeFakultas): User
    {
        $dekan = User::factory()->create();

        Fakultas::create([
            'KODEFAKULTAS' => $kodeFakultas,
            'NAMAFAKULTAS' => "Fakultas {$kodeFakultas}",
            'KDDEKAN' => $dekan->kodeperson,
        ]);

        $dekan->givePermissionTo('view penelitian', 'approve penelitian', 'reject penelitian');
        Sanctum::actingAs($dekan);

        return $dekan;
    }

    private function prodiIn(string $kodeFakultas, string $kodeProdi = 'TI'): Prodi
    {
        return Prodi::create([
            'KODEPRODI' => $kodeProdi,
            'NAMAPRODI' => 'Teknik Informatika',
            'KDFAKULTAS' => $kodeFakultas,
        ]);
    }

    private function submittedProposal(string $kodeProdi, array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Usulan Uji Approval Dekan',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'KDPRODI' => $kodeProdi,
            'ISPENGAJUANFINAL' => 1,
            'ISDOKUMENPROPOSALFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ], $overrides));
    }

    public function test_dekan_sees_only_submitted_proposals_from_their_own_faculty(): void
    {
        $this->dekanFor('FT');
        $prodiFt = $this->prodiIn('FT');
        $prodiFk = $this->prodiIn('FK', 'KG');

        $ownFacultyProposal = $this->submittedProposal($prodiFt->KODEPRODI);
        $this->submittedProposal($prodiFk->KODEPRODI, ['JUDULPENELITIAN' => 'Usulan Fakultas Lain']);

        $response = $this->getJson('/api/v1/dkn/proposal');

        $response->assertOk();
        $judulList = collect($response->json('data'))->pluck('judul')->all();

        $this->assertContains('Usulan Uji Approval Dekan', $judulList);
        $this->assertNotContains('Usulan Fakultas Lain', $judulList);
        $this->assertSame((string) $ownFacultyProposal->id, $response->json('data.0.id'));
    }

    public function test_dekan_only_sees_proposals_with_submitted_status(): void
    {
        $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT');

        $this->submittedProposal($prodi->KODEPRODI);
        $this->submittedProposal($prodi->KODEPRODI, [
            'JUDULPENELITIAN' => 'Usulan Draft',
            'ISPENGAJUANFINAL' => 0,
        ]);

        $response = $this->getJson('/api/v1/dkn/proposal')->assertOk();
        $judulList = collect($response->json('data'))->pluck('judul')->all();

        $this->assertContains('Usulan Uji Approval Dekan', $judulList);
        $this->assertNotContains('Usulan Draft', $judulList);
    }

    public function test_dekan_queue_waits_for_proposal_document_and_team_approval(): void
    {
        $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT');

        $this->submittedProposal($prodi->KODEPRODI, [
            'JUDULPENELITIAN' => 'Belum Upload Proposal',
            'ISDOKUMENPROPOSALFINAL' => 0,
        ]);
        $this->submittedProposal($prodi->KODEPRODI, ['JUDULPENELITIAN' => 'Anggota Belum Setuju'])
            ->tim()->create(['NIKNIDN' => 'P00001', 'PERAN' => 'ANGGOTA', 'ISAPPROVED' => 0]);
        $this->submittedProposal($prodi->KODEPRODI);

        $judulList = collect($this->getJson('/api/v1/dkn/proposal')->assertOk()->json('data'))->pluck('judul')->all();

        $this->assertSame(['Usulan Uji Approval Dekan'], $judulList);
    }

    public function test_dekan_can_approve_a_proposal_in_their_faculty(): void
    {
        $dekan = $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT');
        $proposal = $this->submittedProposal($prodi->KODEPRODI);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/approve", [
            'catatan' => 'Sesuai dengan RIP Fakultas, layak dilanjutkan ke LPPM',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian', [
            'id' => $proposal->id,
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1,
            // Requirement LPPM: begitu Dekan ACC, LPPM otomatis ikut ACC -
            // tidak perlu approval terpisah dari ketua LPPM.
            'ISAPPROVEDBYLPPM' => 1,
            'APPROVALPERMOHONAN_KDDEKAN' => $dekan->kodeperson,
        ]);
        $this->assertNotNull($proposal->fresh()->APPROVALPERMOHONAN_TIMESTAMP);

        $this->assertDatabaseHas('penelitian', [
            'id' => $proposal->id,
            'APPROVALPERMOHONAN_CATATANDEKAN' => 'Sesuai dengan RIP Fakultas, layak dilanjutkan ke LPPM',
        ]);
    }

    public function test_catatan_dekan_is_exposed_on_the_proposal_resource_after_approving(): void
    {
        $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT');
        $proposal = $this->submittedProposal($prodi->KODEPRODI);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/approve", [
            'catatan' => 'Layak dilanjutkan',
        ])->assertOk();

        $resource = (new PenelitianResource(
            Penelitian::with(PenelitianResource::EAGER_RELATIONS)->find($proposal->id)
        ))->resolve();

        $this->assertSame('SETUJU', $resource['catatanDekan']['keputusan']);
        $this->assertSame('Layak dilanjutkan', $resource['catatanDekan']['catatan']);
    }

    public function test_approve_requires_a_note(): void
    {
        $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT');
        $proposal = $this->submittedProposal($prodi->KODEPRODI);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/approve", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['catatan']);
    }

    public function test_dekan_can_reject_a_proposal_with_a_note(): void
    {
        $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT');
        $proposal = $this->submittedProposal($prodi->KODEPRODI);
        $proposal->update([
            'LBRPENGESAHANPROPOSAL_NAMAFILE' => 'lembar.docx',
            'LBRPENGESAHANPROPOSAL_QRCODE' => 'qr',
            'LBRPENGESAHANPROPOSAL_ISFINAL' => 1,
        ]);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/reject", [
            'catatan' => 'Belum sesuai RIP Fakultas',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian', [
            'id' => $proposal->id,
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 0,
            '_MSG_PENOLAKANDEKAN' => 'Belum sesuai RIP Fakultas',
            'ISAPPROVEDBYLPPM' => 0,
            'LBRPENGESAHANPROPOSAL_NAMAFILE' => null,
            'LBRPENGESAHANPROPOSAL_QRCODE' => null,
            'LBRPENGESAHANPROPOSAL_ISFINAL' => 0,
        ]);
    }

    public function test_reject_requires_a_note(): void
    {
        $this->dekanFor('FT');
        $prodi = $this->prodiIn('FT');
        $proposal = $this->submittedProposal($prodi->KODEPRODI);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['catatan']);
    }

    public function test_approved_proposal_leaves_the_queue_and_its_decision_is_locked(): void
    {
        $this->dekanFor('FT');
        $proposal = $this->submittedProposal($this->prodiIn('FT')->KODEPRODI);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/approve", ['catatan' => 'Sesuai'])->assertOk();

        $this->assertNotContains((string) $proposal->id, collect($this->getJson('/api/v1/dkn/proposal')->json('data'))->pluck('id')->map(fn ($id) => (string) $id)->all());
        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/reject", ['catatan' => 'Ubah pikiran'])->assertStatus(422);
        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/approve", ['catatan' => 'Lagi'])->assertStatus(422);

        $this->assertDatabaseHas('penelitian', ['id' => $proposal->id, 'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1, 'APPROVALPERMOHONAN_CATATANDEKAN' => 'Sesuai', '_MSG_PENOLAKANDEKAN' => null]);
    }

    public function test_dekan_can_view_the_proposal_pdf_before_deciding(): void
    {
        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->put('proposal/proposal_x.pdf', '%PDF-1.4 isi');
        $this->dekanFor('FT');
        $proposal = $this->submittedProposal($this->prodiIn('FT')->KODEPRODI, ['FILE_DOKUMENPROPOSAL_FINAL' => 'proposal_x.pdf']);

        $response = $this->get("/api/v1/dkn/proposal/{$proposal->id}/dokumen-proposal")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_dekan_cannot_view_the_proposal_pdf_of_another_faculty(): void
    {
        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->put('proposal/proposal_x.pdf', '%PDF-1.4 isi');
        $this->dekanFor('FT');
        $this->prodiIn('FE', 'MN');
        Fakultas::create(['KODEFAKULTAS' => 'FE', 'NAMAFAKULTAS' => 'Fakultas FE', 'KDDEKAN' => 'LAIN01']);
        $proposal = $this->submittedProposal('MN', ['FILE_DOKUMENPROPOSAL_FINAL' => 'proposal_x.pdf']);

        $this->getJson("/api/v1/dkn/proposal/{$proposal->id}/dokumen-proposal")->assertNotFound();
    }

    public function test_dekan_cannot_approve_a_proposal_from_another_faculty(): void
    {
        $this->dekanFor('FT');
        $prodiLain = $this->prodiIn('FK', 'KG');
        $proposal = $this->submittedProposal($prodiLain->KODEPRODI);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/approve", [
            'catatan' => 'Coba approve proposal fakultas lain',
        ])->assertStatus(404);
    }

    public function test_user_without_approve_permission_is_forbidden(): void
    {
        $dekan = $this->dekanFor('FT');
        $dekan->revokePermissionTo('approve penelitian');
        $prodi = $this->prodiIn('FT');
        $proposal = $this->submittedProposal($prodi->KODEPRODI);

        $this->postJson("/api/v1/dkn/proposal/{$proposal->id}/approve")->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/dkn/proposal')->assertStatus(401);
    }
}
