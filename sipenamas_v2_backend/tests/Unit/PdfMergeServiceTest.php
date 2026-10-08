<?php

namespace Tests\Unit;

use App\Services\Proposal\PdfMergeService;
use FPDF;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;

class PdfMergeServiceTest extends TestCase
{
    private function pdf(int $pages): string
    {
        $f = new FPDF;
        for ($i = 1; $i <= $pages; $i++) {
            $f->AddPage();
            $f->SetFont('Arial', '', 12);
            $f->Cell(0, 10, "hal $i");
        }
        $path = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';
        file_put_contents($path, $f->Output('S'));

        return $path;
    }

    private function pages(string $path): int
    {
        return (new Fpdi)->setSourceFile($path);
    }

    public function test_lembar_is_inserted_after_proposal_cover(): void
    {
        $out = (new PdfMergeService)->sisipkanSetelahCover($this->pdf(4), $this->pdf(2));

        $this->assertSame(6, $this->pages($out));
    }

    public function test_order_is_cover_then_lembar_then_rest(): void
    {
        $out = (new PdfMergeService)->sisipkanSetelahCover($this->sizedPdf([100, 110, 120]), $this->sizedPdf([140]));

        $pdf = new Fpdi;
        $n = $pdf->setSourceFile($out);
        $widths = [];
        for ($i = 1; $i <= $n; $i++) {
            $widths[] = (int) round($pdf->getTemplateSize($pdf->importPage($i))['width']);
        }

        $this->assertSame([100, 140, 110, 120], $widths);
    }

    public function test_unreadable_compressed_pdf_without_ghostscript_fails_with_clear_error(): void
    {
        $legacy = getenv('LEGACY_SAMPLE_PDF') ?: 'C:/Users/wisnu/Documents/htdocs/sipenamas/appz/res/proposal/init_proposalpenelitian_44.pdf';
        if (! is_file($legacy)) {
            $this->markTestSkipped('Sampel PDF legacy tidak tersedia.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Ghostscript/');

        (new PdfMergeService('gs-tidak-ada-xyz'))->sisipkanSetelahCover($legacy, $this->pdf(1));
    }

    private function sizedPdf(array $widths): string
    {
        $f = new FPDF;
        foreach ($widths as $w) {
            $f->AddPage('P', [$w, 150]);
        }
        $path = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';
        file_put_contents($path, $f->Output('S'));

        return $path;
    }
}
