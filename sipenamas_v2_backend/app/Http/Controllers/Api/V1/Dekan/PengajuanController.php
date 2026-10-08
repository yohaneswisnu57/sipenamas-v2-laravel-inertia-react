<?php

namespace App\Http\Controllers\Api\V1\Dekan;

use App\Http\Controllers\Controller;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Daftar seluruh usulan penelitian fakultas Dekan (semua tahap), padanan
 * dkn/myphp/penelitianbelumtuntas.php legacy yang dibatasi lewat
 * fakultas.KDDEKAN; `kdperiode` opsional (legacy menerima `ALL`).
 */
class PengajuanController extends Controller
{
    public function index(Request $request)
    {
        $query = Penelitian::forDekan($request->user()->kodepersonAliases())
            ->where('JENIS_PA', 'PENELITIAN')
            ->where('ISPENGAJUANFINAL', 1)
            ->with(PenelitianResource::EAGER_RELATIONS);

        if ($request->filled('kdperiode') && $request->query('kdperiode') !== 'ALL') {
            $periode = Periode::where('KODEPERIODE', $request->query('kdperiode'))->firstOrFail();
            $query->where('PERIODEKEGIATAN_TAHUN', $periode->TAHUN);
        }

        return ApiResponse::success(PenelitianResource::collection($query->orderByDesc('id')->get()));
    }
}
