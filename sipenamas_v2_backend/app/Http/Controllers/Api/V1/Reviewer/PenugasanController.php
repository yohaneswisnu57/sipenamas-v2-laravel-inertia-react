<?php

namespace App\Http\Controllers\Api\V1\Reviewer;

use App\Enums\RevisiStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\ConfirmKesediaanRequest;
use App\Http\Requests\Reviewer\RevisiJudulRequest;
use App\Http\Requests\Reviewer\SubmitPenilaianRequest;
use App\Http\Requests\Reviewer\VerifikasiRevisiRequest;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Models\PenelitianPenilaianproposal;
use App\Models\PenelitianPenilaianproposalRevisi;
use App\Models\PenelitianReviewer;
use App\Services\Proposal\BorangPenilaianService;
use App\Services\Proposal\KomentarRevisiService;
use App\Services\Proposal\PenilaianAggregationService;
use App\Services\Proposal\RevisiCycleService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Padanan pen/myphp/penilaianreviewer.php legacy (sisi Reviewer). Reviewer
 * hanya melihat proposal yang ditugaskan ke dirinya lewat
 * `penelitian_reviewer.NIK` (lihat Penelitian::scopeForReviewer()), beda
 * dari peneliti yang melihat usulan sendiri.
 */
class PenugasanController extends Controller
{
    public function index(Request $request)
    {
        $aliases = $request->user()->kodepersonAliases();
        $proposals = Penelitian::forReviewer($aliases)
            ->where('STATUSPENUNJUKANREVIEWER', 'FINAL')
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->orderByDesc('id')
            ->get()
            ->map(function (Penelitian $p) use ($aliases) {
                $tugasSaya = $p->reviewers->first(fn (PenelitianReviewer $r) => in_array($r->NIK, $aliases, true));

                return (new PenelitianResource($p))->withoutPengesahan()->resolve() + [
                    'kesediaanSaya' => $tugasSaya?->statusKesediaan(),
                    'tglKesediaanSaya' => $tugasSaya?->TSAPPROVED,
                ];
            });

        return ApiResponse::success($proposals);
    }

    public function show(Request $request, int $id)
    {
        $penelitian = $this->assignedProposal($request, $id);
        $tugasSaya = $penelitian->reviewers->first(fn (PenelitianReviewer $r) => in_array($r->NIK, $request->user()->kodepersonAliases(), true));

        return ApiResponse::success((new PenelitianResource($penelitian))->resolve() + [
            'penilaianSaya' => $tugasSaya ? $this->penilaianSaya($tugasSaya) : null,
        ]);
    }

    public function confirmKesediaan(ConfirmKesediaanRequest $request, int $id)
    {
        $penugasan = $this->ownPenugasan($request, $id);

        abort_if($penugasan->statusKesediaan() !== 'MENUNGGU', 422, 'Kesediaan untuk penugasan ini sudah dikonfirmasi dan tidak dapat diubah.');

        // Menolak = ISAPPROVED 0 + TSAPPROVED terisi; alasan di KOMENTAR
        // (lihat PenelitianReviewer::statusKesediaan()).
        $penugasan->update([
            'ISAPPROVED' => $request->boolean('bersedia'),
            'TSAPPROVED' => now(),
            'KOMENTAR' => $request->boolean('bersedia') ? $penugasan->KOMENTAR : ($request->string('alasan')->toString() ?: null),
        ]);

        return ApiResponse::success(
            null,
            $request->boolean('bersedia')
                ? 'Konfirmasi kesediaan menilai berhasil dicatat'
                : 'Penolakan tugas berhasil dilaporkan ke LPPM'
        );
    }

    /**
     * Borang penilaian skim usulan ini beserta skor yang sudah tersimpan
     * (legacy penilaianproposaldetail.php LST/GENLIST).
     */
    public function borang(Request $request, int $id, BorangPenilaianService $borang)
    {
        return ApiResponse::success($borang->borang($this->ownPenugasan($request, $id)));
    }

