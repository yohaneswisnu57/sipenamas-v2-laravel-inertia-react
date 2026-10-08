<?php

namespace Tests\Feature\Peneliti;

use App\Http\Resources\PenelitianResource;
use App\Models\Fakultas;
use App\Models\Penelitian;
use App\Models\Person;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

/**
 * Requirement LPPM: lembar pengesahan pakai QR code berisi NIK + nama
 * Dekan dan Ketua - lihat App\Services\Proposal\PengesahanQrService.
 */
class PengesahanQrTest extends TestCase
{
    use RefreshDatabase;

    private function resourceFor(Penelitian $penelitian): array
    {
        return (new PenelitianResource(
            Penelitian::with(PenelitianResource::EAGER_RELATIONS)->find($penelitian->id)
        ))->resolve();
    }

    public function test_ketua_qr_is_always_present_and_encodes_nik_and_nama(): void
    {
        Person::create(['KODEPERSON' => 'P00099', 'NAMALENGKAP' => 'Dr. Budi Santoso']);

        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Uji Pengesahan',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ]);

        $resource = $this->resourceFor($penelitian);

        $this->assertSame('P00099', $resource['pengesahan']['ketua']['nik']);
        $this->assertSame('Dr. Budi Santoso', $resource['pengesahan']['ketua']['nama']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $resource['pengesahan']['ketua']['qr']);
    }

    public function test_dekan_qr_is_null_before_dekan_decides(): void
    {
        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Uji Pengesahan',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ]);

        $resource = $this->resourceFor($penelitian);

        $this->assertNull($resource['pengesahan']['dekan']);
    }

    public function test_dekan_qr_is_populated_after_approving(): void
    {
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        SpatiePermission::findOrCreate('approve penelitian', 'sanctum');

        $dekan = User::factory()->create();
        Person::create(['KODEPERSON' => $dekan->kodeperson, 'NAMALENGKAP' => 'Dr. Siti Aminah']);
        Fakultas::create(['KODEFAKULTAS' => 'FT', 'NAMAFAKULTAS' => 'Fakultas Teknik', 'KDDEKAN' => $dekan->kodeperson]);
        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT']);
        $dekan->givePermissionTo('view penelitian', 'approve penelitian');
        $this->actingAs($dekan, 'web');

        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Uji Pengesahan',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'KDPRODI' => 'TI',
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ]);

        $this->postJson("/api/v1/dkn/proposal/{$penelitian->id}/approve", [
            'catatan' => 'Layak dilanjutkan',
        ])->assertOk();

        $resource = $this->resourceFor($penelitian);

        $this->assertSame($dekan->kodeperson, $resource['pengesahan']['dekan']['nik']);
        $this->assertSame('Dr. Siti Aminah', $resource['pengesahan']['dekan']['nama']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $resource['pengesahan']['dekan']['qr']);
    }
}
