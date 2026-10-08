<?php

namespace Tests\Feature\Reviewer;

use App\Models\Penelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class VerifikasiRevisiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view revisi queue penugasan', 'sanctum');
        SpatiePermission::findOrCreate('verifikasi revisi penugasan', 'sanctum');
    }

    private function actingVerifikator(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view revisi queue penugasan', 'verifikasi revisi penugasan');
        Sanctum::actingAs($user);

        return $user;
    }

    private function proposalMenungguVerifikasi(string $verifikatorNik): Penelitian
    {
        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Menunggu Verifikasi Revisi',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
            'STATUSPENILAIANREVIEWER' => 'PERBAIKAN',
        ]);

        $penelitian->update(['ISDOKUMENPROPOSALREVISIFINAL' => 1]);
        $penelitian->reviewers()->create(['NIK' => $verifikatorNik, 'ISREVIEWERREVISI' => 1, 'STATUSPENILAIANREVISI' => 'DRAFT']);

        return $penelitian;
    }

    public function test_verifikator_sees_the_proposal_in_their_revisi_queue(): void
    {
        $user = $this->actingVerifikator();
        $proposal = $this->proposalMenungguVerifikasi($user->kodeperson);

        $response = $this->getJson('/api/v1/rev/penugasan/revisi')->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', (string) $proposal->id);

        $this->assertSame('Usulan Menunggu Verifikasi Revisi', $data['judul']);
    }

    public function test_revisi_queue_excludes_proposals_verified_by_someone_else(): void
    {
        $this->actingVerifikator();
        $this->proposalMenungguVerifikasi('REVIEWER-LAIN');

        $response = $this->getJson('/api/v1/rev/penugasan/revisi')->assertOk();

        $this->assertEmpty($response->json('data'));
    }

    public function test_assigned_verifikator_can_approve_the_revision(): void
    {
        $user = $this->actingVerifikator();
        $proposal = $this->proposalMenungguVerifikasi($user->kodeperson);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/verifikasi-revisi", [
            'status' => 'DISETUJUI',
            'catatan' => 'Revisi sudah sesuai catatan sebelumnya',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian_reviewer', [
            'IDPARENT' => $proposal->id,
            'REVISI_HASILPENILAIAN' => 'SUDAH',
            'REVISI_KOMENTAR' => 'Revisi sudah sesuai catatan sebelumnya',
            'STATUSPENILAIANREVISI' => 'FINAL',
        ]);

        // Nilai ini sudah punya makna "revisi selesai" di data produksi
        // nyata - lihat catatan di App\Domain\Proposal\ProposalStatusResolver.
        $this->assertDatabaseHas('penelitian', [
            'id' => $proposal->id,
            'STATUSPENILAIANREVIEWER' => 'LANJUT (SUDAH PERBAIKAN)',
        ]);
    }

    public function test_assigned_verifikator_can_reject_the_revision(): void
    {
        $user = $this->actingVerifikator();
        $proposal = $this->proposalMenungguVerifikasi($user->kodeperson);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/verifikasi-revisi", [
            'status' => 'DITOLAK',
            'catatan' => 'Masih belum sesuai, tolong perbaiki metodologi lagi',
        ])->assertOk();

        $this->assertDatabaseHas('penelitian_reviewer', [
            'IDPARENT' => $proposal->id,
            'REVISI_HASILPENILAIAN' => 'BELUM',
            'STATUSPENILAIANREVISI' => 'FINAL',
        ]);

        $this->assertDatabaseHas('penelitian', [
            'id' => $proposal->id,
            'STATUSPENILAIANREVIEWER' => 'TOLAK (BELUM PERBAIKAN)',
        ]);
    }

    public function test_verifikasi_requires_status_and_catatan(): void
    {
        $user = $this->actingVerifikator();
        $proposal = $this->proposalMenungguVerifikasi($user->kodeperson);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/verifikasi-revisi", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'catatan']);
    }

    public function test_a_reviewer_who_is_not_the_assigned_verifikator_gets_404(): void
    {
        $this->actingVerifikator();
        $proposal = $this->proposalMenungguVerifikasi('REVIEWER-LAIN');

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/verifikasi-revisi", [
            'status' => 'DISETUJUI',
            'catatan' => 'Coba verifikasi punya orang lain',
        ])->assertStatus(404);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $proposal = $this->proposalMenungguVerifikasi($user->kodeperson);

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/verifikasi-revisi", [
            'status' => 'DISETUJUI', 'catatan' => 'x',
        ])->assertStatus(403);
    }

    /**
     * "Lihat antrian" dan "verifikasi" adalah permission granular terpisah
     * - role bisa diberi salah satu tanpa yang lain (mis. observer yang
     * cuma boleh lihat, tanpa hak memutuskan).
     */
    public function test_user_with_only_view_queue_permission_can_list_but_not_verify(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view revisi queue penugasan');
        Sanctum::actingAs($user);
        $proposal = $this->proposalMenungguVerifikasi($user->kodeperson);

        $this->getJson('/api/v1/rev/penugasan/revisi')->assertOk();

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/verifikasi-revisi", [
            'status' => 'DISETUJUI', 'catatan' => 'x',
        ])->assertStatus(403);
    }

    public function test_user_with_only_verifikasi_permission_cannot_list_the_queue(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('verifikasi revisi penugasan');
        Sanctum::actingAs($user);
        $this->proposalMenungguVerifikasi($user->kodeperson);

        $this->getJson('/api/v1/rev/penugasan/revisi')->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $proposal = $this->proposalMenungguVerifikasi('REV001');

        $this->postJson("/api/v1/rev/penugasan/{$proposal->id}/verifikasi-revisi", [
            'status' => 'DISETUJUI', 'catatan' => 'x',
        ])->assertStatus(401);
    }
}
