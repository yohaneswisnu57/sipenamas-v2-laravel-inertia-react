<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PenelitianResource;
use App\Http\Resources\PeriodeResource;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Support\ApiResponse;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $periode = Periode::aktif()->with('gelombang')->first();

        if (! $periode) {
            return ApiResponse::error('Tidak ada periode aktif saat ini.', 404);
        }

        $proposals = Penelitian::where('KDPERIODE', $periode->KODEPERIODE)
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->get();

        $resolved = $proposals->map(fn ($p) => (new PenelitianResource($p))->withoutPengesahan()->resolve());

        $totalDanaUsulan = $resolved->sum('biayaUsulan');
        $totalDanaDisetujui = $resolved->whereIn('status', [
            ProposalStatus::LOLOS->value, ProposalStatus::MONEV->value,
            ProposalStatus::LAPORAN_AKHIR->value, ProposalStatus::TUNTAS->value,
        ])->sum('biayaDisetujui');

        $periodeResource = (new PeriodeResource($periode))->resolve();

        return ApiResponse::success([
            'activePeriode' => $periodeResource,
            'totalProposal' => $resolved->count(),
            'totalDanaUsulan' => $totalDanaUsulan,
            'totalDanaDisetujui' => $totalDanaDisetujui,
            'paguTerserapPersen' => $periodeResource['totalPagu']
                ? (int) round($totalDanaDisetujui / $periodeResource['totalPagu'] * 100)
                : 0,
            'queueCounts' => [
                'menungguPlotting' => $resolved->whereIn('status', [ProposalStatus::DISETUJUI_DEKAN->value, ProposalStatus::PLOTTED->value])->count(),
                'dalamReview' => $resolved->whereIn('status', [ProposalStatus::REVIEW->value, ProposalStatus::REVISI->value])->count(),
                'sidangFinal' => $resolved->where('status', ProposalStatus::FINAL_APPROVAL->value)->count(),
            ],
            // Legacy tidak punya tabel log aktivitas generik untuk feed ini.
            'recentActivities' => [],
        ]);
    }
}
