<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\SaveKuesionerJawabanRequest;
use App\Models\Pengisiankuesionerpeneliti;
use App\Models\PengisiankuesionerpenelitiDetail;
use App\Models\Periode;
use App\Services\Proposal\LaporanAkhirGate;
use App\Services\Proposal\LaporanAkhirPanel;
use Illuminate\Http\RedirectResponse;

/**
 * Padanan pen/myphp/kuesionerpenelitiandetail.php legacy: kuesioner kepuasan
 * peneliti, satu pengisian per orang per periode (`JENIS_PA = PENELITIAN`).
 * Wajib lengkap sebelum KELENGKAPAN LAPORAN akhir bisa dibuka. Isi jendela:
 * LaporanAkhirPanel::kuesioner().
 */
class KuesionerPenelitianController extends Controller
{
    /**
     * Padanan updateRow + cekstatusOk: A..D -> skor 1..4, lalu hitung ulang ISDONE.
     */
    public function saveJawaban(SaveKuesionerJawabanRequest $request, int $detailId, LaporanAkhirGate $gate, LaporanAkhirPanel $panel): RedirectResponse
    {
        $detail = PengisiankuesionerpenelitiDetail::findOrFail($detailId);
        $pengisian = Pengisiankuesionerpeneliti::where('JENIS_PA', 'PENELITIAN')
            ->whereIn('NIK', $request->user()->kodepersonAliases())
            ->findOrFail($detail->IDPARENT);

        $jawab = $request->input('jawab');
        $detail->update(['JAWAB' => $jawab, 'SKOR' => $panel->skorKuesioner($jawab)]);

        $periode = Periode::where('KODEPERIODE', $pengisian->KDPERIODE)->firstOrFail();
        $pengisian->update(['ISDONE' => $gate->kuesionerSelesai($request->user(), $periode) ? 1 : 0]);

        return back()->with('success', 'Jawaban disimpan');
    }
}
