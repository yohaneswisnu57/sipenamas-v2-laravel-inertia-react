<?php

namespace App\Http\Controllers\Api\V1\Dekan;

use App\Domain\Proposal\ProposalStatusResolver;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dekan\ApproveProposalRequest;
use App\Http\Requests\Dekan\RejectProposalRequest;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Padanan dkn/myphp/approvalusulan.php legacy. Dekan menyetujui/menolak
 * usulan di fakultasnya sendiri (Penelitian::scopeForDekan(), join lewat
 * fakultas.KDDEKAN = KODEPERSON login) sebelum LPPM memplot reviewer.
 */
class ApprovalController extends Controller
{
    public function index(Request $request, ProposalStatusResolver $resolver)
    {
        // Legacy approvalpermohonan.php: hanya usulan dengan proposal final
        // (ISDOKUMENPROPOSALFINAL) dan semua anggota sudah setuju.
        $proposals = Penelitian::forDekan($request->user()->kodepersonAliases())
            ->where('ISDOKUMENPROPOSALFINAL', 1)
            ->where(fn ($q) => $q->whereNull('APPROVALPERMOHONAN_ISAPPROVEBYDEKAN')->orWhere('APPROVALPERMOHONAN_ISAPPROVEBYDEKAN', 0))
            ->whereDoesntHave('tim', fn ($q) => $q->whereNull('ISAPPROVED')->orWhere('ISAPPROVED', '<>', 1))
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Penelitian $p) => $resolver->resolve($p) === ProposalStatus::SUBMITTED)
            ->values();

        return ApiResponse::success(PenelitianResource::collection($proposals));
    }

    public function approve(ApproveProposalRequest $request, int $id)
    {
        $penelitian = $this->belumDisetujui($request, $id);

        // Catatan approve -> APPROVALPERMOHONAN_CATATANDEKAN, catatan
        // penolakan -> _MSG_PENOLAKANDEKAN (kolom legacy). Requirement LPPM: begitu Dekan ACC, ketua LPPM otomatis ikut ACC
        // (ISAPPROVEDBYLPPM) - tidak ada approval LPPM terpisah lagi di
        // titik ini.
        $penelitian->update([
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => true,
            'APPROVALPERMOHONAN_TIMESTAMP' => $penelitian->APPROVALPERMOHONAN_TIMESTAMP ?? now(),
            'APPROVALPERMOHONAN_KDDEKAN' => $request->user()->kodeperson,
            'APPROVALPERMOHONAN_CATATANDEKAN' => $request->string('catatan')->toString(),
            '_MSG_PENOLAKANDEKAN' => null,
            'ISAPPROVEDBYLPPM' => true,
        ]);

        return ApiResponse::success(null, 'Proposal disetujui tingkat Fakultas dan diteruskan ke LPPM');
    }

    public function reject(RejectProposalRequest $request, int $id)
    {
        $penelitian = $this->belumDisetujui($request, $id);

        $penelitian->update([
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => false,
            'APPROVALPERMOHONAN_TIMESTAMP' => null,
            'APPROVALPERMOHONAN_KDDEKAN' => $request->user()->kodeperson,
            'APPROVALPERMOHONAN_CATATANDEKAN' => null,
            'ISAPPROVEDBYLPPM' => false,
            '_MSG_PENOLAKANDEKAN' => $request->string('catatan')->toString(),
            'LBRPENGESAHANPROPOSAL_NAMAFILE' => null,
            'LBRPENGESAHANPROPOSAL_QRCODE' => null,
            'LBRPENGESAHANPROPOSAL_ISFINAL' => 0,
        ]);

        return ApiResponse::success(null, 'Proposal ditolak tingkat Fakultas');
    }

    /**
     * Legacy CEKFILEPENGESAHAN: jendela persetujuan Dekan menampilkan
     * FILE_DOKUMENPROPOSAL_FINAL sebelum Dekan memutuskan.
     */
    public function dokumenProposal(Request $request, int $id)
    {
        $penelitian = $this->ownedProposal($request, $id);
        $relatif = 'proposal/'.$penelitian->FILE_DOKUMENPROPOSAL_FINAL;

        abort_if(blank($penelitian->FILE_DOKUMENPROPOSAL_FINAL) || ! Storage::disk('legacy_res')->exists($relatif), 404, 'Berkas proposal belum diunggah');

        return response()->file(Storage::disk('legacy_res')->path($relatif), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Proposal_'.$penelitian->id.'.pdf"',
        ]);
    }

    /**
     * Legacy mengunci keputusan (centang setuju & tombol DEKAN MENYETUJUI
     * di-disable) begitu APPROVALPERMOHONAN_ISAPPROVEBYDEKAN = 1.
     */
    private function belumDisetujui(Request $request, int $id): Penelitian
    {
        $penelitian = $this->ownedProposal($request, $id);

        abort_if((bool) $penelitian->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN, 422, 'Usulan ini sudah disetujui Dekan dan keputusannya tidak dapat diubah.');

        return $penelitian;
    }

    private function ownedProposal(Request $request, int $id): Penelitian
    {
        return Penelitian::forDekan($request->user()->kodepersonAliases())->findOrFail($id);
    }
}
