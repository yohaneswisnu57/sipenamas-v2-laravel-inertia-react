<?php

namespace App\Services\Proposal;

use App\Models\Periode;
use App\Models\Person;
use App\Models\Prodi;
use App\Models\ProdiAnggaran;
use App\Models\Settingan;
use App\Models\SkimPenelitian;
use App\Models\SumberDana;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan kelayakan pengajuan penelitian - padanan task validasi di
 * pen/myphp/permohonanpenelitian.php (CEKCEKALKETUA, CEKCEKALANGGOTA,
 * CEKKUOTAKETUA, VALIDASIJUMLAHANGGOTA, CEKMAXANGGARAN) dan
 * pen/myphp/statuskesediaantim.php (CEKKUOTAANGGOTA). Legacy menjalankannya
 * di UI sebelum simpan; di V2 dijalankan di server.
 *
 * Lihat docs/legacy-flow/penelitian.md §2.2 dan §2.3.
 */
class ProposalEligibilityService
{
    private const SETTINGAN_KEY = 'arief@nuriman.id';

    private const STATUS_TUNTAS = ['TUNTAS', 'TUNTAS BERSYARAT', 'BATAL'];

    /**
     * @param  array<int, string>  $ketuaAliases  KODEPERSON ketua beserta alias NIP lamanya
     * @param  array<int, string>  $anggotaNpp
     * @param  int|null  $kecualiId  usulan yang sedang diedit - tidak dihitung ke kuota ketua (legacy CEKKUOTAKETUA `idnya`)
     *
     * @throws ValidationException
     */
    public function assertBolehMengajukan(Periode $periode, SkimPenelitian $skim, array $ketuaAliases, ?string $kodeProdiKetua, array $anggotaNpp, float $biayaUsulan, ?int $kecualiId = null): void
    {
        $errors = [];
        $jenis = $this->jenisPa($skim);

        if ($cekal = $this->cariCekal($ketuaAliases, $periode, $jenis)) {
            $errors['skimKode'][] = 'Anda masih memiliki '.$this->deskripsiCekal($cekal).' yang belum tuntas.';
        }

        foreach ($anggotaNpp as $index => $npp) {
            if ($cekal = $this->cariCekal([$npp], $periode, $jenis)) {
                $nama = Person::where('KODEPERSON', $npp)->value('NAMALENGKAP') ?? $npp;
                $errors["anggotaDosen.{$index}.npp"][] = "{$nama} masih memiliki ".$this->deskripsiCekal($cekal).' yang belum tuntas.';
            }

            if (in_array($npp, $ketuaAliases, true)) {
                $errors["anggotaDosen.{$index}.npp"][] = 'Ketua tidak boleh terdaftar sebagai anggota.';
            }
        }

        $kuotaKetua = $this->kuota($skim, 'KETUA');
        if ($kuotaKetua !== null && $this->jumlahJadiKetua($ketuaAliases, $periode, $skim, $kecualiId) >= $kuotaKetua) {
            $errors['skimKode'][] = "Kuota sebagai ketua ({$kuotaKetua} usulan per periode) sudah terpenuhi.";
        }

        $jumlahAnggota = count($anggotaNpp);
        $min = $skim->MINANGGOTA;
        $max = $skim->MAXANGGOTA;
        if (($min !== null && $jumlahAnggota < $min) || ($max !== null && $jumlahAnggota > $max)) {
            $errors['anggotaDosen'][] = "Jumlah anggota dosen untuk skim ini harus {$min} sampai {$max} orang.";
        }

        $batas = $this->batasAnggaran($skim, $kodeProdiKetua, $periode);
        if ($batas !== null && ! $batas['isOpenBudget'] && $biayaUsulan > $batas['nominal']) {
            $errors['biayaUsulan'][] = 'Biaya usulan melebihi batas anggaran Rp '.number_format($batas['nominal'], 0, ',', '.').'.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Kuota anggota dicek saat anggota menyetujui keanggotaan (legacy
     * statuskesediaantim.php): total peran KETUA + ANGGOTA yang sudah
     * disetujui pada periode dan jenis yang sama harus di bawah kuota.
     *
     * @param  array<int, string>  $anggotaAliases
     *
     * @throws ValidationException
     */
    public function assertKuotaAnggota(array $anggotaAliases, SkimPenelitian $skim, int $tahunPeriode): void
    {
        $kuota = $this->kuota($skim, 'ANGGOTA');

        if ($kuota === null) {
            return;
        }

        $terpakai = DB::table('penelitian_tim as t')
            ->join('penelitian as p', 'p.id', '=', 't.IDPARENT')
            ->join('skimpenelitian as s', 's.KODESKIM', '=', 'p.KDSKIMPENELITIAN')
            ->where('p.PERIODEKEGIATAN_TAHUN', $tahunPeriode)
            ->where('s.ISABDIMAS', (int) $skim->ISABDIMAS)
            ->whereIn('t.NIKNIDN', $anggotaAliases)
            ->whereIn('t.PERAN', ['KETUA', 'ANGGOTA'])
            ->where('t.ISAPPROVED', 1)
            ->count();

        if ($terpakai >= $kuota) {
            throw ValidationException::withMessages([
                'kuota' => ["Kuota keterlibatan ({$kuota} usulan per periode) sudah terpenuhi."],
            ]);
        }
    }

    /**
     * Penelitian tahun sebelumnya yang LOLOS tapi belum tuntas.
     *
     * @param  array<int, string>  $kodePerson
     */
    private function cariCekal(array $kodePerson, Periode $periode, string $jenis): ?object
    {
        return DB::table('penelitian_tim as t')
            ->join('penelitian as p', 'p.id', '=', 't.IDPARENT')
            ->whereIn('t.NIKNIDN', $kodePerson)
            ->where('p.PERIODEKEGIATAN_TAHUN', '<', $periode->TAHUN)
            ->where('p.STATUSFINALAPPROVAL', 'LOLOS')
            ->where(fn ($q) => $q->whereNull('p.STATUSKETUNTASANPENELITIAN')
                ->orWhereNotIn('p.STATUSKETUNTASANPENELITIAN', self::STATUS_TUNTAS))
            ->where('p.JENIS_PA', $jenis)
            ->select('t.PERAN', 'p.JUDULPENELITIAN', 'p.PERIODEKEGIATAN_TAHUN')
            ->first();
    }

    private function deskripsiCekal(object $cekal): string
    {
        return sprintf('penelitian %d sebagai %s ("%s")', $cekal->PERIODEKEGIATAN_TAHUN, $cekal->PERAN, $cekal->JUDULPENELITIAN);
    }

    /**
     * @param  array<int, string>  $ketuaAliases
     */
    private function jumlahJadiKetua(array $ketuaAliases, Periode $periode, SkimPenelitian $skim, ?int $kecualiId = null): int
    {
        return DB::table('penelitian_tim as t')
            ->join('penelitian as p', 'p.id', '=', 't.IDPARENT')
            ->join('skimpenelitian as s', 's.KODESKIM', '=', 'p.KDSKIMPENELITIAN')
            ->where('p.PERIODEKEGIATAN_TAHUN', $periode->TAHUN)
            ->where(fn ($q) => $q->whereNull('p.STATUSFINALAPPROVAL')->orWhere('p.STATUSFINALAPPROVAL', '<>', 'TIDAK LOLOS'))
            ->where('s.ISABDIMAS', (int) $skim->ISABDIMAS)
            ->whereIn('t.NIKNIDN', $ketuaAliases)
            ->where('t.PERAN', 'KETUA')
            ->where('t.ISAPPROVED', 1)
            ->when($kecualiId, fn ($q) => $q->where('p.id', '<>', $kecualiId))
            ->count();
    }

    /**
     * Null bila baris setting kuota belum dikonfigurasi.
     */
    private function kuota(SkimPenelitian $skim, string $peran): ?int
    {
        $jenis = $skim->ISABDIMAS ? 'PENGABDIAN' : 'PENELITIAN';
        $nilai = Settingan::where('THISISIT', self::SETTINGAN_KEY)->value("KUOTA_{$jenis}_{$peran}");

        return $nilai === null ? null : (int) $nilai;
    }

    /**
     * Sumber dana default skim menentukan acuan batas: dana LPPM memakai
     * batas skim, selain itu memakai anggaran prodi per periode. Null bila
     * acuan batas belum dikonfigurasi.
     *
     * @return array{nominal: float, isOpenBudget: bool}|null
     */
    private function batasAnggaran(SkimPenelitian $skim, ?string $kodeProdi, Periode $periode): ?array
    {
        $sumberDana = $skim->DEFKDSUMBERDANA
            ? SumberDana::where('KODESUMBERDANA', $skim->DEFKDSUMBERDANA)->first()
            : null;

        if (! $sumberDana) {
            return null;
        }

        if ($sumberDana->ISDANALPPM) {
            // Legacy cekMaxanggaran: open budget di cabang dana LPPM tidak
            // pernah terisi, jadi batas skim selalu berlaku.
            return $skim->ANGGARANPERPENELITIAN === null
                ? null
                : ['nominal' => (float) $skim->ANGGARANPERPENELITIAN, 'isOpenBudget' => false];
        }

        $prodiId = $kodeProdi ? Prodi::where('KODEPRODI', $kodeProdi)->value('id') : null;
        $anggaran = $prodiId
            ? ProdiAnggaran::where('IDPARENT', $prodiId)->where('KDPERIODE', $periode->KODEPERIODE)->first()
            : null;

        if (! $anggaran) {
            return null;
        }

        return ['nominal' => (float) $anggaran->ANGGARANPERPENELITIAN, 'isOpenBudget' => (bool) $anggaran->ISOPENBUDGET];
    }

    private function jenisPa(SkimPenelitian $skim): string
    {
        return $skim->ISABDIMAS ? 'ABDIMAS' : 'PENELITIAN';
    }
}
