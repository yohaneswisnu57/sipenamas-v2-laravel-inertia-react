<?php

namespace Tests\Unit;

use App\Services\Proposal\PengesahanDocxService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class PengesahanDocxServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(ZipArchive::class) || ! extension_loaded('gd')) {
            $this->markTestSkipped('Butuh ekstensi zip dan gd.');
        }
    }

    private function build(?array $dekan, ?array $lppm = null, ?array $anggota = null): string
    {
        return (new PengesahanDocxService)->build([
            'lppm' => $lppm,
            'skim' => 'Penelitian Dosen Pemula',
            'judul' => 'Judul Uji',
            'bidang' => 'Informatika',
            'biaya' => '10,000,000',
            'ketua' => ['nik' => 'P00099', 'nama' => 'Dr. Budi'],
            'anggota' => $anggota ?? [['nik' => 'P00002', 'nama' => 'Ani'], ['nik' => 'P00003', 'nama' => 'Cici']],
            'dekan' => $dekan,
        ]);
    }

    private function xml(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    public function test_fills_placeholders_and_clones_each_anggota(): void
    {
        $xml = $this->xml($this->build(['nik' => 'P00001', 'nama' => 'Dr. Siti']));

        foreach (['JUDUL UJI', 'Dr. Budi', 'P00099', 'Ani', 'Cici', 'Dr. Siti', 'P00001', '10,000,000'] as $text) {
            $this->assertStringContainsStringIgnoringCase($text, $xml);
        }
        $this->assertStringNotContainsString('${', $xml);
    }

    public function test_embeds_qr_images_for_ketua_and_dekan(): void
    {
        $path = $this->build(['nik' => 'P00001', 'nama' => 'Dr. Siti']);
        $zip = new ZipArchive;
        $zip->open($path);
        $media = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $media += str_starts_with($zip->getNameIndex($i), 'word/media/') ? 1 : 0;
        }
        $zip->close();

        $this->assertGreaterThanOrEqual(2, $media);
    }

    public function test_dekan_fields_are_blank_before_dekan_decides(): void
    {
        $xml = $this->xml($this->build(null));

        $this->assertStringNotContainsString('${', $xml);
        $this->assertStringNotContainsString('Dr. Siti', $xml);
    }

    public function test_lppm_signer_is_filled_with_qr_when_provided(): void
    {
        $path = $this->build(null, ['nik' => 'P00777', 'nama' => 'Prof. Ketua LPPM']);
        $xml = $this->xml($path);
        $zip = new ZipArchive;
        $zip->open($path);
        $media = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $media += str_starts_with($zip->getNameIndex($i), 'word/media/') ? 1 : 0;
        }
        $zip->close();

        $this->assertStringContainsString('Prof. Ketua LPPM', $xml);
        $this->assertStringContainsString('P00777', $xml);
        $this->assertSame(2, $media); // ketua + lppm (dekan belum memutuskan)
        $this->assertStringNotContainsString('${', $xml);
    }

    public function test_proposal_without_anggota_leaves_no_raw_placeholders(): void
    {
        $xml = $this->xml($this->build(null, null, []));

        $this->assertStringNotContainsString('${', $xml);
        $this->assertStringNotContainsString('Anggota Peneliti', $xml);
    }
}