    public function submitPenilaian(SubmitPenilaianRequest $request, int $id, BorangPenilaianService $borang, PenilaianAggregationService $agregasi, KomentarRevisiService $komentarRevisi)
    {
        $penugasan = $this->ownPenugasan($request, $id);

        abort_if($penugasan->STATUSPENILAIAN === 'FINAL', 422, 'Penilaian sudah dikirim dan tidak dapat diubah.');

        $status = $request->string('status')->toString() ?: 'FINAL';

        $totalSkor = $borang->simpan($penugasan, $request->validated('skor') ?? [], $status === 'FINAL');
        $komentar = array_values(array_filter(array_map('trim', $request->input('komentarRevisi', []))));
        $komentarRevisi->sinkron($penugasan, $komentar);

        // Legacy updateStatusnya: PERBAIKAN bila ada komentar revisi.
        $hasilPenilaian = match (true) {
            $totalSkor < BorangPenilaianService::SKOR_MINIMUM => 'TOLAK',
            $komentar !== [] || $request->string('rekomendasiStatus')->toString() === 'REVISI' => 'PERBAIKAN',
            default => 'LOLOS',
        };

        $penugasan->update([
            'STATUSPENILAIAN' => $status,
            'TOTALSKOR' => $totalSkor,
            'HASILPENILAIAN' => $hasilPenilaian,
            'KOMENTAR' => $request->string('catatan')->toString(),
            'REKOMENDASIBIAYA' => $request->input('rekomendasiDana'),
        ]);

        // DRAFT = simpan sementara, tidak dikunci dan tidak ikut rekap
        // (PenilaianAggregationService hanya memperhitungkan baris FINAL).
        if ($status === 'FINAL') {
            $agregasi->recompute($penugasan->penelitian);
        }

        return ApiResponse::success(
            ['totalSkor' => $totalSkor, 'hasilPenilaian' => $hasilPenilaian, 'status' => $status],
            $status === 'DRAFT'
                ? 'Draf penilaian proposal berhasil disimpan'
                : 'Penilaian proposal berhasil disimpan'
        );
    }

    /**
     * Reviewer mengganti judul proposal (legacy penilaianproposal.php task
     * REVISIJUDUL). Judul lama dicadangkan ke JUDULPENELITIAN_YGLAMA, tetapi
     * hanya bila kolom cadangan masih kosong - cadangan pertama dipertahankan.
     */
    public function revisiJudul(RevisiJudulRequest $request, int $id)
    {
        $penelitian = $this->ownPenugasan($request, $id)->penelitian;

        $judulLama = $penelitian->JUDULPENELITIAN_YGLAMA ?: $penelitian->JUDULPENELITIAN;

        $penelitian->update([
            'JUDULPENELITIAN' => $request->string('judulBaru')->toString(),
            'JUDULPENELITIAN_YGLAMA' => $judulLama,
        ]);

        return ApiResponse::success(
            ['judulBaru' => $penelitian->JUDULPENELITIAN, 'judulLama' => $judulLama],
            'Judul proposal berhasil direvisi'
        );
    }

    /**
     * Rubrik penilaian untuk reviewer - tombol "Panduan Penilaian" (legacy
     * penilaianproposal.php panduanPenilaian, berkas res/BUTIRPENILAIAN.docx).
     */
    public function panduanPenilaian()
    {
        $relatif = 'BUTIRPENILAIAN.docx';

        abort_unless(Storage::disk('legacy_res')->exists($relatif), 404, 'Berkas panduan penilaian belum tersedia');

        return response()->download(Storage::disk('legacy_res')->path($relatif), 'Panduan_Penilaian.docx');
    }

    /**
     * Naskah proposal yang dinilai reviewer (legacy penilaianproposal.php
     * cekFeldokumenproposal: FILE_DOKUMENPROPOSAL_INIT, naskah sebelum
     * revisi apa pun - dipakai juga oleh verifikator revisi untuk
     * membandingkan dengan naskah revisi).
     */
    public function dokumenProposal(Request $request, int $id)
    {
        return $this->kirimDokumen($this->ownPenugasan($request, $id)->penelitian, 'FILE_DOKUMENPROPOSAL_INIT', 'Proposal');
    }

    /**
     * Naskah revisi yang diunggah peneliti (legacy
     * penilaianproposalhasilrevisi.php cekFeldokumenproposalrev).
     */
    public function dokumenProposalRevisi(Request $request, int $id)
    {
        return $this->kirimDokumen($this->ownPenugasan($request, $id)->penelitian, 'FILE_DOKUMENPROPOSAL_REV', 'Revisi');
    }

    /**
     * Daftar proposal yang perlu diverifikasi hasil revisinya oleh
     * reviewer yang login - terpisah dari index() karena verifikator
     * revisi boleh reviewer siapa saja, tidak harus terdaftar di
     * penelitian_reviewer (Penelitian::forReviewer() tidak berlaku di sini).
     */
    public function revisiQueue(Request $request)
    {
        $penelitianIds = PenelitianReviewer::whereIn('NIK', $request->user()->kodepersonAliases())
            ->where('ISREVIEWERREVISI', 1)
            ->where(fn ($q) => $q->whereNull('STATUSPENILAIANREVISI')->orWhere('STATUSPENILAIANREVISI', '<>', 'FINAL'))
            ->whereHas('penelitian', fn ($q) => $q->where('ISDOKUMENPROPOSALREVISIFINAL', 1))
            ->pluck('IDPARENT');

        $proposals = Penelitian::whereIn('id', $penelitianIds)
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(PenelitianResource::collection($proposals));
    }

