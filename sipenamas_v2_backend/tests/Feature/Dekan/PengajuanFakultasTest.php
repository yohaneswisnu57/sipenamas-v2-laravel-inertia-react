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

class PengajuanFakultasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');

        $dekan = User::factory()->create();
        $dekan->givePermissionTo('view penelitian');
        Sanctum::actingAs($dekan);

        Fakultas::create(['KODEFAKULTAS' => 'FT', 'NAMAFAKULTAS' => 'Teknik', 'KDDEKAN' => $dekan->kodeperson]);
        Fakultas::create(['KODEFAKULTAS' => 'FK', 'NAMAFAKULTAS' => 'Kedokteran', 'KDDEKAN' => 'D00099']);
        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT']);
        Prodi::create(['KODEPRODI' => 'KG', 'NAMAPRODI' => 'Kedokteran Gigi', 'KDFAKULTAS' => 'FK']);
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        Periode::create(['KODEPERIODE' => '2025', 'TAHUN' => 2025, 'ISAKTIF' => 0]);
    }

    private function penelitian(array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Usulan',
            'KDPRODI' => 'TI',
            'JENIS_PA' => 'PENELITIAN',
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'ISPENGAJUANFINAL' => 1,
            'STATUSFINALAPPROVAL' => '-',
            'STATUSKETUNTASANPENELITIAN' => '-',
        ], $overrides));
    }

    public function test_lists_every_stage_of_the_dekan_faculty_with_resolved_status(): void
    {
        $this->penelitian(['JUDULPENELITIAN' => 'Proses']);
        $this->penelitian(['JUDULPENELITIAN' => 'Berjalan', 'STATUSFINALAPPROVAL' => 'LOLOS']);
        $this->penelitian(['JUDULPENELITIAN' => 'Tuntas', 'STATUSFINALAPPROVAL' => 'LOLOS', 'STATUSKETUNTASANPENELITIAN' => 'TUNTAS BERSYARAT']);

        $response = $this->getJson('/api/v1/dkn/pengajuan')->assertOk();

        $status = collect($response->json('data'))->pluck('status', 'judul');
        $this->assertSame('LOLOS', $status['Berjalan']);
        $this->assertSame('TUNTAS', $status['Tuntas']);
        $this->assertNotSame('TUNTAS', $status['Proses']);
        $this->assertCount(3, $status);
    }

    public function test_excludes_other_faculty_drafts_and_abdimas(): void
    {
        $this->penelitian(['JUDULPENELITIAN' => 'Milik']);
        $this->penelitian(['JUDULPENELITIAN' => 'Fakultas Lain', 'KDPRODI' => 'KG']);
        $this->penelitian(['JUDULPENELITIAN' => 'Draft', 'ISPENGAJUANFINAL' => 0]);
        $this->penelitian(['JUDULPENELITIAN' => 'Abdimas', 'JENIS_PA' => 'ABDIMAS']);

        $this->getJson('/api/v1/dkn/pengajuan')
            ->assertOk()
            ->assertJsonPath('data.*.judul', ['Milik']);
    }

    public function test_filters_by_periode_and_accepts_all(): void
    {
        $this->penelitian(['JUDULPENELITIAN' => 'Baru']);
        $this->penelitian(['JUDULPENELITIAN' => 'Lama', 'PERIODEKEGIATAN_TAHUN' => 2025]);

        $this->getJson('/api/v1/dkn/pengajuan?kdperiode=2025')->assertJsonPath('data.*.judul', ['Lama']);
        $this->assertCount(2, $this->getJson('/api/v1/dkn/pengajuan?kdperiode=ALL')->json('data'));
    }

    public function test_requires_view_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/dkn/pengajuan')->assertForbidden();
    }
}
