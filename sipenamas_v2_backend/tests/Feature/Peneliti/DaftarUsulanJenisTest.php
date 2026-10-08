<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

/**
 * Legacy permohonanpenelitian.php / permohonanabdimas.php LST memisahkan
 * daftar per JENIS_PA dan memfilter tahun lewat PERIODEKEGIATAN_TAHUN.
 */
class DaftarUsulanJenisTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        $this->ketua = User::factory()->create();
        $this->ketua->givePermissionTo('view penelitian');
        $this->actingAs($this->ketua, 'web');

        foreach ([['PENELITIAN', 2026], ['PENELITIAN', 2025], ['ABDIMAS', 2026]] as [$jenis, $tahun]) {
            Penelitian::create([
                'JUDULPENELITIAN' => "{$jenis} {$tahun}",
                'PERMOHONANDIBUAT_KDPERSON' => $this->ketua->kodeperson,
                'JENIS_PA' => $jenis,
                'PERIODEKEGIATAN_TAHUN' => $tahun,
            ]);
        }
    }

    public function test_default_list_only_contains_penelitian(): void
    {
        $judul = collect($this->get('/pen/penelitian')->assertOk()->inertiaProp('proposals'))->pluck('judul');

        $this->assertEqualsCanonicalizing(['PENELITIAN 2026', 'PENELITIAN 2025'], $judul->all());
    }

    public function test_jenis_abdimas_lists_abdimas_with_tahun_from_periode_kegiatan(): void
    {
        $data = $this->get('/pen/abdimas')->assertOk()->inertiaProp('abdimasList');

        $this->assertSame(['ABDIMAS 2026'], array_column($data, 'judul'));
        $this->assertSame('2026', $data[0]['tahun']);
    }

    public function test_tahun_filter_uses_periode_kegiatan_tahun(): void
    {
        $data = $this->get('/pen/penelitian?tahun=2025')->assertOk()->inertiaProp('proposals');

        $this->assertSame(['PENELITIAN 2025'], array_column($data, 'judul'));
    }
}
