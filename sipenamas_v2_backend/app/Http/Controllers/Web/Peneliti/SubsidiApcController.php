<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\StoreSubsidiApcRequest;
use App\Http\Resources\SubsidiApcResource;
use App\Models\SubsidiApc;
use App\Support\MasterData\MasterDataOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubsidiApcController extends Controller
{
    public function index(Request $request, MasterDataOptions $options): Response
    {
        $list = SubsidiApc::forPeneliti($request->user()->kodepersonAliases())
            ->with('pengaju.fakultas', 'indexJurnal')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('pen/SubsidiApcPage', [
            'apcList' => SubsidiApcResource::collection($list)->resolve($request),
            'indexJurnalOptions' => $options->indexJurnal(apc: true),
        ]);
    }

    public function store(StoreSubsidiApcRequest $request): RedirectResponse
    {
        $person = $request->user();
        $data = $request->validated();

        $apc = SubsidiApc::create([
            'KDPERSONPENGAJU' => $person->kodeperson,
            'KDPRODI' => $person->kodeprodi,
            'TANGGALPENGAJUAN' => now(),
            'JUDULARTIKEL' => $data['judulArtikel'],
            'INFOJURNAL_NAMAJURNAL' => $data['namaJurnal'],
            'PUBLISHER' => $data['penerbit'] ?? null,
            'INFOJURNAL_TERINDEKDALAM' => $data['kategoriJurnal'],
            'NOMINALPENGAJUANAPC' => $data['nominalPengajuan'],
            'URL' => $data['urlArtikel'] ?? null,
            'DOI' => $data['doi'] ?? null,
            'ISPENGAJUANFINAL' => 1,
            'RES_STATUSAPC' => 'MENUNGGU_REVIEW_LPPM',
            'RES_NOMORAGENDA' => 'APC-'.now()->format('Ymd').'-'.$person->kodeperson,
        ]);

        return back()->with('success', 'Permohonan subsidi APC berhasil dikirim');
    }
}
