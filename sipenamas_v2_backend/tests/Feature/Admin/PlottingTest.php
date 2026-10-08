<?php

namespace Tests\Feature\Admin;

use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Models\PenelitianReviewer;
use App\Models\Periode;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class PlottingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view plotting', 'sanctum');
        SpatiePermission::findOrCreate('assign reviewer plotting', 'sanctum');
        SpatiePermission::findOrCreate('finalize reviewer plotting', 'sanctum');
        SpatiePermission::findOrCreate('add reviewer plotting', 'sanctum');
        SpatiePermission::findOrCreate('assign revisi verifikator plotting', 'sanctum');
    }

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view plotting', 'assign reviewer plotting', 'finalize reviewer plotting', 'add reviewer plotting', 'assign revisi verifikator plotting');
        Sanctum::actingAs($user);

        return $user;
    }

    private function proposal(array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Usulan Uji Plotting',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'ISPENGAJUANFINAL' => 1,
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1,
            'TAHUNUSULAN' => 2026,
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'JENIS_PA' => 'PENELITIAN',
            'STATUSPENUNJUKANREVIEWER' => 'DRAFT',
        ], $overrides));
    }

    private function reviewer(string $kode = 'REV001'): Person
    {
        return Person::create(['KODEPERSON' => $kode, 'NAMALENGKAP' => "Reviewer {$kode}"]);
    }

    public function test_admin_sees_list_of_proposals_for_plotting(): void
    {
        $this->actingAdmin();
        $this->proposal();

        $response = $this->getJson('/api/v1/adm/plotting')->assertOk();

        $this->assertContains('Usulan Uji Plotting', collect($response->json('data'))->pluck('judul')->all());
    }

    public function test_list_defaults_to_active_periode_and_penelitian_only(): void
    {
        Periode::create(['KODEPERIODE' => '2025-1', 'TAHUN' => 2025, 'ISAKTIF' => 0]);
        Periode::create(['KODEPERIODE' => '2026-1', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        $this->actingAdmin();
        $this->proposal(['JUDULPENELITIAN' => 'Aktif']);
        $this->proposal(['JUDULPENELITIAN' => 'Lama', 'PERIODEKEGIATAN_TAHUN' => 2025]);
        $this->proposal(['JUDULPENELITIAN' => 'Abdimas', 'JENIS_PA' => 'ABDIMAS']);

        $judul = fn (string $url) => collect($this->getJson($url)->assertOk()->json('data'))->pluck('judul')->sort()->values()->all();

        $this->assertSame(['Aktif'], $judul('/api/v1/adm/plotting'));
        $this->assertSame(['Lama'], $judul('/api/v1/adm/plotting?kdperiode=2025-1'));
        $this->assertSame(['Aktif', 'Lama'], $judul('/api/v1/adm/plotting?kdperiode=ALL'));
    }

    /**
     * Frontend butuh id baris penelitian_reviewer (bukan cuma NIK) supaya
     * bisa memanggil endpoint replace yang di-keyed oleh baris tersebut.
     */
    public function test_reviewer_summary_exposes_the_penelitian_reviewer_row_id(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON, 'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk();

        $reviewerRow = PenelitianReviewer::where('IDPARENT', $proposal->id)->where('NIK', 'REV001')->firstOrFail();

        $response = $this->getJson('/api/v1/adm/plotting')->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', (string) $proposal->id);

        $this->assertSame((string) $reviewerRow->id, (string) $data['reviewer1']['penugasanId']);
    }

    public function test_reviewer_summary_exposes_whether_revisi_verification_was_already_completed(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV009');
        $proposal->reviewers()->create(['NIK' => 'REV009', 'STATUSPENILAIANREVISI' => 'FINAL', 'REVISI_HASILPENILAIAN' => 'BELUM']);

        $response = $this->getJson('/api/v1/adm/plotting')->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', (string) $proposal->id);

        $this->assertTrue($data['reviewer1']['sudahVerifikasiRevisi']);
    }

    public function test_admin_can_assign_two_reviewers_to_a_proposal(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON,
            'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV001']);
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV002']);
        // Requirement LPPM: assign baru menghasilkan status DRAFT - reviewer
        // belum diberi tahu/tidak bisa konfirmasi sampai admin finalize.
        $this->assertDatabaseHas('penelitian', ['id' => $proposal->id, 'STATUSPENUNJUKANREVIEWER' => 'DRAFT']);
    }

    public function test_admin_can_finalize_plotting_after_assigning_two_reviewers(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON, 'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/finalize")
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian', ['id' => $proposal->id, 'STATUSPENUNJUKANREVIEWER' => 'FINAL']);
    }

    public function test_finalize_fails_when_fewer_than_two_reviewers_assigned(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/finalize")->assertStatus(422);

        $this->assertDatabaseHas('penelitian', ['id' => $proposal->id, 'STATUSPENUNJUKANREVIEWER' => 'DRAFT']);
    }

    public function test_user_without_finalize_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view plotting', 'assign reviewer plotting');
        Sanctum::actingAs($user);
        $proposal = $this->proposal();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/finalize")->assertStatus(403);
    }

    /**
     * Requirement LPPM: kalau salah satu dari reviewer 1/2 menolak, admin
     * TIDAK mengganti baris yang menolak - reviewer ke-3 DITAMBAHKAN
     * berdampingan, keduanya (yang menolak dan yang ditambahkan) tetap
     * ada di database (lihat App\Services\Proposal\ReviewerAssignmentService).
     */
    public function test_admin_can_add_a_third_reviewer_without_touching_the_other_two(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');
        $r3 = $this->reviewer('REV003');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON, 'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk();
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/finalize")->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r3->KODEPERSON,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV001']);
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV002']);
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV003']);
        $this->assertEquals(3, PenelitianReviewer::where('IDPARENT', $proposal->id)->count());
        $this->assertDatabaseHas('penelitian', ['id' => $proposal->id, 'STATUSPENUNJUKANREVIEWER' => 'FINAL']);
    }

    public function test_adding_a_reviewer_already_active_on_the_proposal_fails_validation(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON, 'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => 'REV002',
        ])->assertStatus(422)->assertJsonValidationErrors(['reviewerBaruId']);
    }

    public function test_admin_can_add_reviewer_pembanding_when_conflict_detected(): void
    {
        // Rev1 TOLAK, Rev2 LANJUT → ISBUTUHREVIEWERKETIGA=1 → admin bisa tambah pembanding
        $this->actingAdmin();
        $proposal = $this->proposal(['ISBUTUHREVIEWERKETIGA' => 1, 'STATUSPENUNJUKANREVIEWER' => 'FINAL']);
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');
        $r3 = $this->reviewer('REV003');

        $proposal->reviewers()->create(['NIK' => $r1->KODEPERSON]);
        $proposal->reviewers()->create(['NIK' => $r2->KODEPERSON]);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r3->KODEPERSON,
            'isPembanding' => true,
        ])->assertOk()->assertJsonPath('success', true);

        // REV003 harus ISREVIEWERPEMBANDING=1; REV001/REV002 tetap ada tanpa flag pembanding
        $this->assertDatabaseHas('penelitian_reviewer', [
            'IDPARENT' => $proposal->id, 'NIK' => 'REV003', 'ISREVIEWERPEMBANDING' => 1,
        ]);
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV001']);
        $this->assertDatabaseMissing('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV001', 'ISREVIEWERPEMBANDING' => 1]);
    }

    public function test_reviewer_pembanding_cannot_be_added_without_conflict(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal(['ISBUTUHREVIEWERKETIGA' => 0, 'STATUSPENUNJUKANREVIEWER' => 'FINAL']);
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');
        $r3 = $this->reviewer('REV003');
        $proposal->reviewers()->create(['NIK' => $r1->KODEPERSON]);
        $proposal->reviewers()->create(['NIK' => $r2->KODEPERSON]);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r3->KODEPERSON,
            'isPembanding' => true,
        ])->assertStatus(422);
    }

    public function test_second_reviewer_pembanding_is_rejected(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal(['ISBUTUHREVIEWERKETIGA' => 1, 'STATUSPENUNJUKANREVIEWER' => 'FINAL']);
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');
        $r3 = $this->reviewer('REV003');
        $r4 = $this->reviewer('REV004');
        $proposal->reviewers()->create(['NIK' => $r1->KODEPERSON]);
        $proposal->reviewers()->create(['NIK' => $r2->KODEPERSON]);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r3->KODEPERSON, 'isPembanding' => true,
        ])->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r4->KODEPERSON, 'isPembanding' => true,
        ])->assertStatus(422);
    }

    public function test_reviewer_pembanding_is_shown_separately_in_resource(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal(['ISBUTUHREVIEWERKETIGA' => 1, 'STATUSPENUNJUKANREVIEWER' => 'FINAL']);
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');
        $r3 = $this->reviewer('REV003');
        $proposal->reviewers()->create(['NIK' => $r1->KODEPERSON]);
        $proposal->reviewers()->create(['NIK' => $r2->KODEPERSON]);
        $proposal->reviewers()->create(['NIK' => $r3->KODEPERSON, 'ISREVIEWERPEMBANDING' => 1]);

        $response = $this->getJson('/api/v1/adm/plotting')->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', (string) $proposal->id);

        $this->assertSame('REV001', $data['reviewer1']['id']);
        $this->assertSame('REV002', $data['reviewer2']['id']);
        $this->assertNull($data['reviewer3']);
        $this->assertSame('REV003', $data['reviewerPembanding']['id']);
        $this->assertTrue($data['reviewerPembanding']['isPembanding']);
    }

    public function test_adding_a_fourth_reviewer_fails(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');
        $r3 = $this->reviewer('REV003');
        $r4 = $this->reviewer('REV004');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON, 'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk();
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r3->KODEPERSON,
        ])->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r4->KODEPERSON,
        ])->assertStatus(422);

        $this->assertEquals(3, PenelitianReviewer::where('IDPARENT', $proposal->id)->count());
    }

    public function test_adding_the_proposal_ketua_as_a_third_reviewer_fails_validation_conflict_of_interest(): void
    {
        $this->actingAdmin();
        $ketua = $this->reviewer('P00099');
        $proposal = $this->proposal(['PERMOHONANDIBUAT_KDPERSON' => $ketua->KODEPERSON]);
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON, 'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $ketua->KODEPERSON,
        ])->assertStatus(422)->assertJsonValidationErrors(['reviewerBaruId']);
    }

    public function test_admin_can_assign_revisi_verifikator(): void
    {
        // REV001/REV002 = reviewer asli proposal; REV009 = orang baru sebagai verifikator
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV001');
        $this->reviewer('REV009');
        $proposal->reviewers()->create(['NIK' => 'REV001']);
        $proposal->reviewers()->create(['NIK' => 'REV002']);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", [
            'reviewerId' => 'REV009',
        ])->assertOk()->assertJsonPath('success', true);

        // Baris baru dibuat untuk verifikator, reviewer asli tidak diubah
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV009', 'ISREVIEWERREVISI' => 1]);
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV001', 'ISREVIEWERREVISI' => 0]);
    }

    public function test_reviewer_asli_tidak_bisa_jadi_verifikator_revisi(): void
    {
        // Reviewer asli (baris STATUSPENILAIANREVISI null) tidak boleh jadi verifikator
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV001');
        $proposal->reviewers()->create(['NIK' => 'REV001']);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV001'])
            ->assertUnprocessable();
    }

    public function test_reassigning_revisi_verifikator_while_still_menunggu_upload_replaces_active_verifikator(): void
    {
        // REV001/REV002 = reviewer asli; REV009/REV010 = verifikator kandidat
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV009');
        $this->reviewer('REV010');
        $proposal->reviewers()->create(['NIK' => 'REV001']);
        $proposal->reviewers()->create(['NIK' => 'REV002']);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV009'])->assertOk();
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV010'])->assertOk();

        // Hanya satu verifikator aktif (ISREVIEWERREVISI=1 yang non-FINAL)
        $this->assertEquals(1, $proposal->reviewers()->where('ISREVIEWERREVISI', 1)->where('STATUSPENILAIANREVISI', '!=', 'FINAL')->count());
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV010', 'ISREVIEWERREVISI' => 1]);
    }

    public function test_a_verifikator_who_already_completed_a_revisi_verification_cannot_be_assigned_again(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV009');
        $this->reviewer('REV010');
        $proposal->reviewers()->create(['NIK' => 'REV001']);
        $proposal->reviewers()->create(['NIK' => 'REV002']);

        // REV009 ditunjuk, menolak revisi -> siklus selesai (FINAL).
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV009'])->assertOk();
        $proposal->reviewers()->where('NIK', 'REV009')->where('ISREVIEWERREVISI', 1)->update(['STATUSPENILAIANREVISI' => 'FINAL', 'REVISI_HASILPENILAIAN' => 'BELUM']);

        // Siklus baru: REV009 tidak boleh ditunjuk lagi.
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV009'])
            ->assertStatus(422);

        // REV010 yang belum pernah menyelesaikan verifikasi tetap boleh.
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV010'])->assertOk();
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV010', 'ISREVIEWERREVISI' => 1]);
    }

    public function test_swapping_verifikator_before_any_verification_completes_does_not_block_either_reviewer(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV009');
        $this->reviewer('REV010');
        $proposal->reviewers()->create(['NIK' => 'REV001']);
        $proposal->reviewers()->create(['NIK' => 'REV002']);

        // Admin salah pilih REV009, ganti ke REV010, lalu kembali ke REV009 —
        // belum ada yang finalkan verifikasi, jadi REV009 masih bisa dipilih lagi.
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV009'])->assertOk();
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV010'])->assertOk();
        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV009'])->assertOk();

        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV009', 'ISREVIEWERREVISI' => 1, 'STATUSPENILAIANREVISI' => 'DRAFT']);
    }

    public function test_revisi_verifikator_is_exposed_on_the_proposal_resource(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $verifikator = $this->reviewer('REV009');
        $proposal->reviewers()->create(['NIK' => 'REV001']);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", [
            'reviewerId' => $verifikator->KODEPERSON,
        ])->assertOk();

        $resource = (new PenelitianResource(
            Penelitian::with(PenelitianResource::EAGER_RELATIONS)->find($proposal->id)
        ))->resolve();

        $this->assertSame('REV009', $resource['revisiVerifikator']['nik']);
        $this->assertSame('Reviewer REV009', $resource['revisiVerifikator']['nama']);
        $this->assertSame('MENUNGGU_UPLOAD', $resource['revisiVerifikator']['status']);
    }

    public function test_user_without_assign_revisi_verifikator_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view plotting');
        Sanctum::actingAs($user);
        $proposal = $this->proposal();
        $this->reviewer('REV009');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/revisi-verifikator", ['reviewerId' => 'REV009'])
            ->assertStatus(403);
    }

    public function test_user_without_add_reviewer_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view plotting', 'assign reviewer plotting');
        Sanctum::actingAs($user);
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');
        $r3 = $this->reviewer('REV003');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON, 'reviewer2Id' => $r2->KODEPERSON,
        ])->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer/tambah", [
            'reviewerBaruId' => $r3->KODEPERSON,
        ])->assertStatus(403);
    }

    public function test_assigning_the_same_reviewer_twice_fails_validation(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON,
            'reviewer2Id' => $r1->KODEPERSON,
        ])->assertStatus(422)->assertJsonValidationErrors(['reviewer1Id']);
    }

    public function test_assigning_the_proposal_ketua_as_reviewer_fails_validation_conflict_of_interest(): void
    {
        $this->actingAdmin();
        $ketua = $this->reviewer('P00099');
        $proposal = $this->proposal(['PERMOHONANDIBUAT_KDPERSON' => $ketua->KODEPERSON]);
        $r2 = $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $ketua->KODEPERSON,
            'reviewer2Id' => $r2->KODEPERSON,
        ])->assertStatus(422)->assertJsonValidationErrors(['reviewer1Id']);
    }

    public function test_reassigning_replaces_previous_reviewers(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV001');
        $this->reviewer('REV002');
        $this->reviewer('REV003');
        $this->reviewer('REV004');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => 'REV001', 'reviewer2Id' => 'REV002',
        ])->assertOk();

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => 'REV003', 'reviewer2Id' => 'REV004',
        ])->assertOk();

        $this->assertDatabaseMissing('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV001']);
        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV003']);
        $this->assertEquals(2, PenelitianReviewer::where('IDPARENT', $proposal->id)->count());
    }

    public function test_reassigning_keeps_the_row_of_a_reviewer_that_stays(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal();
        $this->reviewer('REV001');
        $this->reviewer('REV002');
        $this->reviewer('REV003');
        $tetap = $proposal->reviewers()->create(['NIK' => 'REV001', 'TOTALSKOR' => 500]);
        $proposal->reviewers()->create(['NIK' => 'REV002']);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => 'REV001', 'reviewer2Id' => 'REV003',
        ])->assertOk();

        $this->assertDatabaseHas('penelitian_reviewer', ['id' => $tetap->id, 'NIK' => 'REV001', 'TOTALSKOR' => 500]);
        $this->assertDatabaseMissing('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV002']);
    }

    public function test_reviewer_cannot_be_assigned_before_dekan_approval(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal(['APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 0]);
        $this->reviewer('REV001');
        $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => 'REV001', 'reviewer2Id' => 'REV002',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('penelitian_reviewer', ['IDPARENT' => $proposal->id]);
    }

    public function test_reviewers_cannot_be_replaced_after_plotting_is_final(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal(['STATUSPENUNJUKANREVIEWER' => 'FINAL']);
        $this->reviewer('REV001');
        $this->reviewer('REV002');
        $this->reviewer('REV003');
        $proposal->reviewers()->create(['NIK' => 'REV001']);
        $proposal->reviewers()->create(['NIK' => 'REV002']);

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => 'REV001', 'reviewer2Id' => 'REV003',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV002']);
        $this->assertDatabaseMissing('penelitian_reviewer', ['IDPARENT' => $proposal->id, 'NIK' => 'REV003']);
        $this->assertDatabaseHas('penelitian', ['id' => $proposal->id, 'STATUSPENUNJUKANREVIEWER' => 'FINAL']);
    }

    /**
     * ISAPPROVEDBYLPPM bernilai 0 pada semua baris legacy walau Dekan sudah
     * menyetujui - gerbang aksi plotting harus membaca kolom Dekan.
     */
    public function test_legacy_proposal_approved_by_dekan_is_exposed_as_approved(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal(['ISAPPROVEDBYLPPM' => 0, 'STATUSFINALAPPROVAL' => 'LOLOS']);

        $data = collect($this->getJson('/api/v1/adm/plotting')->assertOk()->json('data'))
            ->firstWhere('id', (string) $proposal->id);

        $this->assertTrue($data['isApprovedByDekan']);
    }

    public function test_reviewer_options_for_a_proposal_exclude_its_team_and_other_jenis_reviewers(): void
    {
        $this->actingAdmin();
        $proposal = $this->proposal(['PERMOHONANDIBUAT_KDPERSON' => 'KETUA01']);
        $proposal->tim()->create(['NIKNIDN' => 'KETUA02', 'PERAN' => 'KETUA']);
        $proposal->tim()->create(['NIKNIDN' => 'ANGGOTA01', 'PERAN' => 'ANGGOTA', 'ISAPPROVED' => 0]);
        foreach (['KETUA01', 'KETUA02', 'ANGGOTA01', 'REV001'] as $kode) {
            Person::create(['KODEPERSON' => $kode, 'NAMALENGKAP' => "Dosen {$kode}", 'ISREVIEWERPENELITIAN' => 1]);
        }
        Person::create(['KODEPERSON' => 'ABD001', 'NAMALENGKAP' => 'Reviewer Abdimas', 'ISREVIEWERABDIMAS' => 1]);

        $ids = collect($this->getJson("/api/v1/reviewer?idpen={$proposal->id}")->assertOk()->json('data'))
            ->pluck('id')->all();

        $this->assertSame(['REV001'], $ids);
    }

    public function test_user_without_assign_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view plotting');
        Sanctum::actingAs($user);

        $proposal = $this->proposal();
        $r1 = $this->reviewer('REV001');
        $r2 = $this->reviewer('REV002');

        $this->postJson("/api/v1/adm/plotting/{$proposal->id}/reviewer", [
            'reviewer1Id' => $r1->KODEPERSON,
            'reviewer2Id' => $r2->KODEPERSON,
        ])->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/adm/plotting')->assertStatus(401);
    }
}
