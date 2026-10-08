<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\Person;
use App\Models\User;
use App\Services\Proposal\DocxToPdfConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class UnduhPengesahanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(\ZipArchive::class) || ! extension_loaded('gd')) {
            $this->markTestSkipped('Butuh ekstensi zip dan gd.');
        }
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
    }

    public function test_owner_downloads_docx_and_others_get_404(): void
    {
        $owner = User::factory()->create();
        $owner->givePermissionTo('view penelitian');
        Person::create(['KODEPERSON' => $owner->kodeperson, 'NAMALENGKAP' => 'Dr. Budi']);
        $p = Penelitian::create([
            'JUDULPENELITIAN' => 'Judul Uji',
            'PERMOHONANDIBUAT_KDPERSON' => $owner->kodeperson,
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
            'LBRPENGESAHANPROPOSAL_NAMAFILE' => 'lbrpengesahan_proposal_1.docx',
        ]);

        $this->actingAs($owner, 'web');
        $this->getJson("/pen/penelitian/{$p->id}/pengesahan")->assertStatus(422);
        $this->get("/pen/penelitian/{$p->id}/pengesahan?preview=1")->assertOk();

        $p->update(['APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1]);
        $res = $this->get("/pen/penelitian/{$p->id}/pengesahan");
        $res->assertOk();
        $this->assertStringContainsString('wordprocessingml', $res->headers->get('content-type'));

        $other = User::factory()->create();
        $other->givePermissionTo('view penelitian');
        $this->actingAs($other, 'web');
        $this->get("/pen/penelitian/{$p->id}/pengesahan")->assertNotFound();
    }

    private function ownerWithProposal(array $extra = []): array
    {
        $owner = User::factory()->create();
        $owner->givePermissionTo('view penelitian');
        Person::create(['KODEPERSON' => $owner->kodeperson, 'NAMALENGKAP' => 'Dr. Budi']);
        $p = Penelitian::create($extra + [
            'LBRPENGESAHANPROPOSAL_NAMAFILE' => 'lbrpengesahan_proposal_1.docx',
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1,
            'JUDULPENELITIAN' => 'Judul Uji',
            'PERMOHONANDIBUAT_KDPERSON' => $owner->kodeperson,
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ]);
        $this->actingAs($owner, 'web');

        return [$owner, $p];
    }

    private function fakePdf(int $pages): string
    {
        $f = new \FPDF;
        for ($i = 0; $i < $pages; $i++) {
            $f->AddPage();
        }

        return $f->Output('S');
    }

    private function fakeConverter(): void
    {
        $this->app->instance(DocxToPdfConverter::class, new class extends DocxToPdfConverter
        {
            public function convert(string $docx): string
            {
                $f = new \FPDF;
                $f->AddPage();
                $pdf = preg_replace('/\.docx$/', '.pdf', $docx);
                file_put_contents($pdf, $f->Output('S'));

                return $pdf;
            }
        });
    }

    public function test_lembar_cannot_be_downloaded_before_it_is_generated(): void
    {
        [, $p] = $this->ownerWithProposal(['LBRPENGESAHANPROPOSAL_NAMAFILE' => null]);

        $this->getJson("/pen/penelitian/{$p->id}/pengesahan?preview=1")->assertStatus(422);
    }

    public function test_pdf_gabung_places_lembar_after_cover(): void
    {
        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->put('proposal/init_1.pdf', $this->fakePdf(3));
        $this->fakeConverter();
        [, $p] = $this->ownerWithProposal(['FILE_DOKUMENPROPOSAL_INIT' => 'init_1.pdf']);

        $res = $this->get("/pen/penelitian/{$p->id}/pengesahan?format=pdf&gabung=1");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $tmp = tempnam(sys_get_temp_dir(), 'o_').'.pdf';
        file_put_contents($tmp, $res->streamedContent());
        $this->assertSame(4, (new Fpdi)->setSourceFile($tmp));
    }

    public function test_pdf_gabung_returns_404_when_proposal_file_missing(): void
    {
        Storage::fake('legacy_res');
        $this->fakeConverter();
        [, $p] = $this->ownerWithProposal();

        $this->get("/pen/penelitian/{$p->id}/pengesahan?format=pdf&gabung=1")->assertNotFound();
    }

    public function test_pdf_returns_503_when_libreoffice_unavailable(): void
    {
        $this->app->instance(DocxToPdfConverter::class, new DocxToPdfConverter('binary-tidak-ada-xyz'));
        [, $p] = $this->ownerWithProposal();

        $this->get("/pen/penelitian/{$p->id}/pengesahan?format=pdf")->assertStatus(503);
    }
}
