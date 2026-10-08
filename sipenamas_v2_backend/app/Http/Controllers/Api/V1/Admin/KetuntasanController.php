<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateKetuntasanRequest;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Padanan adm/myphp/hasilpenelitian.php legacy (listData +
 * updateStatusketuntasan): admin LPPM memeriksa capaian luaran dan
 * menetapkan STATUSKETUNTASANPENELITIAN.
 */
class KetuntasanController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->filled('kdperiode')
            ? Periode::where('KODEPERIODE', $request->query('kdperiode'))->firstOrFail()
            : Periode::where('ISAKTIF', 1)->firstOrFail();

        $items = Penelitian::where('JENIS_PA', 'PENELITIAN')
            ->where('PERIODEKEGIATAN_TAHUN', $periode->TAHUN)
            ->where(fn ($q) => $q->whereNull('STATUSFINALAPPROVAL')->orWhere('STATUSFINALAPPROVAL', '<>', 'TIDAK LOLOS'))
            ->with(['skim', 'ketua', 'prodi', 'rencanaTarget'])
            ->orderBy('TGLMULAI')
            ->get()
            ->map(function (Penelitian $penelitian) {
                $target = $penelitian->rencanaTarget->where('ISCHKTARGET', 1);

                return [
                    'id' => $penelitian->id,
                    'judul' => $penelitian->JUDULPENELITIAN,
                    'skim' => $penelitian->skim?->NAMASKIM,
                    'ketua' => $penelitian->ketua?->NAMALENGKAP,
                    'prodi' => $penelitian->prodi?->NAMAPRODI,
                    'statusFinalApproval' => $penelitian->STATUSFINALAPPROVAL,
                    'isDisetujuiDekan' => (bool) $penelitian->ISDEKANAPPROVELAPORANAKHIR,
                    'jumlahTarget' => $target->count(),
                    'jumlahRealisasi' => $target->where('ISCHKREALISASI', 1)->count(),
                    'statusKetuntasan' => $penelitian->STATUSKETUNTASANPENELITIAN ?: '-',
                ];
            });

        return ApiResponse::success(['kdperiode' => $periode->KODEPERIODE, 'items' => $items]);
    }

    public function update(UpdateKetuntasanRequest $request, int $id)
    {
        Penelitian::where('JENIS_PA', 'PENELITIAN')->findOrFail($id)
            ->update(['STATUSKETUNTASANPENELITIAN' => $request->input('status')]);

        return ApiResponse::success(null, 'Ok, Status sudah diUpdate.');
    }
}
