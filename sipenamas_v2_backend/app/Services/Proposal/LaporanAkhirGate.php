<?php

namespace App\Services\Proposal;

use App\Models\Penelitian;
use App\Models\PenelitianTim;
use App\Models\Pengisiankuesionerpeneliti;
use App\Models\PengisiankuesionerpenelitiDetail;
use App\Models\Periode;
use App\Models\Soalkuesionerpeneliti;
use App\Models\SoalkuesionerpenelitiDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Syarat akses tahap laporan akhir yang di legacy dijaga di sisi UI
 * (pen/app.js, panel "hasilpenelitian"): daftar hanya untuk anggota tim,
 * KELENGKAPAN LAPORAN & CAPAIAN DAN LUARAN hanya untuk KETUA, kelengkapan
 * butuh kuesioner periode itu lengkap, capaian butuh lembar pengesahan final.
 */
class LaporanAkhirGate
{
    /**
     * Padanan hasilpenelitian.php listData: penelitian yang login ada di
     * `penelitian_tim`, LOLOS atau sudah TUNTAS.
     */
    public function query(User $user): Builder
    {
        return Penelitian::query()
            ->where('JENIS_PA', 'PENELITIAN')
            ->where(fn (Builder $q) => $q->where('STATUSFINALAPPROVAL', 'LOLOS')->orWhere('STATUSKETUNTASANPENELITIAN', 'TUNTAS'))
            ->whereIn('id', function ($sub) use ($user) {
                $sub->select('IDPARENT')->from('penelitian_tim')->whereIn('NIKNIDN', $user->kodepersonAliases());
            });
    }

    /**
     * Padanan permohonanpenelitian.php doCekperannya.
     */
    public function peran(User $user, Penelitian $penelitian): ?string
    {
        return PenelitianTim::where('IDPARENT', $penelitian->id)
            ->whereIn('NIKNIDN', $user->kodepersonAliases())
            ->value('PERAN');
    }

    public function ketua(User $user, int $id): Penelitian
    {
        $penelitian = $this->query($user)->findOrFail($id);

        abort_if($this->peran($user, $penelitian) !== 'KETUA', 403, 'Maaf, fungsi ini hanya untuk KETUA.');

        return $penelitian;
    }

    public function ketuaDenganKuesioner(User $user, int $id): Penelitian
    {
        $penelitian = $this->ketua($user, $id);
        $periode = Periode::where('TAHUN', $penelitian->PERIODEKEGIATAN_TAHUN)->first();

        abort_if(! $periode || ! $this->kuesionerSelesai($user, $periode), 422, 'Silahkan melengkapi kuesioner terlebih dulu.');

        return $penelitian;
    }

    public function ketuaDenganLembarFinal(User $user, int $id): Penelitian
    {
        $penelitian = $this->ketua($user, $id);

        abort_if(! $penelitian->LBRPENGESAHANLAPHASIL_ISFINAL, 422, 'Maaf, Laporan belum lengkap/Final.');

        return $penelitian;
    }

    /**
     * Padanan kuesionerpenelitiandetail.php cekidot: buat baris pengisian
     * dan satu baris jawaban per soal aktif bila belum ada.
     */
    public function pengisianKuesioner(User $user, Periode $periode): ?Pengisiankuesionerpeneliti
    {
        $soal = Soalkuesionerpeneliti::where('ISAKTIF', 1)->first();

        $pengisian = Pengisiankuesionerpeneliti::where('KDPERIODE', $periode->KODEPERIODE)
            ->where('JENIS_PA', 'PENELITIAN')
            ->whereIn('NIK', $user->kodepersonAliases())
            ->first();

        if (! $pengisian && $soal) {
            $pengisian = Pengisiankuesionerpeneliti::create([
                'KDPERIODE' => $periode->KODEPERIODE,
                'JENIS_PA' => 'PENELITIAN',
                'NIK' => $user->kodeperson,
                'KDSOAL' => $soal->KODESOAL,
            ]);
        }

        if ($pengisian && $soal) {
            SoalkuesionerpenelitiDetail::where('IDPARENT', $soal->id)->pluck('NOMOR')->each(
                fn ($nomor) => PengisiankuesionerpenelitiDetail::firstOrCreate(['IDPARENT' => $pengisian->id, 'NOMORSOAL' => $nomor])
            );
        }

        return $pengisian;
    }

    /**
     * Padanan cekstatusOk: lengkap bila semua soal punya SKOR > 0.
     */
    public function kuesionerSelesai(User $user, Periode $periode): bool
    {
        $pengisian = Pengisiankuesionerpeneliti::where('KDPERIODE', $periode->KODEPERIODE)
            ->where('JENIS_PA', 'PENELITIAN')
            ->whereIn('NIK', $user->kodepersonAliases())
            ->first();

        if (! $pengisian) {
            return false;
        }

        $jawaban = $this->jawabanQuery($pengisian);
        $total = (clone $jawaban)->count();

        return $total > 0 && (clone $jawaban)->where('pengisiankuesionerpeneliti_detail.SKOR', '>', 0)->count() === $total;
    }

    /**
     * Baris jawaban yang soalnya masih ada di `soalkuesionerpeneliti_detail`.
     */
    public function jawabanQuery(Pengisiankuesionerpeneliti $pengisian): Builder
    {
        $soalId = Soalkuesionerpeneliti::where('KODESOAL', $pengisian->KDSOAL)->value('id');

        return PengisiankuesionerpenelitiDetail::query()
            ->where('pengisiankuesionerpeneliti_detail.IDPARENT', $pengisian->id)
            ->join('soalkuesionerpeneliti_detail', function ($join) use ($soalId) {
                $join->on('soalkuesionerpeneliti_detail.NOMOR', '=', 'pengisiankuesionerpeneliti_detail.NOMORSOAL')
                    ->where('soalkuesionerpeneliti_detail.IDPARENT', '=', $soalId);
            });
    }
}
