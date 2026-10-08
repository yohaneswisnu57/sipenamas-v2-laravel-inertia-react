<?php

namespace App\Enums;

/**
 * Status siklus revisi, diturunkan dari kolom legacy oleh
 * App\Domain\Proposal\RevisiState. Siklus normal:
 * MENUNGGU_UPLOAD (verifikator ditunjuk, peneliti belum mengajukan ulang)
 * -> MENUNGGU_VERIFIKASI (peneliti sudah mengajukan ulang)
 * -> DISETUJUI | DITOLAK (verifikator sudah memutuskan).
 */
enum RevisiStatus: string
{
    case MENUNGGU_UPLOAD = 'MENUNGGU_UPLOAD';
    case MENUNGGU_VERIFIKASI = 'MENUNGGU_VERIFIKASI';
    case DISETUJUI = 'DISETUJUI';
    case DITOLAK = 'DITOLAK';
}
