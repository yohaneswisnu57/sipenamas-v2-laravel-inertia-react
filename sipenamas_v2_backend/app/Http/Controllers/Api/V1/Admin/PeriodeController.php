<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePeriodeRequest;
use App\Http\Requests\Admin\UpdatePeriodeRequest;
use App\Http\Resources\PeriodeResource;
use App\Models\Periode;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class PeriodeController extends Controller
{
    public function index()
    {
        $list = Periode::with('gelombang')->orderByDesc('TAHUN')->get();

        return ApiResponse::success(PeriodeResource::collection($list));
    }

    public function store(StorePeriodeRequest $request)
    {
        $data = $request->validated();

        $periode = DB::transaction(function () use ($data) {
            if (! empty($data['isaktif'])) {
                Periode::query()->update(['ISAKTIF' => 0]);
            }

            $periode = Periode::create([
                'KODEPERIODE' => $data['kodeperiode'],
                'TAHUN' => $data['tahun'],
                'DESKRIPSI' => $data['nama'],
                'ISAKTIF' => ! empty($data['isaktif']) ? 1 : 0,
                'TGLBEGIN' => $data['tglBukaUsulan'],
                'TGLEND' => $data['tglTutupUsulan'],
                'TGLPELAKSANAANBEGIN' => $data['tglPelaksanaanMulai'] ?? null,
                'TGLPELAKSANAANEND' => $data['tglPelaksanaanSelesai'] ?? null,
            ]);

            $periode->gelombang()->create([
                'GELOMBANG' => '1',
                'GELAKTIF' => 1,
                ...$this->jadwalGelombang($data),
            ]);

            return $periode;
        });

        return ApiResponse::success(
            new PeriodeResource($periode->load('gelombang')),
            'Periode berhasil ditambahkan',
            201
        );
    }

    /**
     * Ubah data & jadwal periode (padanan editData() legacy). Jadwal ditulis
     * ke gelombang aktif; kolom gelombang lain (TGLREVIEWREVISI_*,
     * TGLWAKTU_*) yang tidak ada di form V2 dibiarkan apa adanya.
     */
    public function update(UpdatePeriodeRequest $request, Periode $periode)
    {
        $data = $request->validated();

        DB::transaction(function () use ($periode, $data) {
            $periode->update([
                'TAHUN' => $data['tahun'],
                'DESKRIPSI' => $data['nama'],
                'TGLBEGIN' => $data['tglBukaUsulan'],
                'TGLEND' => $data['tglTutupUsulan'],
                'TGLPELAKSANAANBEGIN' => $data['tglPelaksanaanMulai'] ?? null,
                'TGLPELAKSANAANEND' => $data['tglPelaksanaanSelesai'] ?? null,
            ]);

            $gelombangAktif = $periode->gelombang()->where('GELAKTIF', 1)->first();

            if ($gelombangAktif) {
                $gelombangAktif->update($this->jadwalGelombang($data));
            } else {
                $periode->gelombang()->create([
                    'GELOMBANG' => '1',
                    'GELAKTIF' => 1,
                    ...$this->jadwalGelombang($data),
                ]);
            }
        });

        return ApiResponse::success(
            new PeriodeResource($periode->fresh('gelombang')),
            'Periode berhasil diperbarui'
        );
    }

    public function toggleAktif(Periode $periode)
    {
        DB::transaction(function () use ($periode) {
            $newValue = $periode->ISAKTIF ? 0 : 1;

            if ($newValue) {
                Periode::query()->update(['ISAKTIF' => 0]);
            }

            $periode->update(['ISAKTIF' => $newValue]);
        });

        return ApiResponse::success(PeriodeResource::collection(Periode::with('gelombang')->orderByDesc('TAHUN')->get()));
    }

    /**
     * Pemetaan field tanggal form V2 ke kolom periodegelombang.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function jadwalGelombang(array $data): array
    {
        return [
            'TGLPROPOSAL_FROM' => $data['tglBukaUsulan'],
            'TGLPROPOSAL_TO' => $data['tglTutupUsulan'],
            'TGLREVIEW_FROM' => $data['tglTutupUsulan'],
            'TGLREVIEW_TO' => $data['tglBatasReview'],
            'TGLREVISI_FROM' => $data['tglBatasReview'],
            'TGLREVISI_TO' => $data['tglBatasRevisi'],
            'TGLLAPORAN_FROM' => $data['tglMonev'],
            'TGLLAPORAN_TO' => $data['tglLaporanAkhir'],
        ];
    }
}
