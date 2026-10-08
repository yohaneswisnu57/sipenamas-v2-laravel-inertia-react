<?php

namespace Tests\Feature\Dekan;

use App\Models\Fakultas;
use App\Models\Penelitian;
use App\Models\PenelitianTim;
use App\Models\Person;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class MonevPenunjukanTest extends TestCase
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
    }

    private function penelitianTuntas(array $overrides = []): Penelitian
    {
        return Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Penelitian Tuntas',
            'JENIS_PA' => 'PENELITIAN',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'KDPRODI' => 'TI',
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'STATUSFINALAPPROVAL' => 'LOLOS',
            'STATUSKETUNTASANPENELITIAN' => 'TUNTAS',
        ], $overrides));
    }

    private function dosen(string $kodeperson, string $kodeProdi = 'TI', bool $isGjm = true): Person
    {
        return Person::create([
            'KODEPERSON' => $kodeperson,
            'NAMALENGKAP' => "Dosen {$kodeperson}",
            'KDPRODI' => $kodeProdi,
            'ISGJM' => $isGjm,
        ]);
    }

    public function test_dekan_sees_only_lolos_and_tuntas_penelitian_from_own_faculty(): void
    {
        $tuntas = $this->penelitianTuntas();
        $bersyarat = $this->penelitianTuntas(['STATUSKETUNTASANPENELITIAN' => 'TUNTAS BERSYARAT']);
        $this->penelitianTuntas(['STATUSKETUNTASANPENELITIAN' => 'BELUM TUNTAS']);
        $this->penelitianTuntas(['STATUSFINALAPPROVAL' => 'TIDAK LOLOS']);
        $this->penelitianTuntas(['KDPRODI' => 'KG']);
        $this->penelitianTuntas(['JENIS_PA' => 'ABDIMAS']);

        $response = $this->getJson('/api/v1/dkn/monev')->assertOk();

        $this->assertEqualsCanonicalizing([$tuntas->id, $bersyarat->id], $response->json('data.*.id'));
    }

    public function test_dekan_sees_abdimas_queue_with_the_jenis_filter(): void
    {
        $this->penelitianTuntas();
        $abdimas = $this->penelitianTuntas(['JENIS_PA' => 'ABDIMAS']);
        $abdimasBersyarat = $this->penelitianTuntas([
            'JENIS_PA' => 'ABDIMAS',
            'STATUSKETUNTASANPENELITIAN' => 'TUNTAS BERSYARAT',
        ]);

        $response = $this->getJson('/api/v1/dkn/monev?jenis=ABDIMAS')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$abdimas->id, $abdimasBersyarat->id],
            $response->json('data.*.id')
        );
    }

    public function test_penunjukan_works_for_abdimas(): void
    {
        $abdimas = $this->penelitianTuntas(['JENIS_PA' => 'ABDIMAS']);
        $this->dosen('G001');

        $this->postJson("/api/v1/dkn/monev/{$abdimas->id}/penunjukan", [
            'kodeperson' => 'G001', 'jenis' => 'ABDIMAS',
        ])->assertOk();

        $this->assertSame('G001', $abdimas->fresh()->MONEVHASILBY);
        $this->assertDatabaseHas('penelitian_monevhasil', ['IDPARENT' => $abdimas->id]);
    }

    public function test_jenis_filter_keeps_abdimas_out_of_the_penelitian_queue(): void
    {
        $abdimas = $this->penelitianTuntas(['JENIS_PA' => 'ABDIMAS']);
        $this->dosen('G001');

        $this->postJson("/api/v1/dkn/monev/{$abdimas->id}/penunjukan", ['kodeperson' => 'G001'])
            ->assertStatus(404);
    }

    public function test_unknown_jenis_is_rejected(): void
    {
        $this->getJson('/api/v1/dkn/monev?jenis=HKI')->assertStatus(422);
    }

    public function test_kandidat_are_gjm_lecturers_of_the_faculty_outside_the_team(): void
    {
        $penelitian = $this->penelitianTuntas();
        $this->dosen('G001');
        $this->dosen('G002');
        $this->dosen('N001', isGjm: false);
        $this->dosen('K001', 'KG');
        PenelitianTim::create(['IDPARENT' => $penelitian->id, 'NIKNIDN' => 'G002']);

        $response = $this->getJson("/api/v1/dkn/monev/{$penelitian->id}/kandidat")->assertOk();

        $this->assertSame(['G001'], $response->json('data.*.kodeperson'));
    }

    public function test_penunjukan_sets_reviewer_and_creates_a_single_monev_row(): void
    {
        $penelitian = $this->penelitianTuntas();
        $this->dosen('G001');
        $this->dosen('G003');

        $this->postJson("/api/v1/dkn/monev/{$penelitian->id}/penunjukan", ['kodeperson' => 'G001'])->assertOk();
        $this->postJson("/api/v1/dkn/monev/{$penelitian->id}/penunjukan", ['kodeperson' => 'G003'])->assertOk();

        $this->assertSame('G003', $penelitian->fresh()->MONEVHASILBY);
        $this->assertDatabaseCount('penelitian_monevhasil', 1);
        $this->assertDatabaseHas('penelitian_monevhasil', ['IDPARENT' => $penelitian->id]);
    }

    public function test_penunjukan_rejects_a_non_candidate(): void
    {
        $penelitian = $this->penelitianTuntas();
        $this->dosen('N001', isGjm: false);

        $this->postJson("/api/v1/dkn/monev/{$penelitian->id}/penunjukan", ['kodeperson' => 'N001'])
            ->assertStatus(422);

        $this->assertNull($penelitian->fresh()->MONEVHASILBY);
    }

    public function test_dekan_cannot_assign_for_another_faculty(): void
    {
        $penelitian = $this->penelitianTuntas(['KDPRODI' => 'KG']);
        $this->dosen('K001', 'KG');

        $this->postJson("/api/v1/dkn/monev/{$penelitian->id}/penunjukan", ['kodeperson' => 'K001'])
            ->assertStatus(404);
    }
}
