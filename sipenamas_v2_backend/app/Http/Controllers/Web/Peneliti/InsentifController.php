<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\StoreInsentifRequest;
use App\Http\Resources\InsentifResource;
use App\Models\IndexJurnal;
use App\Models\Insentif;
use App\Support\MasterData\MasterDataOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsentifController extends Controller
{
    public function index(Request $request, MasterDataOptions $options): Response
    {
        $list = Insentif::forPeneliti($request->user()->kodepersonAliases())
            ->with('pengaju.fakultas', 'indexJurnal')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('pen/InsentifJurnalPage', [
            'insentifList' => InsentifResource::collection($list)->resolve($request),
            'indexJurnalOptions' => $options->indexJurnal(),
        ]);
    }

    public function store(StoreInsentifRequest $request): RedirectResponse
    {
        $person = $request->user();
        $data = $request->validated();
        // Legacy GETJENISPUBLIKASI: index jurnal menentukan jenis publikasi
        // dan apakah insentif boleh diajukan (ADAINSENTIF).
        $index = IndexJurnal::where('KODEINDEXJURNAL', $data['tingkatJurnal'])->first();

        $insentif = Insentif::create([
            'KDPERSONPENGAJU' => $person->kodeperson,
            'KDPRODI' => $person->kodeprodi,
            'IDPENELITIANREFF' => $data['idPenelitianReff'] ?? null,
            'JENISREFF' => $data['idPenelitianReff'] ?? null ? 'PENELITIAN' : null,
            'TANGGALPENGAJUAN' => now(),
            'JUDULARTIKEL' => $data['judulArtikel'],
            'INFOJURNAL_NAMAJURNAL' => $data['namaJurnal'],
            'INFOJURNAL_TERINDEKDALAM' => $data['tingkatJurnal'],
            'KDJENISPUBLIKASI' => $index?->KDJENISPUBLIKASI,
            'TAHUN' => $data['tahunTerbit'],
            'VOLUME' => $data['volume'] ?? null,
            'NOMOR' => $data['nomor'] ?? null,
            'URL' => $data['urlArtikel'] ?? null,
            'DOI' => $data['doi'] ?? null,
            'ISPENGAJUANFINAL' => 1,
            'ISMENGAJUKANINSENTIF' => $index?->ADAINSENTIF ? 1 : 0,
            'RES_STATUSINSENTIF' => 'VERIFIKASI_LPPM',
            '_STATUSINSENTIF' => 'PROSES',
            'RES_NOMORAGENDA' => 'INS-'.now()->format('Ymd').'-'.$person->kodeperson,
        ]);

        return back()->with('success', 'Pengajuan insentif publikasi berhasil disimpan');
    }
}
