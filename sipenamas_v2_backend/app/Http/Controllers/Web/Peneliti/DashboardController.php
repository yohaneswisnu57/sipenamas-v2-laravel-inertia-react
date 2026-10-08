<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Enums\JenisPa;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Services\Proposal\KomentarRevisiService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Statistik dan daftar usulan dimuat sesudah render pertama (deferred),
     * halaman menampilkan skeleton selama menunggu.
     */
    public function __invoke(Request $request, KomentarRevisiService $komentarRevisi): Response
    {
        $proposals = fn () => Penelitian::forPeneliti($request->user()->kodepersonAliases())
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->orderByDesc('id')
            ->get();

        return Inertia::render('pen/PenelitiDashboardPage', [
            'stats' => Inertia::defer(fn () => $this->stats($proposals(), $komentarRevisi)),
            'proposals' => Inertia::defer(fn () => PenelitianResource::collection(
                $proposals()->where('JENIS_PA', JenisPa::PENELITIAN->value)->values()
            )->resolve($request)),
            'templates' => TemplateController::katalog(),
        ]);
    }

    /**
     * @param  Collection<int, Penelitian>  $proposals
     * @return array<string, mixed>
     */
    private function stats($proposals, KomentarRevisiService $komentarRevisi): array
    {
        $resolved = $proposals->map(fn ($p) => (new PenelitianResource($p))->withoutPengesahan()->resolve());

        $active = $resolved->reject(fn ($p) => in_array($p['status'], [ProposalStatus::TUNTAS->value, ProposalStatus::TIDAK_LOLOS->value]));
        $totalDana = $resolved->sum('biayaDisetujui');

        // Tindakan peneliti: unggah revisi (legacy hasilreviewpenelitian, khusus
        // ketua). Monev diisi reviewer monev, bukan peneliti, jadi tidak di sini.
        $pendingAction = $resolved->first(fn ($p) => $p['status'] === ProposalStatus::REVISI->value && $p['isKetua']);

        return [
            'totalUsulan' => $resolved->count(),
            'usulanAktif' => $active->count(),
            'totalDanaDisetujui' => $totalDana,
            'pendingAction' => $pendingAction,
            'batasRevisi' => $pendingAction ? $komentarRevisi->batasRevisi() : null,
            'recentProposals' => $resolved->take(5)->values(),
        ];
    }
}
