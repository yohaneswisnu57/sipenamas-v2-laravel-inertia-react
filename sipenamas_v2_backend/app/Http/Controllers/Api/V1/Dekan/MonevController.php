<?php

namespace App\Http\Controllers\Api\V1\Dekan;

use App\Enums\JenisPa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dekan\AssignMonevRequest;
use App\Models\Penelitian;
use App\Models\Person;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Padanan dkn/myphp/monevpenelitianpenunjukan.php dan monevabdimaspenunjukan.php
 * legacy: Dekan menunjuk reviewer monev (`penelitian.MONEVHASILBY`) untuk usulan
 * LOLOS yang sudah TUNTAS / TUNTAS BERSYARAT di fakultasnya. Jenis usulan dipilih
 * lewat `?jenis=PENELITIAN|ABDIMAS` (default PENELITIAN).
 */
class MonevController extends Controller
{
    public function index(Request $request)
    {
        $proposals = $this->monevQuery($request)
            ->when($request->filled('tahun'), fn (Builder $q) => $q->where('PERIODEKEGIATAN_TAHUN', $request->integer('tahun')))
            ->with(['skim', 'reviewerMonev.prodi', 'monevHasil'])
            ->orderByDesc('PERIODEKEGIATAN_TAHUN')
            ->orderBy('TGLMULAI')
            ->get()
            ->map(function (Penelitian $penelitian) {
                $reviewer = $penelitian->reviewerMonev;

                return [
                    'id' => $penelitian->id,
                    'judul' => $penelitian->JUDULPENELITIAN,
                    'tahun' => $penelitian->PERIODEKEGIATAN_TAHUN,
                    'skim' => $penelitian->skim?->NAMASKIM,
                    'jenis' => $penelitian->JENIS_PA,
                    'statusKetuntasan' => $penelitian->STATUSKETUNTASANPENELITIAN,
                    'reviewerMonev' => $reviewer ? [
                        'kodeperson' => $reviewer->KODEPERSON,
                        'nama' => $reviewer->NAMALENGKAP,
                        'prodi' => $reviewer->prodi?->NAMAPRODI,
                    ] : null,
                    'isFinal' => (bool) $penelitian->monevHasil->first()?->ISFINAL,
                ];
            });

        return ApiResponse::success($proposals);
    }

    /**
     * Padanan cmbreviewermonevpenelitian.php: dosen GJM (`person.ISGJM`)
     * sefakultas dengan prodi penelitian, bukan anggota tim penelitian itu.
     */
    public function kandidat(Request $request, int $id)
    {
        $penelitian = $this->monevQuery($request)->findOrFail($id);

        $kandidat = $this->kandidatQuery($penelitian)
            ->when($request->filled('q'), fn (Builder $q) => $q->where('NAMALENGKAP', 'like', '%'.$request->string('q').'%'))
            ->with('prodi')
            ->orderBy('NAMALENGKAP')
            ->get()
            ->map(fn (Person $person) => [
                'kodeperson' => $person->KODEPERSON,
                'nama' => $person->NAMALENGKAP,
                'prodi' => $person->prodi?->NAMAPRODI,
            ]);

        return ApiResponse::success($kandidat);
    }

    public function penunjukan(AssignMonevRequest $request, int $id)
    {
        $penelitian = $this->monevQuery($request)->findOrFail($id);
        $kodeperson = $request->input('kodeperson');

        abort_if(
            filled($kodeperson) && ! $this->kandidatQuery($penelitian)->where('KODEPERSON', $kodeperson)->exists(),
            422,
            'Reviewer monev harus dosen GJM sefakultas dan bukan anggota tim penelitian'
        );

        $penelitian->update(['MONEVHASILBY' => $kodeperson]);
        $penelitian->monevHasil()->firstOrCreate([]);

        return ApiResponse::success(null, 'Data penunjukan sudah disimpan');
    }

    /**
     * Legacy monevabdimaspenunjukan.php menulis `TUNTAS BESYARAT` (kurang huruf R),
     * sehingga abdimas TUNTAS BERSYARAT tidak pernah masuk antrean. Ejaan yang
     * benar dipakai untuk kedua jenis.
     */
    private function monevQuery(Request $request): Builder
    {
        return Penelitian::forDekan($request->user()->kodepersonAliases())
            ->where('JENIS_PA', JenisPa::fromRequest($request->input('jenis'))->value)
            ->where('STATUSFINALAPPROVAL', 'LOLOS')
            ->whereIn('STATUSKETUNTASANPENELITIAN', ['TUNTAS', 'TUNTAS BERSYARAT']);
    }

    private function kandidatQuery(Penelitian $penelitian): Builder
    {
        return Person::query()
            ->where('ISGJM', 1)
            ->whereIn('KDPRODI', function ($sub) use ($penelitian) {
                $sub->select('KODEPRODI')
                    ->from('prodi')
                    ->where('KDFAKULTAS', $penelitian->prodi?->KDFAKULTAS);
            })
            ->whereNotIn('KODEPERSON', function ($sub) use ($penelitian) {
                $sub->select('NIKNIDN')
                    ->from('penelitian_tim')
                    ->where('IDPARENT', $penelitian->id)
                    ->whereNotNull('NIKNIDN');
            });
    }
}
