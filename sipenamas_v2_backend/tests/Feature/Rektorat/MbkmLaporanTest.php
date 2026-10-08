<?php

namespace Tests\Feature\Rektorat;

use App\Models\Mahasiswa;
use App\Models\MbkmDatahasilDosen;
use App\Models\MbkmMhs;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class MbkmLaporanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view rektorat', 'sanctum');
        $rektorat = User::factory()->create();
        $rektorat->givePermissionTo('view rektorat');
        Sanctum::actingAs($rektorat);

        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT', 'PREFIXNIK' => '51', 'ISMADIUN' => 0]);
        Prodi::create(['KODEPRODI' => 'TI5', 'NAMAPRODI' => 'Teknik Industri', 'KDFAKULTAS' => 'FT', 'PREFIXNIK' => '515', 'ISMADIUN' => 0]);
    }

    public function test_prodi_is_resolved_by_longest_nim_prefix(): void
    {
        MbkmMhs::create(['NIM' => '5150001', 'NAMAMAHASISWA' => ' Dimas ']);
        MbkmMhs::create(['NIM' => '5120002', 'NAMAMAHASISWA' => 'Clara']);

        $rows = collect($this->getJson('/api/v1/rkt/mbkm/pengisian')->assertOk()->json('data'))
            ->keyBy('nim');

        $this->assertSame('Teknik Industri', $rows['5150001']['namaProdi']);
        $this->assertSame('Teknik Informatika', $rows['5120002']['namaProdi']);
        $this->assertSame('Dimas', $rows['5150001']['nama']);
    }

    public function test_rekap_prodi_counts_only_non_madiun_students_with_percentage(): void
    {
        Mahasiswa::create(['NIM' => '5120001', 'NAMAMAHASISWA' => 'A', 'NAMAPRODI' => 'Teknik Informatika']);
        Mahasiswa::create(['NIM' => '5120002', 'NAMAMAHASISWA' => 'B', 'NAMAPRODI' => 'Teknik Informatika']);
        Mahasiswa::create(['NIM' => '5120003', 'NAMAMAHASISWA' => 'C', 'NAMAPRODI' => 'Teknik Informatika Madiun']);

        MbkmMhs::create(['NIM' => '5120001', 'NAMAMAHASISWA' => 'A']);
        MbkmMhs::create(['NIM' => '5120003', 'NAMAMAHASISWA' => 'C']);

        $rows = collect($this->getJson('/api/v1/rkt/mbkm/rekap-prodi')->assertOk()->json('data.prodi'))
            ->keyBy('kodeProdi');

        $this->assertSame(1, $rows['TI']['jumlahPengisi']);
        $this->assertSame(2, $rows['TI']['totalMahasiswa']);
        $this->assertEquals(50.0, $rows['TI']['persen']);
    }

    public function test_belum_mengisi_excludes_students_already_in_mbkm(): void
    {
        Mahasiswa::create(['NIM' => '5120001', 'NAMAMAHASISWA' => 'A', 'NAMAPRODI' => 'Teknik Informatika']);
        Mahasiswa::create(['NIM' => '5120002', 'NAMAMAHASISWA' => 'B', 'NAMAPRODI' => 'Teknik Informatika']);
        MbkmMhs::create(['NIM' => '5120001', 'NAMAMAHASISWA' => 'A']);

        $nim = $this->getJson('/api/v1/rkt/mbkm/belum-mengisi')->assertOk()->json('data.*.nim');

        $this->assertSame(['5120002'], $nim);
    }

    public function test_data_hasil_returns_questionnaire_rows_and_rejects_unknown_respondent(): void
    {
        MbkmDatahasilDosen::create(['IDENTITAS' => '12345', 'NAMA' => 'Budi', 'PERTANYAAN' => 'Apakah jelas?', 'JAWABAN' => 'Ya']);

        $this->getJson('/api/v1/rkt/mbkm/data-hasil/dosen')
            ->assertOk()
            ->assertJsonPath('data.0.jawaban', 'Ya');

        $this->getJson('/api/v1/rkt/mbkm/data-hasil/rektor')->assertNotFound();
    }
}
