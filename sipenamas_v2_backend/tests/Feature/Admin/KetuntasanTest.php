<?php

namespace Tests\Feature\Admin;

use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class KetuntasanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view final approval', 'sanctum');
        SpatiePermission::findOrCreate('decide final approval penelitian', 'sanctum');
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
    }

    private function actingAdmin(array $izin = ['view final approval', 'decide final approval penelitian']): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo($izin);
        Sanctum::actingAs($admin);
    }

    private function penelitian(array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Penelitian', 'JENIS_PA' => 'PENELITIAN', 'PERIODEKEGIATAN_TAHUN' => 2026,
            'STATUSFINALAPPROVAL' => 'LOLOS', 'STATUSKETUNTASANPENELITIAN' => '-',
        ], $overrides));
    }

    public function test_list_excludes_tidak_lolos_and_other_periods(): void
    {
        $this->actingAdmin();
        $milik = $this->penelitian();
        $this->penelitian(['STATUSFINALAPPROVAL' => 'TIDAK LOLOS']);
        $this->penelitian(['PERIODEKEGIATAN_TAHUN' => 2025]);
        $this->penelitian(['JENIS_PA' => 'ABDIMAS']);

        $this->getJson('/api/v1/adm/ketuntasan')->assertOk()->assertJsonPath('data.items.*.id', [$milik->id]);
    }

    public function test_admin_sets_status_ketuntasan(): void
    {
        $this->actingAdmin();
        $penelitian = $this->penelitian();

        foreach (['TUNTAS BERSYARAT', 'BELUM TUNTAS', 'BATAL', 'TUNTAS'] as $status) {
            $this->putJson("/api/v1/adm/ketuntasan/{$penelitian->id}", ['status' => $status])->assertOk();
            $this->assertSame($status, $penelitian->fresh()->STATUSKETUNTASANPENELITIAN);
        }
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->actingAdmin();
        $penelitian = $this->penelitian();

        $this->putJson("/api/v1/adm/ketuntasan/{$penelitian->id}", ['status' => 'SELESAI'])->assertStatus(422);
    }

    public function test_update_needs_the_decide_permission(): void
    {
        $this->actingAdmin(['view final approval']);
        $penelitian = $this->penelitian();

        $this->putJson("/api/v1/adm/ketuntasan/{$penelitian->id}", ['status' => 'TUNTAS'])->assertStatus(403);
    }
}
