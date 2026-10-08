<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\UpdateDanaPenyertaanRequest;
use App\Models\Penelitian;
use App\Services\Proposal\LembarPengesahanProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Padanan jendela "Lembar Pengesahan" proposal legacy
 * (permohonanpenelitian.php UPDATEDANAPENYERTAAN, GENFELLEMBARPENGESAHAN,
 * SETFINALFELLEMBARPENGESAHAN): ketua mengisi dana penyertaan, generate
 * dokumen, lalu set final. Lembar final jadi syarat unggah proposal.
 */
class PengesahanProposalController extends Controller
{
    public function updateDanaPenyertaan(UpdateDanaPenyertaanRequest $request, int $id): RedirectResponse
    {
        $penelitian = $this->belumFinal($request, $id);

        $penelitian->update([
            'LBRPENGESAHANPROPOSAL_DANAMITRA' => $request->input('danaMitra'),
            'LBRPENGESAHANPROPOSAL_DANAINKIND' => $request->input('danaInkind'),
        ]);

        return $this->hasil($penelitian, 'Dana Penyertaan sudah diupdate');
    }

    public function generate(Request $request, int $id, LembarPengesahanProposalService $lembar): RedirectResponse
    {
        $penelitian = $this->belumFinal($request, $id);

        abort_if(
            $penelitian->LBRPENGESAHANPROPOSAL_DANAMITRA === null || $penelitian->LBRPENGESAHANPROPOSAL_DANAINKIND === null,
            422,
            'Silahkan melengkapi isian Dana Penyertaan terlebih dahulu.'
        );

        $penelitian->update([
            'LBRPENGESAHANPROPOSAL_NAMAFILE' => $lembar->simpan($penelitian),
            'LBRPENGESAHANPROPOSAL_QRCODE' => uniqid().$penelitian->id.uniqid(),
        ]);

        return $this->hasil($penelitian, 'Lembar pengesahan proposal dibuat');
    }

    public function setFinal(Request $request, int $id, LembarPengesahanProposalService $lembar): RedirectResponse
    {
        $penelitian = $this->belumFinal($request, $id);

        abort_if(blank($penelitian->LBRPENGESAHANPROPOSAL_NAMAFILE), 422, 'Dokumen belum digenerate!');

        $penelitian->update(['LBRPENGESAHANPROPOSAL_ISFINAL' => 1]);
        $lembar->simpan($penelitian);

        return $this->hasil($penelitian, 'Lembar pengesahan berstatus final');
    }

    /**
     * Ketua usulan yang sudah diajukan, selama lembar belum final (legacy
     * menonaktifkan DANA PENYERTAAN, GENERATE, dan SET FINAL setelah final).
     */
    private function belumFinal(Request $request, int $id): Penelitian
    {
        $penelitian = Penelitian::whereIn('PERMOHONANDIBUAT_KDPERSON', $request->user()->kodepersonAliases())->findOrFail($id);

        abort_if(! $penelitian->ISPENGAJUANFINAL, 422, 'Usulan masih draft. Ajukan usulan terlebih dulu');
        abort_if((bool) $penelitian->LBRPENGESAHANPROPOSAL_ISFINAL, 422, 'Lembar pengesahan sudah final dan tidak dapat diubah lagi');

        return $penelitian;
    }

    private function hasil(Penelitian $penelitian, string $pesan): RedirectResponse
    {
        return back()->with('success', $pesan);
    }
}
