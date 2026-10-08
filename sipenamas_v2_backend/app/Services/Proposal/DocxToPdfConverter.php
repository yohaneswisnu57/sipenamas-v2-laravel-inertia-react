<?php

namespace App\Services\Proposal;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class DocxToPdfConverter
{
    public function __construct(private ?string $binary = null)
    {
        $this->binary ??= config('services.libreoffice.bin', '/usr/bin/soffice');
    }

    public function convert(string $docx): string
    {
        $dir = dirname($docx);
        $tmpProfile = 'file://'.sys_get_temp_dir().'/lo_profile_'.uniqid();
        $process = new Process([$this->binary, '-env:UserInstallation='.$tmpProfile, '--headless', '--nologo', '--nofirststartwizard', '--convert-to', 'pdf', '--outdir', $dir, $docx]);
        $process->setTimeout(60);
        $process->setEnv(['HOME' => sys_get_temp_dir()]);

        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new RuntimeException('LibreOffice tidak tersedia: '.$e->getMessage(), 0, $e);
        }

        $pdf = preg_replace('/\.docx$/', '.pdf', $docx);

        // Bersihkan folder temporary profile LibreOffice
        $profileDir = str_replace('file://', '', $tmpProfile);
        if (is_dir($profileDir)) {
            File::deleteDirectory($profileDir);
        }

        if (! $process->isSuccessful() || ! is_file($pdf)) {
            Log::error('LibreOffice Error: '.$process->getErrorOutput());
            throw new RuntimeException('Konversi DOCX ke PDF gagal. '.$process->getErrorOutput());
        }

        return $pdf;
    }
}
