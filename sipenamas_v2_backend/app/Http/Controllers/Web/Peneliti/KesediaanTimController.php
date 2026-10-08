<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Models\PenelitianTim;
use App\Models\Periode;
use App\Services\Proposal\ProposalEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Padanan pen/myphp/statuskesediaantim.php: anggota dosen menyetujui
 * keanggotaannya di usulan orang lain. Legacy tidak punya aksi menolak.
 * Lihat docs/legacy-flow/penelitian.md §2.3.
 */
class KesediaanTimController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = PenelitianTim::with(['penelitian.skim', 'penelitian.ketua'])
            ->whereIn('NIKNIDN', $request->user()->kodepersonAliases())
            ->where('PERAN', 'ANGGOTA')
            ->where(fn ($q) => $q->whereNull('ISAPPROVED')->orWhere('ISAPPROVED', '<>', 1))
            ->whereHas('penelitian', fn ($q) => $q->where('ISPENGAJUANFINAL', 1))
            ->orderByDesc('id')
            ->get();

        return Inertia::render('pen/KesediaanTimPage', ['items' => $rows->map(fn (PenelitianTim $tim) => [
            'timId' => $tim->id,
            'penelitianId' => (string) $tim->IDPARENT,
            'judul' => $tim->penelitian->JUDULPENELITIAN,
            'skimNama' => $tim->penelitian->skim?->NAMASKIM,
            'ketuaNama' => $tim->penelitian->ketua?->NAMALENGKAP,
            'tugas' => $tim->URAIANTUGAS,
        ])->values()]);
    }

    public function setuju(Request $request, int $timId, ProposalEligibilityService $eligibility): RedirectResponse
    {
        $aliases = $request->user()->kodepersonAliases();

        $tim = PenelitianTim::with('penelitian.skim')
            ->whereIn('NIKNIDN', $aliases)
            ->where('PERAN', 'ANGGOTA')
            ->findOrFail($timId);

        abort_if((bool) $tim->ISAPPROVED, 422, 'Keanggotaan sudah disetujui sebelumnya');

        $penelitian = $tim->penelitian;
        $tahun = $penelitian->PERIODEKEGIATAN_TAHUN ?? Periode::aktif()->value('TAHUN');

        if ($penelitian->skim && $tahun) {
            $eligibility->assertKuotaAnggota($aliases, $penelitian->skim, (int) $tahun);
        }

        $tim->update(['ISAPPROVED' => 1, 'TSAPPROVED' => now()]);

        return back()->with('success', 'Keanggotaan penelitian disetujui');
    }
}
