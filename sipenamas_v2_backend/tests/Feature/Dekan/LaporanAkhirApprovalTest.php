<?php

namespace Tests\Feature\Dekan;

use App\Models\Fakultas;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class LaporanAkhirApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        SpatiePermission::findOrCreate('approve penelitian', 'sanctum');

        $dekan = User::factory()->create();
        $dekan->givePermissionTo('view penelitian', 'approve penelitian');
        Sanctum::actingAs($dekan);

        Fakultas::create(['KODEFAKULTAS' => 'FT', 'NAMAFAKULTAS' => 'Teknik', 'KDDEKAN' => $dekan->kodeperson]);
        Fakultas::create(['KODEFAKULTAS' => 'FK', 'NAMAFAKULTAS' => 'Kedokteran', 'KDDEKAN' => 'D00099']);
        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT']);
        Prodi::create(['KODEPRODI' => 'KG', 'NAMAPRODI' => 'Kedokteran Gigi', 'KDFAKULTAS' => 'FK']);
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
    }

    private function penelitian(array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Penelitian Lolos',
            'JENIS_PA' => 'PENELITIAN',
            'KDPRODI' => 'TI',
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'ISPENGAJUANFINAL' => 1,
            'ISDOKUMENPROPOSALFINAL' => 1,
            'STATUSFINALAPPROVAL' => 'LOLOS',
            'LBRPENGESAHANLAPHASIL_ISFINAL' => 1,
        ], $overrides));
    }

    public function test_dekan_lists_lolos_penelitian_of_own_faculty_for_the_period(): void
    {
        $milik = $this->penelitian();
        $this->penelitian(['KDPRODI' => 'KG']);
        $this->penelitian(['STATUSFINALAPPROVAL' => '-']);
        $this->penelitian(['PERIODEKEGIATAN_TAHUN' => 2025]);

        $this->getJson('/api/v1/dkn/laporan-akhir?kdperiode=2026')
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$milik->id]);
    }

    public function test_approval_needs_a_final_lembar_pengesahan(): void
    {
        $penelitian = $this->penelitian(['LBRPENGESAHANLAPHASIL_ISFINAL' => 0]);

        $this->postJson("/api/v1/dkn/laporan-akhir/{$penelitian->id}/approve")->assertStatus(422);

        $this->assertFalse((bool) $penelitian->fresh()->ISDEKANAPPROVELAPORANAKHIR);
    }

    public function test_dekan_approves_laporan_akhir_once(): void
    {
        $penelitian = $this->penelitian();

        $this->postJson("/api/v1/dkn/laporan-akhir/{$penelitian->id}/approve")->assertOk();
        $waktu = $penelitian->fresh()->TSDEKANAPPROVELAPORANAKHIR;
        $this->travel(1)->hours();
        $this->postJson("/api/v1/dkn/laporan-akhir/{$penelitian->id}/approve")->assertOk();

        $penelitian->refresh();
        $this->assertTrue((bool) $penelitian->ISDEKANAPPROVELAPORANAKHIR);
        $this->assertNotNull($waktu);
        $this->assertEquals($waktu, $penelitian->TSDEKANAPPROVELAPORANAKHIR);
    }

    public function test_dekan_cannot_approve_for_another_faculty(): void
    {
        $penelitian = $this->penelitian(['KDPRODI' => 'KG']);

        $this->postJson("/api/v1/dkn/laporan-akhir/{$penelitian->id}/approve")->assertNotFound();
    }
}
