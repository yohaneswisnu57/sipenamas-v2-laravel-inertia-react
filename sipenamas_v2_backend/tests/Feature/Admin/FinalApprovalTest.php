<?php

namespace Tests\Feature\Admin;

use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class FinalApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view final approval', 'sanctum');
        SpatiePermission::findOrCreate('decide final approval penelitian', 'sanctum');
    }

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view final approval', 'decide final approval penelitian');
        Sanctum::actingAs($user);

        return $user;
    }

    private function readyForFinalApproval(array $overrides = []): Penelitian
    {
        $penelitian = Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Usulan Siap Final Approval',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'JENIS_PA' => 'PENELITIAN',
        ], $overrides));

        // FINAL_APPROVAL status: semua reviewer STATUSPENILAIAN=FINAL.
        $penelitian->reviewers()->create(['NIK' => 'REV001', 'STATUSPENILAIAN' => 'FINAL', 'HASILPENILAIAN' => 'LOLOS']);
        $penelitian->reviewers()->create(['NIK' => 'REV002', 'STATUSPENILAIAN' => 'FINAL', 'HASILPENILAIAN' => 'LOLOS']);

        return $penelitian;
    }

    public function test_admin_sees_proposals_ready_for_final_approval(): void
    {
        $this->actingAdmin();
        $this->readyForFinalApproval();

        $response = $this->getJson('/api/v1/adm/final-approval')->assertOk();

        $this->assertContains('Usulan Siap Final Approval', collect($response->json('data'))->pluck('judul')->all());
    }

    public function test_list_defaults_to_active_periode_and_penelitian_only(): void
    {
        Periode::create(['KODEPERIODE' => '2025-1', 'TAHUN' => 2025, 'ISAKTIF' => 0]);
        Periode::create(['KODEPERIODE' => '2026-1', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        $this->actingAdmin();
        $this->readyForFinalApproval(['JUDULPENELITIAN' => 'Aktif']);
        $this->readyForFinalApproval(['JUDULPENELITIAN' => 'Lama', 'PERIODEKEGIATAN_TAHUN' => 2025]);
        $this->readyForFinalApproval(['JUDULPENELITIAN' => 'Abdimas', 'JENIS_PA' => 'ABDIMAS']);

        $judul = fn (string $url) => collect($this->getJson($url)->assertOk()->json('data'))->pluck('judul')->sort()->values()->all();

        $this->assertSame(['Aktif'], $judul('/api/v1/adm/final-approval'));
        $this->assertSame(['Lama'], $judul('/api/v1/adm/final-approval?kdperiode=2025-1'));
        $this->assertSame(['Aktif', 'Lama'], $judul('/api/v1/adm/final-approval?kdperiode=ALL'));
    }

    public function test_admin_can_approve_a_proposal(): void
    {
        $this->actingAdmin();
        $proposal = $this->readyForFinalApproval();

        $this->postJson("/api/v1/adm/final-approval/{$proposal->id}", [
            'status' => 'LOLOS',
            'biayaDisetujui' => 10000000,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('penelitian', [
            'id' => $proposal->id,
            'STATUSFINALAPPROVAL' => 'LOLOS',
            'NOMINALDANA_FINAL' => 10000000,
            // Legacy finalapproval.php editData tidak menulis nomor SK; nomor
            // surat resmi baru dibuat saat generate surat (CETAKSURATTUGAS_*).
            'SURATTUGAS_NOMORSK' => null,
        ]);
    }

    public function test_admin_can_reject_a_proposal_at_final_approval(): void
    {
        $this->actingAdmin();
        $proposal = $this->readyForFinalApproval();

        $this->postJson("/api/v1/adm/final-approval/{$proposal->id}", [
            'status' => 'TIDAK_LOLOS',
        ])->assertOk();

        $this->assertDatabaseHas('penelitian', [
            'id' => $proposal->id,
            'STATUSFINALAPPROVAL' => 'TIDAK LOLOS',
            'NOMINALDANA_FINAL' => null,
        ]);

    }

    public function test_decide_requires_biaya_disetujui_when_status_is_lolos(): void
    {
        $this->actingAdmin();
        $proposal = $this->readyForFinalApproval();

        $this->postJson("/api/v1/adm/final-approval/{$proposal->id}", [
            'status' => 'LOLOS',
        ])->assertStatus(422)->assertJsonValidationErrors(['biayaDisetujui']);
    }

    public function test_decide_rejects_an_unknown_status_value(): void
    {
        $this->actingAdmin();
        $proposal = $this->readyForFinalApproval();

        $this->postJson("/api/v1/adm/final-approval/{$proposal->id}", [
            'status' => 'DIBATALKAN',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_user_without_decide_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view final approval');
        Sanctum::actingAs($user);

        $proposal = $this->readyForFinalApproval();

        $this->postJson("/api/v1/adm/final-approval/{$proposal->id}", [
            'status' => 'LOLOS', 'biayaDisetujui' => 5000000,
        ])->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/adm/final-approval')->assertStatus(401);
    }
}
