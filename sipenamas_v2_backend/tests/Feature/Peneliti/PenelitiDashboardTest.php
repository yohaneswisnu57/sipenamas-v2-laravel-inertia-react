<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\PeriodeGelombang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Banner "Tindakan Diperlukan" di dashboard peneliti: hanya usulan REVISI
 * milik ketua (legacy hasilreviewpenelitian khusus ketua), dengan batas dari
 * `periodegelombang.TGLREVISI_TO` gelombang aktif, bukan tanggal tetap.
 */
class PenelitiDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::findOrCreate('PEN', 'sanctum');
        $this->ketua = User::factory()->create();
        $this->ketua->assignRole('PEN');
        $this->actingAs($this->ketua, 'web');
    }

    private function usulan(string $statusPenilaian, ?string $ketua = null): Penelitian
    {
        return Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Dashboard',
            'PERMOHONANDIBUAT_KDPERSON' => $ketua ?? $this->ketua->kodeperson,
            'JENIS_PA' => 'PENELITIAN',
            'ISPENGAJUANFINAL' => 1,
            'STATUSPENILAIANREVIEWER' => $statusPenilaian,
        ]);
    }

    public function test_pending_revisi_carries_the_active_gelombang_deadline(): void
    {
        $periode = Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        PeriodeGelombang::create(['IDPARENT' => $periode->id, 'GELAKTIF' => 1, 'TGLREVISI_TO' => '2026-11-20']);
        $usulan = $this->usulan('PERBAIKAN');

        $this->get('/pen/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pen/PenelitiDashboardPage')
                ->missing('stats')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.pendingAction.id', (string) $usulan->id)
                    ->where('stats.batasRevisi', '2026-11-20')
                    ->has('proposals', 1)
                    ->etc()));
    }

    public function test_no_pending_action_without_revisi(): void
    {
        $this->usulan('LANJUT');

        $this->get('/pen/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.pendingAction', null)
                    ->where('stats.batasRevisi', null)
                    ->etc()));
    }
}
