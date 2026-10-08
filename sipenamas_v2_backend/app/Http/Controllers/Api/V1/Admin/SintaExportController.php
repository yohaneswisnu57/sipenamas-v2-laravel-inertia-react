<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Support\ApiResponse;

/**
 * Padanan adm/myphp/finalapproval_sinta_xls.php legacy (versi JSON;
 * generate .xlsx sungguhan menyusul di Fase 4 bersama fitur dokumen lain).
 */
class SintaExportController extends Controller
{
    public function __invoke()
    {
        $proposals = Penelitian::with(PenelitianResource::EAGER_RELATIONS)
            ->orderBy('id')
            ->get();

        $rows = $proposals->values()->map(function (Penelitian $p, int $index) {
            $resolved = (new PenelitianResource($p))->withoutPengesahan()->resolve();
            $didanai = in_array($resolved['status'], [
                ProposalStatus::LOLOS->value, ProposalStatus::MONEV->value,
                ProposalStatus::LAPORAN_AKHIR->value, ProposalStatus::TUNTAS->value,
            ]);

            return [
                'no' => $index + 1,
                'kode_pt' => '071015',
                'nama_pt' => 'Universitas Katolik Widya Mandala Surabaya',
                'tahun' => $resolved['tahun'],
                'nidn_ketua' => $resolved['ketuaNpp'],
                'nama_ketua' => $resolved['ketuaNama'],
                'judul' => $resolved['judul'],
                'bidang_fokus' => $resolved['bidangFokus'],
                'skema' => $resolved['skimNama'],
                'lama_kegiatan' => '1 Tahun',
                'dana_usulan' => $resolved['biayaUsulan'],
                'dana_disetujui' => $resolved['biayaDisetujui'] ?? 0,
                'target_tkt' => '4-6',
                'status' => $didanai ? 'Didanai' : 'Seleksi',
                'nomor_sk' => $resolved['suratTugas']['isFinal'] ? $resolved['suratTugas']['nomor'] : '-',
            ];
        });

        return ApiResponse::success($rows);
    }
}
