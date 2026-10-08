<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Domain\Proposal\ProposalStatusResolver;
use App\Enums\JenisPa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\StorePenelitianRequest;
use App\Http\Requests\Peneliti\UpdateRencanaTargetRequest;
use App\Http\Requests\Peneliti\UploadDokumenProposalRequest;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Services\Proposal\DocxToPdfConverter;
use App\Services\Proposal\LembarPengesahanProposalService;
use App\Services\Proposal\PdfMergeService;
use App\Services\Proposal\ProposalSubmissionService;
use App\Services\Proposal\SuratKeputusanService;
use App\Support\MasterData\MasterDataOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Padanan pen/myphp/permohonanpenelitian.php + penilaianproposalrevisi.php
 * legacy, digabung jadi
 * satu resource controller karena semuanya beroperasi di entitas yang sama
 * (satu baris `penelitian`) dan berbagi data scoping "usulan sendiri".
 */
class PenelitianController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('pen/DaftarPenelitianPage', [
            'proposals' => fn () => $this->daftar($request, JenisPa::PENELITIAN),
        ]);
    }

    public function indexAbdimas(Request $request): Response
    {
        return Inertia::render('pen/DaftarAbdimasPage', [
            'abdimasList' => fn () => $this->daftar($request, JenisPa::ABDIMAS),
        ]);
    }

    /**
     * Legacy permohonanpenelitian/permohonanabdimas.php LST: daftar dipisah
     * per JENIS_PA dan tahun memakai PERIODEKEGIATAN_TAHUN.
     *
     * @return array<int, array<string, mixed>>
     */
    private function daftar(Request $request, JenisPa $jenis): array
    {
        $query = Penelitian::forPeneliti($request->user()->kodepersonAliases())
            ->where('JENIS_PA', $jenis->value)
            ->with(PenelitianResource::EAGER_RELATIONS);

        if ($request->filled('tahun')) {
            $query->where('PERIODEKEGIATAN_TAHUN', $request->string('tahun'));
        }

        $proposals = $query->orderByDesc('id')->get();

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $proposals = $proposals->filter(fn ($p) => app(ProposalStatusResolver::class)->resolve($p)->value === $status)->values();
        }

        return PenelitianResource::collection($proposals)->resolve($request);
    }

    public function show(Request $request, int $id): Response
    {
        $penelitian = Penelitian::forPeneliti($request->user()->kodepersonAliases())
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->findOrFail($id);

        return Inertia::render('pen/DetailPenelitianPage', [
            'proposal' => (new PenelitianResource($penelitian))->resolve($request),
            'rencanaTarget' => $this->rencanaTargetList($penelitian),
        ]);
    }

    /**
     * Form usulan baru. `?jenis` tidak dipakai: jenis ditentukan route
     * (/pen/penelitian/baru atau /pen/abdimas/baru).
     */
    public function create(MasterDataOptions $options): Response
    {
        return $this->form($options, JenisPa::PENELITIAN);
    }

    public function createAbdimas(MasterDataOptions $options): Response
    {
        return $this->form($options, JenisPa::ABDIMAS);
    }

    public function edit(Request $request, int $id, MasterDataOptions $options): Response
    {
        return $this->form($options, JenisPa::PENELITIAN, $this->draftUntukForm($request, $id));
    }

    public function editAbdimas(Request $request, int $id, MasterDataOptions $options): Response
    {
        return $this->form($options, JenisPa::ABDIMAS, $this->draftUntukForm($request, $id));
    }

    public function unduhSuratTugas(Request $request, int $id, SuratKeputusanService $surat): BinaryFileResponse
    {
        $penelitian = Penelitian::whereHas('tim', fn ($q) => $q->whereIn('NIKNIDN', $request->user()->kodepersonAliases()))->findOrFail($id);
        $jenis = $penelitian->STATUSFINALAPPROVAL === 'LOLOS' ? 'ST' : 'STPP';
        $path = $surat->path($penelitian, $jenis);

        abort_if(! $path || $penelitian->CETAKSURATTUGAS_STATUS !== 'FINAL', 404, 'Surat tugas belum tersedia');

        return response()->download($path, "SuratTugas_{$penelitian->CETAKSURATTUGAS_NOMORSURAT}.docx");
    }

    /**
     * Padanan CEKFELLEMBARPENGESAHAN (pratinjau, `?preview=1`) dan
     * CEKPENGESAHANDISETUJUIDEKAN + DONLOTFELLEMBARPENGESAHAN (unduh hanya
     * setelah disetujui Dekan). Khusus ketua, setelah lembar di-generate.
     */
    public function unduhPengesahan(Request $request, int $id, LembarPengesahanProposalService $lembarPengesahan, DocxToPdfConverter $pdfConverter, PdfMergeService $merger): BinaryFileResponse
    {
        $penelitian = Penelitian::whereIn('PERMOHONANDIBUAT_KDPERSON', $request->user()->kodepersonAliases())
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->findOrFail($id);

        abort_if(blank($penelitian->LBRPENGESAHANPROPOSAL_NAMAFILE), 422, 'Dokumen belum digenerate!');
        abort_if(
            ! $request->boolean('preview') && ! $penelitian->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN,
            422,
            'DOKUMEN TIDAK BISA DIUNDUH: BELUM DISETUJUI DEKAN.'
        );

        $path = $lembarPengesahan->render($penelitian);

        if ($request->query('format') !== 'pdf') {
            return response()->download($path, 'LembarPengesahan.docx')->deleteFileAfterSend();
        }

        try {
            $lembar = $pdfConverter->convert($path);
        } catch (RuntimeException $e) {
            abort(503, 'Konversi PDF belum tersedia di server');
        }

        if (! $request->boolean('gabung')) {
            return response()->download($lembar, 'LembarPengesahan.pdf')->deleteFileAfterSend();
        }

        $nama = $penelitian->FILE_DOKUMENPROPOSAL_FINAL ?: $penelitian->FILE_DOKUMENPROPOSAL_INIT;
        $disk = Storage::disk('legacy_res');
        abort_if(! $nama || ! $disk->exists('proposal/'.$nama), 404, 'Berkas proposal belum diunggah');

        $sumber = tempnam(sys_get_temp_dir(), 'prop_').'.pdf';
        file_put_contents($sumber, $disk->get('proposal/'.$nama));
        try {
            $gabungan = $merger->sisipkanSetelahCover($sumber, $lembar);
        } catch (RuntimeException $e) {
            abort(503, $e->getMessage());
        } finally {
            @unlink($sumber);
        }

        return response()->download($gabungan, 'ProposalLengkap.pdf')->deleteFileAfterSend();
    }

    public function store(StorePenelitianRequest $request, ProposalSubmissionService $service): RedirectResponse
    {
        $penelitian = $service->submit($request->user(), $request->validated());

        return redirect($this->daftarUrl($penelitian))->with(
            'success',
            $penelitian->ISPENGAJUANFINAL
                ? 'Usulan tersimpan. Setelah semua anggota menyetujui, unggah dokumen proposal agar diteruskan ke Dekan'
                : 'Draft usulan tersimpan. Usulan belum diajukan'
        );
    }

    public function update(StorePenelitianRequest $request, int $id, ProposalSubmissionService $service): RedirectResponse
    {
        $penelitian = $this->editableByKetua($request, $id);

        abort_if($penelitian->ISPENGAJUANFINAL, 422, 'Usulan sudah diajukan dan tidak dapat diubah');

        $penelitian = $service->update($request->user(), $penelitian, $request->validated());

        return redirect($this->daftarUrl($penelitian))->with('success', 'Usulan diperbarui');
    }

    /**
     * Padanan permohonanpenelitian.php deleteData; legacy menolak di UI
     * bila bukan ketua atau sudah disetujui Dekan.
     */
    public function destroy(Request $request, int $id, ProposalSubmissionService $service): RedirectResponse
    {
        $penelitian = $this->editableByKetua($request, $id, 'Maaf, data pengajuan ini tidak bisa dihapus karena sudah disetujui oleh Dekan.');
        $daftar = $this->daftarUrl($penelitian);
        $service->delete($penelitian);

        return redirect($daftar)->with('success', 'Usulan dihapus');
    }

    /**
     * Padanan pen/myphp/dokumenproposalpenelitian.php (cekBolehuploadprop +
     * importFeldokumenproposal): proposal hanya boleh diunggah ketua setelah
     * usulan diajukan, semua anggota setuju, dan lembar pengesahan final.
     */
    public function uploadDokumenProposal(UploadDokumenProposalRequest $request, int $id): RedirectResponse
    {
        $penelitian = $this->editableByKetua($request, $id);

        abort_if(! $penelitian->ISPENGAJUANFINAL, 422, 'Usulan masih draft. Ajukan usulan terlebih dulu sebelum mengunggah dokumen proposal');

        abort_if(
            $penelitian->tim()->where(fn ($q) => $q->whereNull('ISAPPROVED')->orWhere('ISAPPROVED', '<>', 1))->exists(),
            422,
            'Dokumen proposal baru bisa diunggah setelah semua anggota menyetujui keanggotaan'
        );

        abort_if(! $penelitian->LBRPENGESAHANPROPOSAL_ISFINAL, 422, 'Dokumen proposal baru bisa diunggah setelah lembar pengesahan di-set final');
        abort_if($penelitian->ISDOKUMENPROPOSALFINAL, 422, 'Dokumen proposal sudah final dan tidak bisa diganti');

        $file = $request->file('dokumenProposal');
        $filename = sprintf('proposal_%d_%s.pdf', $penelitian->id, Str::random(8));

        if (! Storage::disk('legacy_res')->putFileAs('proposal', $file, $filename)) {
            abort(500, 'Gagal menyimpan file ke server (Permission Denied).');
        }

        $penelitian->update([
            'FILE_DOKUMENPROPOSAL_INIT' => $filename,
            'FILE_DOKUMENPROPOSAL_FINAL' => $filename,
        ]);

        return back()->with('success', 'Dokumen proposal berhasil diunggah. Set dokumen final agar diteruskan ke Dekan');
    }

    /**
     * Pratinjau naskah proposal yang sudah diunggah (masih draft maupun final)
     * agar peneliti bisa memeriksa sebelum set dokumen final.
     */
    public function lihatDokumenProposal(Request $request, int $id): BinaryFileResponse
    {
        $penelitian = $this->ownedProposal($request, $id);
        $relatif = 'proposal/'.$penelitian->FILE_DOKUMENPROPOSAL_FINAL;

        abort_if(blank($penelitian->FILE_DOKUMENPROPOSAL_FINAL) || ! Storage::disk('legacy_res')->exists($relatif), 404, 'Dokumen proposal belum diunggah');

        return response()->file(Storage::disk('legacy_res')->path($relatif), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"Proposal_{$penelitian->id}.pdf\"",
        ]);
    }

    /**
     * Padanan dokumenproposalpenelitian.php updateFinal: setelah final
     * proposal dan rencana target tidak bisa diubah, usulan masuk antrean Dekan.
     */
    public function finalDokumenProposal(Request $request, int $id): RedirectResponse
    {
        $penelitian = $this->editableByKetua($request, $id);

        abort_if(blank($penelitian->FILE_DOKUMENPROPOSAL_FINAL), 422, 'Dokumen proposal belum diunggah');

        $penelitian->update(['ISDOKUMENPROPOSALFINAL' => 1]);

        return back()->with('success', 'Dokumen proposal final dan diteruskan ke Dekan');
    }

    /**
     * Padanan rencanatargetpenelitian.php editData: ketua mencentang target
     * opsional; target wajib selalu tercentang.
     */
    public function updateRencanaTarget(UpdateRencanaTargetRequest $request, int $id): RedirectResponse
    {
        $penelitian = $this->editableByKetua($request, $id);
        abort_if($penelitian->ISDOKUMENPROPOSALFINAL, 422, 'Dokumen proposal sudah final, rencana target tidak bisa diubah');
        $dipilih = $request->validated('targetIds');

        $penelitian->rencanaTarget()->get()->each(fn ($target) => $target->update([
            'ISCHKTARGET' => ($target->ISWAJIB || in_array($target->id, $dipilih, true)) ? 1 : 0,
        ]));

        return back()->with('success', 'Rencana target luaran disimpan');
    }

    /**
     * @param  array<string, mixed>|null  $usulan
     */
    private function form(MasterDataOptions $options, JenisPa $jenis, ?array $usulan = null): Response
    {
        return Inertia::render('pen/FormUsulanPenelitianPage', [
            'isAbdimas' => $jenis === JenisPa::ABDIMAS,
            'usulan' => $usulan,
            'periodeAktif' => $options->periodeAktif(),
            'skimOptions' => $options->skim($jenis === JenisPa::ABDIMAS),
            'fakultasOptions' => $options->fakultas(),
            'sumberDanaOptions' => $options->sumberDana(),
        ]);
    }

    /**
     * Mode edit (padanan tombol Edit legacy): draft milik pengguna ini.
     *
     * @return array<string, mixed>
     */
    private function draftUntukForm(Request $request, int $id): array
    {
        $penelitian = Penelitian::forPeneliti($request->user()->kodepersonAliases())
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->findOrFail($id);

        return (new PenelitianResource($penelitian))->resolve($request);
    }

    private function daftarUrl(Penelitian $penelitian): string
    {
        return $penelitian->JENIS_PA === JenisPa::ABDIMAS->value ? '/pen/abdimas' : '/pen/penelitian';
    }

    private function ownedProposal(Request $request, int $id): Penelitian
    {
        return Penelitian::forPeneliti($request->user()->kodepersonAliases())->findOrFail($id);
    }

    /**
     * Usulan milik ketua yang masih boleh diubah (belum disetujui Dekan).
     */
    private function editableByKetua(Request $request, int $id, string $pesanDisetujui = 'Usulan sudah disetujui Dekan dan tidak dapat diubah'): Penelitian
    {
        $penelitian = Penelitian::whereIn('PERMOHONANDIBUAT_KDPERSON', $request->user()->kodepersonAliases())->findOrFail($id);

        abort_if($penelitian->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN, 422, $pesanDisetujui);

        return $penelitian;
    }

    /**
     * @return array<int, array{id: int, kategori: ?string, subkategori: ?string, indikator: ?string, isWajib: bool, isDipilih: bool}>
     */
    private function rencanaTargetList(Penelitian $penelitian): array
    {
        return $penelitian->rencanaTarget()->get()->map(fn ($target) => [
            'id' => $target->id,
            'kategori' => $target->KATEGORI,
            'subkategori' => $target->SUBKATEGORI,
            'indikator' => $target->INDIKATORNYA,
            'isWajib' => (bool) $target->ISWAJIB,
            'isDipilih' => (bool) $target->ISCHKTARGET,
        ])->all();
    }
}
