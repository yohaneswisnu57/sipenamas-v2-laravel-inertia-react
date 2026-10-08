<?php

namespace App\Http\Controllers\Api\V1\Dekan;

use App\Http\Controllers\Controller;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Daftar penelitian fakultas Dekan yang sudah LOLOS tetapi belum tuntas,
 * padanan dkn/myphp/penelitianbelumtuntas.php:
 * JENIS_PA = PENELITIAN, STATUSFINALAPPROVAL = LOLOS, dan
 * STATUSKETUNTASANPENELITIAN bukan TUNTAS maupun TUNTAS BERSYARAT.
 * `kdperiode` opsional (legacy menerima `ALL`).
 */
class MonitoringController extends Controller
{
    public function belumTuntas(Request $request)
    {
        $query = Penelitian::forDekan($request->user()->kodepersonAliases())
            ->where('JENIS_PA', 'PENELITIAN')
            ->where('STATUSFINALAPPROVAL', 'LOLOS')
            ->whereNotIn('STATUSKETUNTASANPENELITIAN', ['TUNTAS', 'TUNTAS BERSYARAT'])
            ->with(PenelitianResource::EAGER_RELATIONS);

        if ($request->filled('kdperiode') && $request->query('kdperiode') !== 'ALL') {
            $periode = Periode::where('KODEPERIODE', $request->query('kdperiode'))->firstOrFail();
            $query->where('PERIODEKEGIATAN_TAHUN', $periode->TAHUN);
        }

        $items = $query->orderByDesc('id')->get()->map(fn (Penelitian $penelitian) => array_merge(
            (new PenelitianResource($penelitian))->withoutPengesahan()->resolve($request),
            [
                'statusKetuntasan' => $penelitian->STATUSKETUNTASANPENELITIAN ?: '-',
                'isDisetujuiDekan' => (bool) $penelitian->ISDEKANAPPROVELAPORANAKHIR,
                'adaDokumenHasil' => filled($penelitian->FILE_DOKUMENHASILPENELITIAN),
            ]
        ));

        return ApiResponse::success($items);
    }
}
