<?php

namespace Tests\Feature\Admin;

use App\Models\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class PeriodeUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view periode', 'sanctum');
        SpatiePermission::findOrCreate('create periode', 'sanctum');

        $this->periode = Periode::create([
            'KODEPERIODE' => '2026', 'TAHUN' => 2026, 'DESKRIPSI' => 'Periode Lama', 'ISAKTIF' => 1,
        ]);
    }

    private function actingAdmin(array $izin = ['view periode', 'create periode']): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo($izin);
        Sanctum::actingAs($admin);
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tahun' => '2027',
            'nama' => 'Periode Baru',
            'tglBukaUsulan' => '2027-01-01',
            'tglTutupUsulan' => '2027-02-01',
            'tglBatasReview' => '2027-03-01',
            'tglBatasRevisi' => '2027-04-01',
            'tglMonev' => '2027-08-01',
            'tglLaporanAkhir' => '2027-12-01',
            'tglPelaksanaanMulai' => '2027-03-10',
            'tglPelaksanaanSelesai' => '2027-03-20',
        ], $overrides);
    }

    public function test_admin_edits_periode_and_active_gelombang_schedule(): void
    {
        $this->actingAdmin();
        $gelombang = $this->periode->gelombang()->create([
            'GELOMBANG' => '1', 'GELAKTIF' => 1, 'TGLPROPOSAL_FROM' => '2026-01-01', 'TGLWAKTU_FROM' => '2026-05-05',
        ]);

        $this->putJson("/api/v1/adm/periode/{$this->periode->id}", $this->payload(['kodeperiode' => 'X', 'isaktif' => false]))
            ->assertOk()
            ->assertJsonPath('data.nama', 'Periode Baru')
            ->assertJsonPath('data.tglBukaUsulan', '2027-01-01')
            ->assertJsonPath('data.tglLaporanAkhir', '2027-12-01');

        $periode = $this->periode->fresh();
        $this->assertSame('2026', $periode->KODEPERIODE);
        $this->assertSame('2027', (string) $periode->TAHUN);
        $this->assertTrue($periode->ISAKTIF);
        $this->assertSame('2027-03-20', $periode->tglPelaksanaanSelesai());

        $gelombang->refresh();
        $this->assertSame(1, $periode->gelombang()->count());
        $this->assertSame('2027-02-01', substr((string) $gelombang->TGLREVIEW_FROM, 0, 10));
        $this->assertSame('2026-05-05', substr((string) $gelombang->TGLWAKTU_FROM, 0, 10));
    }

    public function test_edit_creates_active_gelombang_when_periode_has_none(): void
    {
        $this->actingAdmin();

        $this->putJson("/api/v1/adm/periode/{$this->periode->id}", $this->payload())->assertOk();

        $this->assertSame(1, $this->periode->gelombang()->where('GELAKTIF', 1)->count());
    }

    public function test_schedule_must_be_in_order(): void
    {
        $this->actingAdmin();

        $this->putJson("/api/v1/adm/periode/{$this->periode->id}", $this->payload(['tglBatasReview' => '2027-01-15']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tglBatasReview');
    }

    public function test_edit_needs_the_create_periode_permission(): void
    {
        $this->actingAdmin(['view periode']);

        $this->putJson("/api/v1/adm/periode/{$this->periode->id}", $this->payload())->assertStatus(403);
        $this->assertSame('Periode Lama', $this->periode->fresh()->DESKRIPSI);
    }
}
