<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_dosen_search_returns_active_dosen_ordered_by_nama(): void
    {
        $prodi = Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika']);

        Person::create([
            'KODEPERSON' => 'DSN02',
            'NAMALENGKAP' => 'Budi Santoso',
            'STATUSNYA' => 'A',
            'KDPRODI' => $prodi->KODEPRODI,
        ]);

        Person::create([
            'KODEPERSON' => 'DSN01',
            'NAMALENGKAP' => 'Agus Pratama',
            'STATUSNYA' => 'A',
            'KDPRODI' => $prodi->KODEPRODI,
        ]);

        // Inactive person should be excluded
        Person::create([
            'KODEPERSON' => 'DSN03',
            'NAMALENGKAP' => 'Candra Pensiun',
            'STATUSNYA' => 'T',
            'KDPRODI' => $prodi->KODEPRODI,
        ]);

        $res = $this->getJson('/api/v1/dosen')->assertOk();

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('Agus Pratama', $data[0]['nama']);
        $this->assertSame('DSN01', $data[0]['npp']);
        $this->assertSame('Teknik Informatika', $data[0]['prodi']);
        $this->assertSame('Budi Santoso', $data[1]['nama']);
        $this->assertSame('DSN02', $data[1]['npp']);
    }

    public function test_dosen_search_filters_by_query_name_and_npp(): void
    {
        Person::create(['KODEPERSON' => '12345', 'NAMALENGKAP' => 'Prof. Dr. Hendra', 'STATUSNYA' => 'A']);
        Person::create(['KODEPERSON' => '67890', 'NAMALENGKAP' => 'Dr. Maya', 'STATUSNYA' => 'A']);

        // Query by name
        $resName = $this->getJson('/api/v1/dosen?q=Hendra')->assertOk()->json('data');
        $this->assertCount(1, $resName);
        $this->assertSame('12345', $resName[0]['npp']);

        // Query by NPP
        $resNpp = $this->getJson('/api/v1/dosen?q=67890')->assertOk()->json('data');
        $this->assertCount(1, $resNpp);
        $this->assertSame('Dr. Maya', $resNpp[0]['nama']);
    }

    public function test_dosen_search_excludes_superusers_external_and_admin_accounts(): void
    {
        // Real lecturer
        Person::create([
            'KODEPERSON' => '111080619',
            'NAMALENGKAP' => 'Dosen Asli, S.Kom., M.Kom.',
            'STATUSNYA' => 'A',
            'ISSUPERUSER' => false,
            'ISEXTERNAL' => false,
        ]);

        // Administrator / Superuser account (like legacy XADM01)
        Person::create([
            'KODEPERSON' => 'XADM01',
            'NAMALENGKAP' => 'ADMINISTRATOR #1',
            'STATUSNYA' => 'A',
            'ISSUPERUSER' => true,
            'ISEXTERNAL' => true,
            'ISPENELITI' => true,
        ]);

        // External person
        Person::create([
            'KODEPERSON' => '521970284',
            'NAMALENGKAP' => 'Ir. Wenny Eksternal',
            'STATUSNYA' => 'A',
            'ISSUPERUSER' => false,
            'ISEXTERNAL' => true,
        ]);

        // Searching "admin" should NOT return ADMINISTRATOR #1
        $resAdmin = $this->getJson('/api/v1/dosen?q=admin')->assertOk()->json('data');
        $this->assertCount(0, $resAdmin);

        // General list should only return real internal lecturer
        $resAll = $this->getJson('/api/v1/dosen')->assertOk()->json('data');
        $this->assertCount(1, $resAll);
        $this->assertSame('111080619', $resAll[0]['npp']);
    }
}
