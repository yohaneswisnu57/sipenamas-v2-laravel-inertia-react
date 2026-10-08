<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Proposal\ProposalStatusResolver;
use App\Enums\JenisPa;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinalDecisionRequest;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Padanan adm/myphp/finalapproval.php legacy.
 */
class FinalApprovalController extends Controller
{
    /**
     * Legacy memfilter JENIS_PA dan PERIODEKEGIATAN_TAHUN dari combo periode
     * (default glbKDPERIODE = periode aktif; `*` = semua periode).
     */
    public function index(Request $request)
    {
        $proposals = Penelitian::with(PenelitianResource::EAGER_RELATIONS)
            ->where('JENIS_PA', JenisPa::fromRequest($request->input('jenis'))->value)
            ->forKdperiode($request->query('kdperiode'))
            ->orderByDesc('id')
            ->get();

        $ready = $proposals->filter(fn ($p) => in_array(
            app(ProposalStatusResolver::class)->resolve($p)->value,
            [ProposalStatus::REVIEW->value, ProposalStatus::REVISI->value, ProposalStatus::FINAL_APPROVAL->value, ProposalStatus::LOLOS->value,
                ProposalStatus::TIDAK_LOLOS->value, ProposalStatus::MONEV->value, ProposalStatus::LAPORAN_AKHIR->value, ProposalStatus::TUNTAS->value]
        ))->values();

        return ApiResponse::success(PenelitianResource::collection($ready));
    }

    public function decide(FinalDecisionRequest $request, Penelitian $penelitian)
    {
        $data = $request->validated();
        $lolos = $data['status'] === 'LOLOS';

        $penelitian->update([
            'STATUSFINALAPPROVAL' => $lolos ? 'LOLOS' : 'TIDAK LOLOS',
            'NOMINALDANA_FINAL' => $lolos ? $data['biayaDisetujui'] : null,
        ]);

        return ApiResponse::success(null, 'Keputusan final LPPM berhasil disimpan');
    }
}
