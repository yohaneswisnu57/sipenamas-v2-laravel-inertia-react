<?php

namespace Tests\Feature\Reviewer;

use App\Models\Penelitian;
use App\Models\PenelitianReviewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class PenugasanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        SpatiePermission::findOrCreate('confirm kesediaan penugasan', 'sanctum');
        SpatiePermission::findOrCreate('submit penilaian penugasan', 'sanctum');
    }

    private function actingReviewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan');
        Sanctum::actingAs($user);

        return $user;
    }

    private function proposalAssignedTo(string $reviewerKode): Penelitian
    {
        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Uji Penugasan',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
            'STATUSPENUNJUKANREVIEWER' => 'FINAL',
        ]);

        $penelitian->reviewers()->create(['NIK' => $reviewerKode]);

        return $penelitian;
    }

    public function test_reviewer_sees_only_proposals_assigned_to_them(): void
    {
        $user = $this->actingReviewer();
        $this->proposalAssignedTo($user->kodeperson);

        Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Reviewer Lain',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00098',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ])->reviewers()->create(['NIK' => 'REVIEWER-LAIN']);

        $response = $this->getJson('/api/v1/rev/penugasan')->assertOk();
        $judulList = collect($response->json('data'))->pluck('judul')->all();

        $this->assertContains('Usulan Uji Penugasan', $judulList);
        $this->assertNotContains('Usulan Reviewer Lain', $judulList);
    }

    public function test_reviewer_can_confirm_kesediaan(): void
    {
        $user = $this->actingReviewer();
        $proposal = $this->proposalAssignedTo($user->kodeperson);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/kesediaan", ['bersedia' => true])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian_reviewer', [
            'IDPARENT' => $proposal->id, 'NIK' => $user->kodeperson, 'ISAPPROVED' => 1,
        ]);
    }

    public function test_kesediaan_is_locked_once_confirmed_and_shown_in_the_list(): void
    {
        $user = $this->actingReviewer();
        $proposal = $this->proposalAssignedTo($user->kodeperson);

        $this->assertSame('MENUNGGU', $this->getJson('/api/v1/rev/penugasan')->json('data.0.kesediaanSaya'));

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/kesediaan", ['bersedia' => true])->assertOk();

        $this->assertSame('BERSEDIA', $this->getJson('/api/v1/rev/penugasan')->json('data.0.kesediaanSaya'));
        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/kesediaan", ['bersedia' => false, 'alasan' => 'Berubah pikiran'])
            ->assertStatus(422);
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => $user->kodeperson, 'ISAPPROVED' => 1]);
    }

    public function test_reviewer_can_decline_kesediaan(): void
    {
        $user = $this->actingReviewer();
        $proposal = $this->proposalAssignedTo($user->kodeperson);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/kesediaan", ['bersedia' => false, 'alasan' => 'Konflik kepentingan'])
            ->assertOk()->assertJsonPath('success', true);

        $penugasan = PenelitianReviewer::where('IDPARENT', $proposal->id)->where('NIK', $user->kodeperson)->first();
        $this->assertFalse($penugasan->ISAPPROVED);
        $this->assertSame('Konflik kepentingan', $penugasan->KOMENTAR);
        $this->assertSame('MENOLAK', $penugasan->statusKesediaan());
    }

    public function test_legacy_reviewer_that_already_scored_counts_as_bersedia(): void
    {
        $legacy = new PenelitianReviewer(['ISAPPROVED' => 0, 'TSAPPROVED' => now(), 'STATUSPENILAIAN' => 'FINAL']);

        $this->assertSame('BERSEDIA', $legacy->statusKesediaan());
        $this->assertSame('MENUNGGU', (new PenelitianReviewer(['ISAPPROVED' => 0]))->statusKesediaan());
    }

    public function test_submit_penilaian_fails_validation_without_required_scores(): void
    {
        $user = $this->actingReviewer();
        $proposal = $this->proposalAssignedTo($user->kodeperson);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/penilaian", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['skor', 'catatan', 'rekomendasiStatus']);
    }

    public function test_reviewer_cannot_act_on_a_proposal_not_assigned_to_them(): void
    {
        $this->actingReviewer();
        $proposal = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Reviewer Lain',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00098',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ]);
        $proposal->reviewers()->create(['NIK' => 'REVIEWER-LAIN']);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/kesediaan", ['bersedia' => true])
            ->assertStatus(404);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/rev/penugasan')->assertStatus(401);
    }

    /**
     * Requirement LPPM: selama plotting masih DRAFT (admin belum
     * finalize), reviewer belum diberi tahu - proposal itu TIDAK boleh
     * tampil di antrian penugasannya sama sekali, walau baris
     * penelitian_reviewer sudah ada.
     */
    public function test_reviewer_does_not_see_a_draft_plotted_proposal(): void
    {
        $user = $this->actingReviewer();
        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Masih Draft Plotting',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
            'STATUSPENUNJUKANREVIEWER' => 'DRAFT',
        ]);
        $penelitian->reviewers()->create(['NIK' => $user->kodeperson]);

        $response = $this->getJson('/api/v1/rev/penugasan')->assertOk();
        $this->assertEmpty($response->json('data'));

        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}")->assertStatus(404);

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/kesediaan", ['bersedia' => true])
            ->assertStatus(404);
    }

    public function test_reviewer_can_view_the_proposal_pdf_they_are_assigned_to_score(): void
    {
        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->put('proposal/proposal_init.pdf', '%PDF-1.4 isi');
        $user = $this->actingReviewer();
        $proposal = $this->proposalAssignedTo($user->kodeperson);
        $proposal->update(['FILE_DOKUMENPROPOSAL_INIT' => 'proposal_init.pdf']);

        $response = $this->get("/api/v1/rev/penugasan/{$proposal->id}/dokumen-proposal")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_reviewer_cannot_view_the_proposal_pdf_of_a_proposal_not_assigned_to_them(): void
    {
        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->put('proposal/proposal_init.pdf', '%PDF-1.4 isi');
        $this->actingReviewer();
        $proposal = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Reviewer Lain',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00098',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
            'STATUSPENUNJUKANREVIEWER' => 'FINAL',
            'FILE_DOKUMENPROPOSAL_INIT' => 'proposal_init.pdf',
        ]);
        $proposal->reviewers()->create(['NIK' => 'REVIEWER-LAIN']);

        $this->getJson("/api/v1/rev/penugasan/{$proposal->id}/dokumen-proposal")->assertStatus(404);
    }

    public function test_viewing_the_proposal_pdf_fails_when_not_uploaded_yet(): void
    {
        Storage::fake('legacy_res');
        $user = $this->actingReviewer();
        $proposal = $this->proposalAssignedTo($user->kodeperson);

        $this->getJson("/api/v1/rev/penugasan/{$proposal->id}/dokumen-proposal")->assertStatus(404);
    }

    public function test_reviewer_can_view_the_revised_proposal_pdf(): void
    {
        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->put('proposal/proposal_rev.pdf', '%PDF-1.4 revisi');
        $user = $this->actingReviewer();
        $proposal = $this->proposalAssignedTo($user->kodeperson);
        $proposal->update(['FILE_DOKUMENPROPOSAL_REV' => 'proposal_rev.pdf']);

        $response = $this->get("/api/v1/rev/penugasan/{$proposal->id}/dokumen-proposal-revisi")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
