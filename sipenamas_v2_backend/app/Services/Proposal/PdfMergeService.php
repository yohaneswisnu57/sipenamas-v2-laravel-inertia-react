<?php

namespace App\Services\Proposal;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use Symfony\Component\Process\Process;

class PdfMergeService
{
    public function __construct(private ?string $ghostscript = null)
    {
        $this->ghostscript ??= app()->bound('config') ? config('services.ghostscript.bin', 'gs') : 'gs';
    }

    /** Halaman 1 proposal (cover), seluruh lembar pengesahan, lalu sisa proposal. */
    public function sisipkanSetelahCover(string $proposalPdf, string $lembarPdf): string
    {
        $proposalPdf = $this->bisaDibaca($proposalPdf);
        $lembarPdf = $this->bisaDibaca($lembarPdf);

        $pdf = new Fpdi;

        $total = $pdf->setSourceFile($proposalPdf);
        $this->import($pdf, 1);

        $lembarPages = $pdf->setSourceFile($lembarPdf);
        for ($i = 1; $i <= $lembarPages; $i++) {
            $this->import($pdf, $i);
        }

        $pdf->setSourceFile($proposalPdf);
        for ($i = 2; $i <= $total; $i++) {
            $this->import($pdf, $i);
        }

        $out = tempnam(sys_get_temp_dir(), 'gabung_').'.pdf';
        $pdf->Output('F', $out);

        return $out;
    }

    /** FPDI gratis tidak bisa membaca PDF >= 1.5 berkompresi; turunkan ke 1.4 lewat Ghostscript (seperti legacy). */
    private function bisaDibaca(string $pdf): string
    {
        try {
            (new Fpdi)->setSourceFile($pdf);

            return $pdf;
        } catch (CrossReferenceException) {
        }

        $out = tempnam(sys_get_temp_dir(), 'gs_').'.pdf';
        $proc = new Process([$this->ghostscript, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', '-dNOPAUSE', '-dQUIET', '-dBATCH', '-sOutputFile='.$out, $pdf]);
        $proc->setTimeout(120);

        try {
            $proc->run();
        } catch (\Throwable $e) {
            throw new RuntimeException('Ghostscript tidak tersedia untuk menurunkan versi PDF.', 0, $e);
        }
        if (! $proc->isSuccessful() || ! is_file($out)) {
            throw new RuntimeException('Ghostscript gagal menurunkan versi PDF.');
        }

        return $out;
    }

    private function import(Fpdi $pdf, int $page): void
    {
        $tpl = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);
    }
}
