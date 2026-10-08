<?php

namespace Tests\Feature;

use App\Models\Penelitian;
use App\Services\Proposal\DocxToPdfConverter;
use App\Services\Proposal\SuratKeputusanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman verifikasi QR surat - padanan appz/dox/index.php legacy.
 */
class DokumenPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('legacy_res');
        Storage::disk('legacy_res')->put('proposal/surattugas_1.docx', 'docx surat tugas');
        Storage::disk('legacy_res')->put('proposal/suratdana_1.docx', 'docx surat dana');

        Penelitian::create([
            'JUDULPENELITIAN' => 'Penelitian Lolos',
            'CETAKSURATTUGAS_NAMAFILE' => 'surattugas_1.docx',
            'CETAKSURATTUGAS_QRCODE' => 'kodest123',
            'CETAKSURATDANA_NAMAFILE' => 'suratdana_1.docx',
            'CETAKSURATDANA_QRCODE' => 'kodespd456',
        ]);
    }

    private function fakeConverter(): void
    {
        $this->app->instance(DocxToPdfConverter::class, new class extends DocxToPdfConverter
        {
            public function convert(string $docx): string
            {
                $pdf = preg_replace('/\.docx$/', '.pdf', $docx);
                file_put_contents($pdf, '%PDF-1.4 '.file_get_contents($docx));

                return $pdf;
            }
        });
    }

    public function test_anyone_can_open_the_document_of_a_valid_qr_code_without_logging_in(): void
    {
        $this->fakeConverter();

        $response = $this->get('/api/v1/dox/ST/kodest123')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('docx surat tugas', $response->streamedContent());
        $this->assertSame(['proposal/suratdana_1.docx', 'proposal/surattugas_1.docx'], Storage::disk('legacy_res')->allFiles());
    }

    public function test_spd_code_opens_the_surat_pencairan_dana(): void
    {
        $this->fakeConverter();

        $response = $this->get('/api/v1/dox/SPD/kodespd456')->assertOk();

        $this->assertStringContainsString('docx surat dana', $response->streamedContent());
    }

    public function test_a_code_under_the_wrong_jenis_is_not_found(): void
    {
        $this->get('/api/v1/dox/ST/kodespd456')
            ->assertNotFound()
            ->assertSeeText('KODE SALAH ATAU DOKUMEN TIDAK DITEMUKAN.');
    }

    public function test_unknown_code_or_jenis_is_not_found(): void
    {
        $this->get('/api/v1/dox/ST/tidakada')->assertNotFound()->assertSeeText('KODE SALAH ATAU DOKUMEN TIDAK DITEMUKAN.');
        $this->get('/api/v1/dox/XYZ/kodest123')->assertNotFound();
    }

    public function test_missing_file_is_not_found(): void
    {
        Storage::disk('legacy_res')->delete('proposal/surattugas_1.docx');

        $this->get('/api/v1/dox/ST/kodest123')->assertNotFound();
    }

    public function test_returns_503_when_the_document_cannot_be_converted(): void
    {
        $this->app->instance(DocxToPdfConverter::class, new DocxToPdfConverter('binary-tidak-ada-xyz'));

        $this->get('/api/v1/dox/ST/kodest123')->assertStatus(503);
    }

    public function test_qr_points_to_the_configured_verification_page(): void
    {
        config(['services.dox.url' => 'https://sipenamas.contoh.ac.id/dox/']);

        $this->assertSame(
            'https://sipenamas.contoh.ac.id/dox/SPD/kodespd456',
            app(SuratKeputusanService::class)->urlVerifikasi('SPD', 'kodespd456')
        );
    }
}
