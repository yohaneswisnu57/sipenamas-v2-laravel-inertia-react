<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\UpdateCapaianRequest;
use App\Http\Requests\Peneliti\UploadLuaranRequest;
use App\Models\Penelitian;
use App\Models\PenelitianRencanatarget;
use App\Services\Proposal\LaporanAkhirGate;
use App\Services\Proposal\LaporanAkhirPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Padanan pen/myphp/rencanatargethasilpenelitian.php legacy (jendela
 * "TARGET CAPAIAN & LUARAN"): ketua mengunggah bukti tiap target yang
 * dicentang dan menandai realisasinya. Hanya terbuka setelah lembar
 * pengesahan laporan akhir final. Isi jendela: LaporanAkhirPanel::capaian().
 */
class CapaianLuaranController extends Controller
{
    public function __construct(private LaporanAkhirGate $gate, private LaporanAkhirPanel $panel) {}

    /**
     * Padanan updateData + syarat di pen/app.js: realisasi butuh dokumen;
     * target ber-insentif butuh pengajuan insentif final lebih dulu.
     */
    public function update(UpdateCapaianRequest $request, int $id, int $targetId): RedirectResponse
    {
        $penelitian = $this->gate->ketuaDenganLembarFinal($request->user(), $id);
        $target = $this->target($penelitian, $targetId);
        $realisasi = $request->boolean('realisasi');

        abort_if(
            $realisasi && $target->ISADAINSENTIF && ! $this->panel->insentifFinal($penelitian),
            422,
            'UNTUK BISA REALISASI, SILAHKAN MELENGKAPI KELENGKAPAN DOKUMEN TERLEBIH DULU.'
        );
        abort_if($realisasi && blank($target->FILE_DOC), 422, 'UNTUK BISA REALISASI, SILAHKAN UPLOAD DOKUMEN TERLEBIH DULU.');

        $target->update([
            'ISCHKREALISASI' => $realisasi ? 1 : 0,
            'KETHASIL' => (string) $request->input('keterangan'),
            'STATUSTAYANG' => (string) $request->input('statusTayang'),
        ]);

        return back()->with('success', 'Capaian disimpan');
    }

    public function uploadDokumen(UploadLuaranRequest $request, int $id, int $targetId): RedirectResponse
    {
        $penelitian = $this->gate->ketuaDenganLembarFinal($request->user(), $id);
        $target = $this->target($penelitian, $targetId);
        $file = $request->file('dokumen');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $namaFile = "luaran_{$target->id}.{$ext}";

        $this->hapusFile($target);
        Storage::disk('legacy_res')->putFileAs(LaporanAkhirPanel::FOLDER_LUARAN, $file, $namaFile);
        $target->update(['FILE_DOC' => $namaFile, 'FILE_EXT' => strtoupper($ext)]);

        return back()->with('success', 'Upload selesai');
    }

    public function hapusDokumen(Request $request, int $id, int $targetId): RedirectResponse
    {
        $penelitian = $this->gate->ketuaDenganLembarFinal($request->user(), $id);
        $target = $this->target($penelitian, $targetId);

        $this->hapusFile($target);
        $target->update(['FILE_DOC' => '', 'FILE_EXT' => '']);

        return back()->with('success', 'Dokumen dihapus');
    }

    public function unduhDokumen(Request $request, int $id, int $targetId): BinaryFileResponse
    {
        $target = $this->target($this->gate->ketuaDenganLembarFinal($request->user(), $id), $targetId);
        $path = LaporanAkhirPanel::FOLDER_LUARAN.'/'.$target->FILE_DOC;

        abort_if(blank($target->FILE_DOC) || ! Storage::disk('legacy_res')->exists($path), 404, 'Dokumen belum diunggah');

        return response()->file(Storage::disk('legacy_res')->path($path));
    }

    private function target(Penelitian $penelitian, int $targetId): PenelitianRencanatarget
    {
        return $penelitian->rencanaTarget()->where('ISCHKTARGET', 1)->findOrFail($targetId);
    }

    private function hapusFile(PenelitianRencanatarget $target): void
    {
        if (filled($target->FILE_DOC)) {
            Storage::disk('legacy_res')->delete(LaporanAkhirPanel::FOLDER_LUARAN.'/'.$target->FILE_DOC);
        }
    }
}
