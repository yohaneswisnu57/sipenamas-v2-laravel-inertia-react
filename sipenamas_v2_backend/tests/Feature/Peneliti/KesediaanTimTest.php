<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\PenelitianTim;
use App\Models\Settingan;
use App\Models\SkimPenelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class KesediaanTimTest extends TestCase
{
    use RefreshDatabase;

    private User $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('create penelitian', 'sanctum');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        SkimPenelitian::create(['KODESKIM' => 'INT01', 'NAMASKIM' => 'Penelitian Dasar', 'ISABDIMAS' => 0]);

        $this->anggota = User::factory()->create();
        $this->anggota->givePermissionTo('create penelitian', 'view penelitian');
        $this->actingAs($this->anggota, 'web');
    }

    private function undangan(array $penelitian = []): PenelitianTim
    {
        $usulan = Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Usulan Orang Lain',
            'KDSKIMPENELITIAN' => 'INT01',
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'ISPENGAJUANFINAL' => 1,
        ], $penelitian));

        return $usulan->tim()->create(['NIKNIDN' => $this->anggota->kodeperson, 'PERAN' => 'ANGGOTA', 'ISAPPROVED' => 0]);
    }

    public function test_anggota_sees_pending_invitations_only(): void
    {
        $pending = $this->undangan();
        $this->undangan(['JUDULPENELITIAN' => 'Masih Draft', 'ISPENGAJUANFINAL' => 0]);

        $this->get('/pen/kesediaan-tim')
            ->assertOk()
            ->assertPropCount(1, 'items')
            ->assertProp('items.0.timId', $pending->id);
    }

    public function test_anggota_can_approve_membership(): void
    {
        $tim = $this->undangan();

        $this->post("/pen/kesediaan-tim/{$tim->id}/setuju")->assertAksiBerhasil();

        $this->assertTrue($tim->fresh()->ISAPPROVED);
        $this->assertNotNull($tim->fresh()->TSAPPROVED);
    }

    public function test_approval_is_blocked_when_anggota_quota_is_reached(): void
    {
        Settingan::create(['THISISIT' => 'arief@nuriman.id', 'KUOTA_PENELITIAN_ANGGOTA' => 1]);
        $sudah = $this->undangan(['JUDULPENELITIAN' => 'Sudah Disetujui']);
        $sudah->update(['ISAPPROVED' => 1]);
        $tim = $this->undangan();

        $this->post("/pen/kesediaan-tim/{$tim->id}/setuju")
            ->assertSessionHasErrors('kuota');

        $this->assertFalse($tim->fresh()->ISAPPROVED);
    }

    public function test_user_cannot_approve_someone_elses_membership(): void
    {
        $usulan = Penelitian::create(['JUDULPENELITIAN' => 'Lain', 'ISPENGAJUANFINAL' => 1]);
        $tim = $usulan->tim()->create(['NIKNIDN' => 'P99999', 'PERAN' => 'ANGGOTA', 'ISAPPROVED' => 0]);

        $this->post("/pen/kesediaan-tim/{$tim->id}/setuju")->assertAksiDitolak('Data tidak ditemukan.');
    }
}
