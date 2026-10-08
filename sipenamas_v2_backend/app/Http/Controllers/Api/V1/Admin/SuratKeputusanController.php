<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateSuratRequest;
use App\Models\Penelitian;
use App\Services\Proposal\SuratKeputusanService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Padanan adm/myphp/finalapproval.php legacy: proses massal ST/STPP/SPD.
 */
class SuratKeputusanController extends Controller
{
    public function __construct(private SuratKeputusanService $surat) {}

    /**
     * Legacy GENFILESURAT. Surat yang sudah FINAL tidak digenerate ulang
     * (maksud CEKLISTKEFINALAN legacy). Nomor bentrok ditolak kecuali admin
     * mengonfirmasi lanjut (`abaikanDuplikasi`), sama seperti dialog legacy.
     */
    public function generate(GenerateSuratRequest $request)
    {
        $ids = array_map('intval', $request->input('ids'));
        $penelitian = Penelitian::whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values();

        abort_if(
            $penelitian->contains(fn ($p) => $p->CETAKSURATTUGAS_STATUS === 'FINAL' || $p->CETAKSURATDANA_STATUS === 'FINAL'),
            422,
            'Maaf proses tidak dapat dilanjutkan, karena terdapat dokumen SURAT yg berstatus FINAL dalam daftar pilihan.'
        );

        $nomor = (int) $request->input('nomor');
        $jadwal = [];
        foreach ($penelitian as $p) {
            $jadwal[] = [$p, $nomor];
            $nomor += $this->surat->jumlahNomor($p);
        }

        $bentrok = $this->surat->nomorTerpakai(range((int) $request->input('nomor'), $nomor - 1), $ids);
        if ($bentrok !== [] && ! $request->boolean('abaikanDuplikasi')) {
            return ApiResponse::error(
                'DITEMUKAN DATA DENGAN NOMOR YG AKAN DUPLIKASI ('.implode(', ', $bentrok).').',
                409,
                ['nomorDuplikasi' => $bentrok]
            );
        }

        foreach ($jadwal as [$p, $n]) {
            $this->surat->generate($p, $n, $request->string('tanggal')->toString());
        }

        return ApiResponse::success(null, 'PROSES GENERATE SELESAI.');
    }

    public function setFinal(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'exists:penelitian,id']])['ids'];
        $penelitian = Penelitian::whereIn('id', $ids)->get();

        abort_if($penelitian->sum(fn ($p) => $this->surat->jumlahBelumFinal($p)) === 0, 422, 'Maaf semua dokumen sudah berstatus FINAL.');

        foreach ($penelitian as $p) {
            $this->surat->setFinal($p);
        }

        return ApiResponse::success(null, 'PROSES SELESAI.');
    }

    public function unduh(int $id, string $jenis)
    {
        abort_unless(in_array($jenis, SuratKeputusanService::JENIS, true), 404);
        $penelitian = Penelitian::findOrFail($id);
        $path = $this->surat->path($penelitian, $jenis);

        abort_if(! $path, 404, 'Surat belum digenerate');

        return response()->download($path, "{$jenis}_{$id}.docx");
    }
}
