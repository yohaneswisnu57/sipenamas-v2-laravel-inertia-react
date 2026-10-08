<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Domain\Proposal\RevisiState;
use App\Enums\RevisiStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\SaveResponRevisiRequest;
use App\Http\Requests\Peneliti\UploadDokumenRevisiRequest;
use App\Models\Penelitian;
use App\Services\Proposal\KomentarRevisiService;
use App\Services\Proposal\RevisiCycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Padanan jendela revisi proposal legacy (pen/myphp/hasilreviewpenelitian.php):
 * ketua menanggapi tiap komentar revisi reviewer (UPDATERESPON), mengunggah
 * naskah revisi sebagai draft yang boleh diganti (IMPORTFELDOKUMENPROPOSAL),
 * melihatnya (CEKFELDOKUMENPROPOSAL), lalu menandainya final (UPDATEFINAL).
 * Setelah final, tanggapan dan naskah terkunci.
 */
class RevisiProposalController extends Controller
{
    public function __construct(private KomentarRevisiService $komentar) {}

    public function show(Request $request, int $id): Response
    {
        $penelitian = $this->ketua($request, $id);
        $state = RevisiState::of($penelitian);

        return Inertia::render('pen/RevisiProposalPage', ['revisi' => [
            'id' => $penelitian->id,
            'judul' => $penelitian->JUDULPENELITIAN,
            'komentar' => $this->komentar->thread($penelitian),
            'alasanTertutup' => $this->komentar->alasanTertutup($penelitian),
            'adaVerifikator' => $state !== null,
            'revisiStatus' => $state?->status->value,
            'isFinal' => (bool) $penelitian->ISDOKUMENPROPOSALREVISIFINAL,
            'adaDokumenRevisi' => filled($penelitian->FILE_DOKUMENPROPOSAL_REV),
            'tsUploadRevisi' => $penelitian->TS_UPLOADPROPOSALREVISI,
            'catatanVerifikator' => $state && in_array($state->status, [RevisiStatus::DISETUJUI, RevisiStatus::DITOLAK], true)
                ? $state->verifikator->REVISI_KOMENTAR
                : null,
        ]]);
    }

    public function saveRespon(SaveResponRevisiRequest $request, int $id, int $komentarId): RedirectResponse
    {
        $penelitian = $this->terbuka($request, $id);

        $this->komentar->komentar($penelitian, $komentarId)->update(['KOMENRESPON' => (string) $request->input('respon')]);

        return back()->with('success', 'Tanggapan disimpan');
    }

    /**
     * Unggah naskah revisi sebagai draft; unggahan berikutnya mengganti naskah
     * sebelumnya selama revisi belum final.
     */
    public function uploadDokumen(UploadDokumenRevisiRequest $request, int $id): RedirectResponse
    {
        $penelitian = $this->terbuka($request, $id);
        $lama = $penelitian->FILE_DOKUMENPROPOSAL_REV;

        $namaFile = sprintf('revisi_%d_%s.pdf', $penelitian->id, Str::random(8));
        Storage::disk('legacy_res')->putFileAs('proposal', $request->file('dokumenRevisi'), $namaFile);

        $penelitian->update([
            'FILE_DOKUMENPROPOSAL_REV' => $namaFile,
            'FILE_DOKUMENPROPOSAL_FINAL' => $namaFile,
            'TS_UPLOADPROPOSALREVISI' => now(),
        ]);

        if (filled($lama) && $lama !== $namaFile) {
            Storage::disk('legacy_res')->delete('proposal/'.$lama);
        }

        return back()->with('success', 'Draft naskah revisi disimpan');
    }

    public function lihatDokumen(Request $request, int $id): BinaryFileResponse
    {
        $penelitian = $this->ketua($request, $id);
        $relatif = 'proposal/'.$penelitian->FILE_DOKUMENPROPOSAL_REV;

        abort_if(blank($penelitian->FILE_DOKUMENPROPOSAL_REV) || ! Storage::disk('legacy_res')->exists($relatif), 404, 'Naskah revisi belum diunggah');

        return response()->file(Storage::disk('legacy_res')->path($relatif), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"Revisi_{$penelitian->id}.pdf\"",
        ]);
    }

    public function final(Request $request, int $id, RevisiCycleService $revisiCycle): RedirectResponse
    {
        $penelitian = $this->terbuka($request, $id);

        abort_if(blank($penelitian->FILE_DOKUMENPROPOSAL_REV), 422, 'Naskah revisi belum diunggah');

        // Requirement LPPM: pengajuan ulang hanya setelah admin menunjuk
        // verifikator revisi.
        try {
            $revisiCycle->finalkan($penelitian);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('success', 'Revisi proposal final dan dikirim ke reviewer');
    }

    private function ketua(Request $request, int $id): Penelitian
    {
        return Penelitian::whereIn('PERMOHONANDIBUAT_KDPERSON', $request->user()->kodepersonAliases())->findOrFail($id);
    }

    private function terbuka(Request $request, int $id): Penelitian
    {
        $penelitian = $this->ketua($request, $id);

        abort_if((bool) $penelitian->ISDOKUMENPROPOSALREVISIFINAL, 422, 'Revisi sudah dikirim dan tidak dapat diubah lagi');
        abort_if(($alasan = $this->komentar->alasanTertutup($penelitian)) !== null, 422, (string) $alasan);

        return $penelitian;
    }
}
