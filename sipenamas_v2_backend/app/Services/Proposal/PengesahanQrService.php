<?php

namespace App\Services\Proposal;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR code untuk lembar pengesahan (berisi NIK + nama Dekan/Ketua) -
 * pakai SvgWriter (bukan PngWriter) karena ekstensi GD tidak selalu
 * tersedia di server, sementara SVG murni teks XML tanpa dependensi
 * image library.
 */
class PengesahanQrService
{
    /**
     * Isi teks yang di-encode ke dalam QR - dipisah dari generate() supaya
     * bisa diuji langsung (memverifikasi NIK dan nama sama-sama ikut) tanpa
     * perlu men-decode gambar QR-nya.
     */
    public function payload(string $nik, string $nama): string
    {
        return "NIK: {$nik}\nNama: {$nama}";
    }

    public function generate(string $nik, string $nama): string
    {
        $builder = new Builder(writer: new SvgWriter, size: 200, margin: 5);

        return $builder->build(data: $this->payload($nik, $nama))->getDataUri();
    }
}
