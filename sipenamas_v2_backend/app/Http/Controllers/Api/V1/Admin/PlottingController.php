<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Proposal\ProposalStatusResolver;
use App\Enums\JenisPa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignReviewerRequest;
use App\Http\Requests\Admin\AssignRevisiVerifikatorRequest;
use App\Http\Requests\Admin\TambahReviewerRequest;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Services\Proposal\ReviewerAssignmentService;
use App\Services\Proposal\RevisiCycleService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Padanan adm/myphp/setreviewer.php legacy.
 */
class PlottingController extends Controller
{
    public function index(Request $request)
    {
        // Padanan setreviewer.php: hanya JENIS_PA PENELITIAN pada periode terpilih.
        $query = Penelitian::with(PenelitianResource::EAGER_RELATIONS)
            ->where('JENIS_PA', JenisPa::fromRequest($request->input('jenis'))->value)
            ->forKdperiode($request->query('kdperiode'));

        if ($request->filled('fakultas')) {
            $fakultas = $request->string('fakultas')->toString();
            $query->whereHas('prodi.fakultas', fn ($q) => $q->where('KODEFAKULTAS', $fakultas));
        }

        if ($request->filled('search')) {
            $q = $request->string('search')->toString();
            $query->where(fn ($sub) => $sub
                ->where('JUDULPENELITIAN', 'like', "%{$q}%")
                ->orWhere('PERMOHONANDIBUAT_KDPERSON', 'like', "%{$q}%")
                ->orWhereHas('ketua', fn ($kq) => $kq->where('NAMALENGKAP', 'like', "%{$q}%"))
                ->orWhereHas('prodi', fn ($pq) => $pq->where('NAMAPRODI', 'like', "%{$q}%")));
        }

        $proposals = $query->orderByDesc('id')->get();

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $proposals = $proposals->filter(fn ($p) => app(ProposalStatusResolver::class)->resolve($p)->value === $status)->values();
        }

        return ApiResponse::success(PenelitianResource::collection($proposals));
    }

    public function assignReviewer(AssignReviewerRequest $request, Penelitian $penelitian, ReviewerAssignmentService $service)
    {
        if (! $penelitian->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN) {
            return ApiResponse::error('Usulan belum disetujui Dekan - reviewer belum bisa ditugaskan', 422);
        }

        // Legacy setreviewer: tombol Simpan disembunyikan bila penunjukan sudah FINAL.
        if ($penelitian->STATUSPENUNJUKANREVIEWER === 'FINAL') {
            return ApiResponse::error('Plotting sudah final - reviewer tidak bisa diganti', 422);
        }

        $service->assign($penelitian, $request->string('reviewer1Id')->toString(), $request->string('reviewer2Id')->toString());

        return ApiResponse::success(null, 'Reviewer berhasil ditugaskan (draft, belum diberi tahu ke reviewer)');
    }

    public function finalize(Penelitian $penelitian, ReviewerAssignmentService $service)
    {
        try {
            $service->finalize($penelitian);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, 'Plotting difinalisasi - reviewer sekarang bisa mengonfirmasi kesediaan');
    }

    public function tambahReviewer(TambahReviewerRequest $request, Penelitian $penelitian, ReviewerAssignmentService $service)
    {
        $isPembanding = $request->boolean('isPembanding', false);

        try {
            $service->tambahReviewerKe3(
                $penelitian,
                $request->string('reviewerBaruId')->toString(),
                $isPembanding,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            null,
            $isPembanding ? 'Reviewer pembanding berhasil ditambahkan' : 'Reviewer ke-3 berhasil ditambahkan'
        );
    }

    /**
     * Menunjuk verifikator hasil revisi dari reviewer usulan ini (legacy
     * setStatusreviewerrevisi) - lihat RevisiCycleService::tunjukVerifikator().
     */
    public function assignRevisiVerifikator(AssignRevisiVerifikatorRequest $request, Penelitian $penelitian, RevisiCycleService $revisiCycle)
    {
        try {
            $revisiCycle->tunjukVerifikator($penelitian, $request->string('reviewerId')->toString());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, 'Verifikator revisi berhasil ditunjuk');
    }
}
