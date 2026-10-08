<?php

namespace Tests\Feature\Peneliti;

use App\Models\SkimPenelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class TemplateUnduhanTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_templates_per_skim_using_skim_name(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        SkimPenelitian::create(['KODESKIM' => 'INT03', 'NAMASKIM' => 'Dosen Pemula DB']);
        $this->actingAs(User::factory()->create()->assignRole('PEN'), 'web');

        // Katalog template tampil di dashboard peneliti.
        $data = collect($this->get('/pen/dashboard')->assertOk()->inertiaProp('templates'));

        $this->assertCount(27, $data);
        $this->assertSame('Dosen Pemula DB', $data->firstWhere('id', 'tpl03-proposal')['skim']);
        $this->assertNull($data->firstWhere('id', 'tpl09-proposal'));
        $this->assertTrue($data->firstWhere('id', 'tpl03-panduan')['readOnly']);
        $this->assertFalse($data->firstWhere('id', 'tpl03-proposal')['readOnly']);
    }

    public function test_download_serves_legacy_file_for_skim_and_jenis(): void
    {
        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->putFileAs('tpldoc', UploadedFile::fake()->create('x.docx', 1), 'FNAME_TPL03PROPOSAL.docx');
        $this->actingAs(User::factory()->create(), 'web');

        $res = $this->get('/pen/template/tpl03-proposal/unduh')->assertOk();
        $this->assertStringContainsString('Template_Proposal_Penelitian_Dosen_Pemula.docx', $res->headers->get('content-disposition'));

        Storage::disk('legacy_res')->putFileAs('tpldoc', UploadedFile::fake()->create('x.pdf', 1), 'FNAME_TPL03PANDUAN.pdf');
        $panduan = $this->get('/pen/template/tpl03-panduan/unduh')->assertOk();
        $this->assertStringStartsWith('inline', $panduan->headers->get('content-disposition'));

        $this->get('/pen/template/tpl03-laporan/unduh')->assertNotFound();
        $this->get('/pen/template/tpl09-proposal/unduh')->assertNotFound();
        $this->get('/pen/template/../unduh')->assertNotFound();
    }
}
