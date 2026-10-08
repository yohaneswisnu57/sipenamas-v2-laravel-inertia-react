<?php

namespace App\Support\MasterData;

use App\Http\Resources\FakultasResource;
use App\Http\Resources\PeriodeResource;
use App\Http\Resources\SkimResource;
use App\Http\Resources\SumberDanaResource;
use App\Models\Fakultas;
use App\Models\IndexJurnal;
use App\Models\Periode;
use App\Models\SkimPenelitian;
use App\Models\SumberDana;

/**
 * Data referensi (dropdown) yang dipakai API master data lintas modul dan
 * props halaman Inertia. Satu sumber query supaya keduanya selalu sama.
 */
class MasterDataOptions
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function periode(): array
    {
        return PeriodeResource::collection(Periode::with('gelombang')->orderByDesc('TAHUN')->get())->resolve();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function periodeAktif(): ?array
    {
        $periode = Periode::aktif()->with('gelombang')->first();

        return $periode ? (new PeriodeResource($periode))->resolve() : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function skim(bool $abdimas): array
    {
        $query = SkimPenelitian::aktif();
        $abdimas ? $query->abdimas() : $query->penelitian();

        return SkimResource::collection($query->orderBy('URUTAN')->get())->resolve();
    }

    /**
     * @return array<int, array{kode: string, nama: string, adaInsentif: bool}>
     */
    public function indexJurnal(bool $apc = false): array
    {
        $query = IndexJurnal::orderBy('URUTAN');

        if ($apc) {
            $query->where('BOLEHAPC', 1);
        }

        return $query->get()->map(fn ($i) => [
            'kode' => $i->KODEINDEXJURNAL,
            'nama' => $i->NAMAINDEXJURNAL,
            'adaInsentif' => (bool) $i->ADAINSENTIF,
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fakultas(): array
    {
        return FakultasResource::collection(Fakultas::orderBy('URUTAN')->get())->resolve();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sumberDana(): array
    {
        return SumberDanaResource::collection(SumberDana::orderBy('URUTAN')->get())->resolve();
    }
}
