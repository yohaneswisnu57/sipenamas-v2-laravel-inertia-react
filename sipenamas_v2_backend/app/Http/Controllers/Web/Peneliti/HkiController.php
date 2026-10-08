<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\StoreHkiRequest;
use App\Http\Resources\HkiResource;
use App\Models\Hki;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HkiController extends Controller
{
    public function index(Request $request): Response
    {
        $list = Hki::forPeneliti($request->user()->kodepersonAliases())
            ->with('peserta.person.fakultas')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('pen/HkiPatenPage', [
            'hkiList' => HkiResource::collection($list)->resolve($request),
        ]);
    }

    public function store(StoreHkiRequest $request): RedirectResponse
    {
        $person = $request->user();
        $data = $request->validated();

        $hki = DB::transaction(function () use ($person, $data) {
            $hki = Hki::create([
                'JUDUL' => $data['judulCiptaan'],
                'JENISHKI' => $data['jenisCiptaan'],
                'STATUSHKI' => 'PEMERIKSAAN_SUBSTANTIF',
                'TGLDAFTAR' => now(),
                'VALID' => 1,
            ]);

            $hki->peserta()->create([
                'NIP' => $person->kodeperson,
                'NAMA' => $person->nama,
                'JNSPESERTA' => 'UTAMA',
            ]);

            foreach ($data['pesertaLain'] ?? [] as $peserta) {
                $hki->peserta()->create([
                    'NAMA' => $peserta['nama'],
                    'JNSPESERTA' => $peserta['jenisPeserta'] ?? 'ANGGOTA',
                ]);
            }

            return $hki;
        });

        return back()->with('success', 'Pendaftaran HKI/Paten berhasil dikirim ke LPPM');
    }
}
