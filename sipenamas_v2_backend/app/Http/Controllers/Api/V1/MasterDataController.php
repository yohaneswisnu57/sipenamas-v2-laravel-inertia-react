<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\JenisPa;
use App\Http\Controllers\Controller;
use App\Http\Resources\DosenOptionResource;
use App\Http\Resources\MahasiswaResource;
use App\Http\Resources\ProdiResource;
use App\Http\Resources\ReviewerOptionResource;
use App\Models\Mahasiswa;
use App\Models\Penelitian;
use App\Models\Person;
use App\Models\Prodi;
use App\Support\ApiResponse;
use App\Support\MasterData\MasterDataOptions;
use Illuminate\Http\Request;

/**
 * Endpoint referensi read-only yang dipakai lintas modul (dropdown form
 * usulan, filter dashboard, dst) - padanan tabel master legacy.
 */
class MasterDataController extends Controller
{
    public function periode(MasterDataOptions $options)
    {
        return ApiResponse::success($options->periode());
    }

    public function periodeAktif(MasterDataOptions $options)
    {
        $periode = $options->periodeAktif();

        return $periode
            ? ApiResponse::success($periode)
            : ApiResponse::error('Tidak ada periode aktif saat ini.', 404);
    }

    public function skim(Request $request, MasterDataOptions $options)
    {
        return ApiResponse::success($options->skim($request->boolean('abdimas')));
    }

    /**
     * Legacy cmbindexjurnal.php: pilihan "Terindeks Dalam" insentif jurnal,
     * urut URUTAN. `?apc=1` = cmbindexjurnalapc.php (hanya BOLEHAPC = 1).
     */
    public function indexJurnal(Request $request, MasterDataOptions $options)
    {
        return ApiResponse::success($options->indexJurnal($request->boolean('apc')));
    }

    public function fakultas(MasterDataOptions $options)
    {
        return ApiResponse::success($options->fakultas());
    }

    public function prodi(Request $request)
    {
        $query = Prodi::query();

        if ($request->filled('fakultas')) {
            $query->where('KDFAKULTAS', $request->string('fakultas'));
        }

        return ApiResponse::success(ProdiResource::collection($query->orderBy('NAMAPRODI')->get()));
    }

    public function sumberDana(MasterDataOptions $options)
    {
        return ApiResponse::success($options->sumberDana());
    }

    public function dosen(Request $request)
    {
        $q = $request->string('q')->toString();

        $dosen = Person::with('prodi')
            ->where(fn ($query) => $query->where('ISSUPERUSER', false)->orWhereNull('ISSUPERUSER'))
            ->where(fn ($query) => $query->where('ISEXTERNAL', false)->orWhereNull('ISEXTERNAL'))
            ->where('KODEPERSON', 'not like', 'XADM%')
            ->where('KODEPERSON', 'not like', 'superadmin%')
            ->where(function ($query) {
                $query->where('STATUSNYA', 'A')
                    ->orWhere('ISPENELITI', 1)
                    ->orWhereNull('STATUSNYA');
            })
            ->where('STATUSNYA', '!=', 'T')
            ->when($q, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('NAMALENGKAP', 'like', "%{$q}%")
                ->orWhere('KODEPERSON', 'like', "%{$q}%")
                ->orWhere('NIDN', 'like', "%{$q}%")))
            ->orderBy('NAMALENGKAP')
            ->limit(20)
            ->get();

        return ApiResponse::success(DosenOptionResource::collection($dosen));
    }

    /**
     * Padanan adm/myphp/cmbreviewer.php: dengan `idpen`, hanya reviewer
     * sesuai jenis usulan (penelitian/abdimas) dan tanpa ketua maupun
     * anggota tim usulan itu (konflik kepentingan).
     */
    public function reviewer(Request $request)
    {
        $q = $request->string('q')->toString();
        $penelitian = $request->filled('idpen') ? Penelitian::find($request->integer('idpen')) : null;

        $reviewer = Person::with('prodi')
            ->when(
                $penelitian,
                fn ($query) => $query
                    ->where($penelitian->JENIS_PA === JenisPa::ABDIMAS->value ? 'ISREVIEWERABDIMAS' : 'ISREVIEWERPENELITIAN', 1)
                    ->whereNotIn('KODEPERSON', array_filter([
                        $penelitian->PERMOHONANDIBUAT_KDPERSON,
                        ...$penelitian->tim()->pluck('NIKNIDN')->all(),
                    ])),
                fn ($query) => $query->where(fn ($sub) => $sub->where('ISREVIEWERPENELITIAN', 1)->orWhere('ISREVIEWERABDIMAS', 1)),
            )
            ->when($q, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('NAMALENGKAP', 'like', "%{$q}%")
                ->orWhere('KODEPERSON', 'like', "%{$q}%")))
            ->orderBy('NAMALENGKAP')
            ->limit(30)
            ->get();

        return ApiResponse::success(ReviewerOptionResource::collection($reviewer));
    }

    public function mahasiswa(Request $request)
    {
        $q = $request->string('q')->toString();

        $mahasiswa = Mahasiswa::when($q, fn ($query) => $query->where(fn ($sub) => $sub
            ->where('NAMAMAHASISWA', 'like', "%{$q}%")
            ->orWhere('NIM', 'like', "%{$q}%")))
            ->limit(20)
            ->get();

        return ApiResponse::success(MahasiswaResource::collection($mahasiswa));
    }
}