    /**
     * Komentar revisi seluruh reviewer beserta tanggapan peneliti (legacy
     * rev/myphp/penilaianproposalrevisirespon.php) - untuk reviewer usulan ini.
     */
    public function komentarRevisi(Request $request, int $id, KomentarRevisiService $komentarRevisi)
    {
        return ApiResponse::success($komentarRevisi->thread($this->ownPenugasan($request, $id)->penelitian));
    }

    /**
     * Verifikator revisi boleh reviewer siapa saja yang ditunjuk admin
     * LPPM (App\Http\Controllers\Api\V1\Admin\PlottingController::assignRevisiVerifikator),
     * TIDAK harus salah satu dari reviewer 1/2 - jadi tidak lewat
     * ownPenugasan()/Penelitian::forReviewer() seperti aksi reviewer lain.
     */
    public function verifikasiRevisi(VerifikasiRevisiRequest $request, int $id, RevisiCycleService $revisiCycle)
    {
        $revisiCycle->verify(
            $id,
            $request->user()->kodepersonAliases(),
            RevisiStatus::from($request->string('status')->toString()),
            $request->string('catatan')->toString()
        );

        return ApiResponse::success(null, 'Verifikasi hasil revisi berhasil disimpan');
    }

    private function assignedProposal(Request $request, int $id): Penelitian
    {
        return Penelitian::forReviewer($request->user()->kodepersonAliases())
            ->where('STATUSPENUNJUKANREVIEWER', 'FINAL')
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->findOrFail($id);
    }

    /**
     * Baris `penelitian_reviewer` milik reviewer yang login untuk proposal
     * ini - findOrFail di sini sekaligus jadi guard otorisasi data (404
     * kalau proposal ada tapi bukan ditugaskan ke reviewer yang login, ATAU
     * plotting masih DRAFT - reviewer belum diberi tahu, lihat
     * App\Services\Proposal\ReviewerAssignmentService).
     */
    /**
     * Legacy penilaianproposal: begitu STATUSPENILAIAN = FINAL, borang dan
     * tombol simpan di-disable - reviewer melihat hasilnya saja.
     *
     * @return array{isFinal: bool, totalSkor: ?int, hasil: ?string, catatan: ?string, rekomendasiDana: mixed, komentarRevisi: list<string>}
     */
    private function penilaianSaya(PenelitianReviewer $penugasan): array
    {
        $idPenilaian = PenelitianPenilaianproposal::where('IDPARENT', $penugasan->IDPARENT)->where('IDREVIEWER', $penugasan->id)->value('id');

        return [
            'isFinal' => $penugasan->STATUSPENILAIAN === 'FINAL',
            'totalSkor' => $penugasan->TOTALSKOR !== null ? (int) $penugasan->TOTALSKOR : null,
            'hasil' => $penugasan->HASILPENILAIAN,
            'catatan' => $penugasan->KOMENTAR,
            'rekomendasiDana' => $penugasan->REKOMENDASIBIAYA,
            'komentarRevisi' => $idPenilaian
                ? PenelitianPenilaianproposalRevisi::where('IDPARENT', $idPenilaian)->orderBy('id')->pluck('KOMENREVISI')->all()
                : [],
        ];
    }

    private function kirimDokumen(Penelitian $penelitian, string $kolom, string $label)
    {
        $namaFile = $penelitian->{$kolom};
        $relatif = 'proposal/'.$namaFile;

        abort_if(blank($namaFile) || ! Storage::disk('legacy_res')->exists($relatif), 404, "Berkas {$label} belum diunggah");

        return response()->file(Storage::disk('legacy_res')->path($relatif), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$label}_{$penelitian->id}.pdf\"",
        ]);
    }

    private function ownPenugasan(Request $request, int $penelitianId): PenelitianReviewer
    {
        return PenelitianReviewer::where('IDPARENT', $penelitianId)
            ->whereIn('NIK', $request->user()->kodepersonAliases())
            ->whereHas('penelitian', fn ($q) => $q->where('STATUSPENUNJUKANREVIEWER', 'FINAL'))
            ->firstOrFail();
    }
}
