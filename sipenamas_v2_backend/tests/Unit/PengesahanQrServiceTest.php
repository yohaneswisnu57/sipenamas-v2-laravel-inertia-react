<?php

namespace Tests\Unit;

use App\Services\Proposal\PengesahanQrService;
use PHPUnit\Framework\TestCase;

/**
 * Pakai SvgWriter (bukan PngWriter) - lihat catatan di
 * App\Services\Proposal\PengesahanQrService: server ini tidak selalu
 * punya ekstensi GD yang dibutuhkan PngWriter.
 */
class PengesahanQrServiceTest extends TestCase
{
    public function test_generates_an_svg_data_uri(): void
    {
        $uri = (new PengesahanQrService)->generate('P00001', 'Dr. Budi Santoso');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
    }

    public function test_payload_contains_both_nik_and_nama(): void
    {
        $payload = (new PengesahanQrService)->payload('P00001', 'Dr. Budi Santoso');

        $this->assertStringContainsString('P00001', $payload);
        $this->assertStringContainsString('Dr. Budi Santoso', $payload);
    }

    public function test_different_inputs_produce_different_qr_codes(): void
    {
        $service = new PengesahanQrService;

        $uriA = $service->generate('P00001', 'Dr. Budi Santoso');
        $uriB = $service->generate('P00002', 'Dr. Siti Aminah');

        $this->assertNotSame($uriA, $uriB);
    }
}
