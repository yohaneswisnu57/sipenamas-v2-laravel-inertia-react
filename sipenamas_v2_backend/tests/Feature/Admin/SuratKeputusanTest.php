<?php

namespace Tests\Feature\Admin;

use App\Models\Fakultas;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Person;
use App\Models\Prodi;
use App\Models\SkimPenelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;
use ZipArchive;

class SuratKeputusanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('legacy_res');
        SpatiePermission::findOrCreate('view final approval', 'sanctum');
        SpatiePermission::findOrCreate('decide final approval penelitian', 'sanctum');
        Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo('view final approval', 'decide final approval penelitian'));

        Fakultas::create(['KODEFAKULTAS' => 'FT', 'NAMAFAKULTAS' => 'FAKULTAS TEKNIK']);
        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT']);
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1, 'TGLEND' => '2026-12-01', 'TGLPELAKSANAANBEGIN' => '2026-06-01', 'TGLPELAKSANAANEND' => '2026-11-30']);
        SkimPenelitian::create(['KODESKIM' => 'INT01', 'NAMASKIM' => 'Penelitian Dasar', 'NOMORKODEANGGARAN' => '612.01.2439']);
        DB::table('tabelkodeanggaran')->insert(['NOMORKODE' => '612.01.2439', 'KETERANGAN' => 'Hibah Penelitian']);
        Person::create(['KODEPERSON' => 'P00099', 'NAMALENGKAP' => 'Dr. Ketua']);
    }

    private function usulan(string $status): Penelitian
    {
        $p = Penelitian::create([
            'JUDULPENELITIAN' => 'Judul Uji',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'KDPRODI' => 'TI',
            'KDSKIMPENELITIAN' => 'INT01',
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'STATUSFINALAPPROVAL' => $status,
            'NOMINALDANA_FINAL' => 4_500_000,
        ]);
        $p->tim()->create(['NIKNIDN' => 'P00099', 'PERAN' => 'KETUA', 'ISAPPROVED' => 1, 'URUTAN' => 1]);

        return $p;
    }

    private function teks(string $namaFile): string
    {
        $zip = new ZipArchive;
        $zip->open(Storage::disk('legacy_res')->path("proposal/{$namaFile}"));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return strip_tags($xml);
    }

    public function test_lolos_gets_surat_tugas_and_spd_with_consecutive_numbers_and_70_percent_dana(): void
    {
        $a = $this->usulan('LOLOS');
        $b = $this->usulan('TIDAK LOLOS');
        $c = $this->usulan('LOLOS');

        $this->postJson('/api/v1/adm/surat/generate', ['ids' => [$a->id, $b->id, $c->id], 'nomor' => 10, 'tanggal' => '2026-09-30'])->assertOk();

        $a->refresh();
        $b->refresh();
        $c->refresh();
        $this->assertSame([10, 11], [(int) $a->CETAKSURATTUGAS_NOMORSURAT, (int) $a->CETAKSURATDANA_NOMORSURAT]);
        $this->assertSame([12, null], [(int) $b->CETAKSURATTUGAS_NOMORSURAT, $b->CETAKSURATDANA_NOMORSURAT]);
        $this->assertSame("surattugaspp_{$b->id}.docx", $b->CETAKSURATTUGAS_NAMAFILE);
        $this->assertSame([13, 14], [(int) $c->CETAKSURATTUGAS_NOMORSURAT, (int) $c->CETAKSURATDANA_NOMORSURAT]);

        $st = $this->teks("surattugas_{$a->id}.docx");
        $this->assertStringContainsString('3.150.000', $st);
        $this->assertStringContainsString('tiga juta seratus lima puluh ribu', $st);
        $this->assertStringContainsString('612.01.2439 (Hibah Penelitian)', $st);
        $this->assertStringContainsString('01-06-2026', $st);
        $this->assertStringContainsString('Fakultas Teknik', $st);
        $this->assertStringContainsString('Dr. Ketua (P00099)', $st);
        $this->assertStringNotContainsString('${', str_replace('${MGZ}', '', $st));
        $this->assertStringContainsString('3.150.000', $this->teks("suratdana_{$a->id}.docx"));
    }

    public function test_set_final_marks_documents_final_and_blocks_regenerate(): void
    {
        $a = $this->usulan('LOLOS');
        $this->postJson('/api/v1/adm/surat/generate', ['ids' => [$a->id], 'nomor' => 1, 'tanggal' => '2026-09-30'])->assertOk();

        $this->postJson('/api/v1/adm/surat/final', ['ids' => [$a->id]])->assertOk();

        $a->refresh();
        $this->assertSame(['FINAL', 'FINAL'], [$a->CETAKSURATTUGAS_STATUS, $a->CETAKSURATDANA_STATUS]);
        $this->postJson('/api/v1/adm/surat/final', ['ids' => [$a->id]])->assertStatus(422);
        $this->postJson('/api/v1/adm/surat/generate', ['ids' => [$a->id], 'nomor' => 50, 'tanggal' => '2026-09-30'])->assertStatus(422);
        $this->getJson("/api/v1/adm/final-approval/{$a->id}/surat/SPD")->assertOk();
    }

    public function test_duplicate_number_needs_confirmation(): void
    {
        $a = $this->usulan('LOLOS');
        $b = $this->usulan('LOLOS');
        $this->postJson('/api/v1/adm/surat/generate', ['ids' => [$a->id], 'nomor' => 7, 'tanggal' => '2026-09-30'])->assertOk();

        $this->postJson('/api/v1/adm/surat/generate', ['ids' => [$b->id], 'nomor' => 6, 'tanggal' => '2026-09-30'])
            ->assertStatus(409)
            ->assertJsonPath('errors.nomorDuplikasi', [7]);

        $this->postJson('/api/v1/adm/surat/generate', ['ids' => [$b->id], 'nomor' => 6, 'tanggal' => '2026-09-30', 'abaikanDuplikasi' => true])->assertOk();
        $this->assertSame(6, (int) $b->refresh()->CETAKSURATTUGAS_NOMORSURAT);
    }
}
