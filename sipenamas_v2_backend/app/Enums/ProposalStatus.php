<?php

namespace App\Enums;

/**
 * Mirror persis dari STATUS_USULAN di sipenamas_v2_frontend/src/utils/constants.js.
 * Legacy menyebar status ke belasan kolom flag pada tabel `penelitian` /
 * `kegiatanabdimas` - nilai enum ini dihasilkan oleh
 * App\Domain\Proposal\ProposalStatusResolver, bukan dibaca langsung dari satu kolom.
 */
enum ProposalStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case DITOLAK_DEKAN = 'DITOLAK_DEKAN';
    case DISETUJUI_DEKAN = 'DISETUJUI_DEKAN';
    case PLOTTED = 'PLOTTED';
    case REVIEW = 'REVIEW';
    case REVISI = 'REVISI';
    case MENUNGGU_VERIFIKASI_REVISI = 'MENUNGGU_VERIFIKASI_REVISI';
    case FINAL_APPROVAL = 'FINAL_APPROVAL';
    case LOLOS = 'LOLOS';
    case TIDAK_LOLOS = 'TIDAK_LOLOS';
    case MONEV = 'MONEV';
    case LAPORAN_AKHIR = 'LAPORAN_AKHIR';
    case TUNTAS = 'TUNTAS';
}
