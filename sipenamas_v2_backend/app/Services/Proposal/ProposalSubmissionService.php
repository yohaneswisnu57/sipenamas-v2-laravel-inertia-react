<?php

namespace App\Services\Proposal;

use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Models\PenelitianTim;
use App\Models\Periode;
use App\Models\SkimPenelitian;
use App\Models\TabelRencanaTarget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menulis satu usulan penelitian ke tabel `penelitian` beserta baris
 * turunannya (tim dosen, mahasiswa, 4 tabel RAB) dalam satu transaksi -
 * padanan permohonanpenelitian.php + permohonanpenelitiantim.php +
 * cmbmahasiswa.php legacy yang aslinya terpisah beberapa request AJAX.
 *
 * Seperti checkbox "ajukan" legacy, `ajukan = false` menyimpan draft
 * (ISPENGAJUANFINAL = 0) yang masih bisa diedit ketua; begitu diajukan
 * form terkunci. Usulan masuk antrean Dekan setelah semua anggota setuju
 * dan dokumen proposal diunggah (ISDOKUMENPROPOSALFINAL) - lihat
 * docs/legacy-flow/penelitian.md §2.
 */
class ProposalSubmissionService
{
    public function __construct(private ProposalEligibilityService $eligibility) {}

    public function submit(User $ketua, array $data): Penelitian
    {
        $periode = Periode::aktif()->first();

        if (! $periode) {
            throw ValidationException::withMessages(['periode' => ['Belum ada periode pengajuan yang aktif.']]);
        }

        $skim = SkimPenelitian::where('KODESKIM', $data['skimKode'])->firstOrFail();

        $this->eligibility->assertBolehMengajukan(
            $periode,
            $skim,
            $ketua->kodepersonAliases(),
            $ketua->kodeprodi,
            array_column($data['anggotaDosen'] ?? [], 'npp'),
            (float) $data['biayaUsulan'],
        );

        return DB::transaction(function () use ($ketua, $data, $periode, $skim) {
            $penelitian = Penelitian::create([
                'KDPERIODE' => $periode->KODEPERIODE,
                'PERIODEKEGIATAN_TAHUN' => $periode->TAHUN,
                'TAHUNUSULAN' => (int) now()->year,
                'JENIS_PA' => $skim->ISABDIMAS ? 'ABDIMAS' : 'PENELITIAN',
                'PERMOHONANDIBUAT_KDPERSON' => $ketua->kodeperson,
                'PERMOHONANDIBUAT_STATUS' => 'SUBMITTED',
                'PERMOHONANDIBUAT_TIMESTAMP' => now(),
                'KDPRODI' => $ketua->kodeprodi,
                'ISDOKUMENPROPOSALFINAL' => 0,
                'STATUSPENUNJUKANREVIEWER' => 'DRAFT',
                'STATUSFINALAPPROVAL' => '-',
                'STATUSKETUNTASANPENELITIAN' => '-',
            ] + $this->kolomUsulan($data, $skim));

            $penelitian->tim()->create([
                'NIKNIDN' => $ketua->kodeperson,
                'PERAN' => 'KETUA',
                'URUTAN' => 1,
                'ISAPPROVED' => 1,
                'TSAPPROVED' => now(),
            ]);

            $this->tulisTurunan($penelitian, $data);
            $this->generateRencanaTarget($penelitian, $skim);

            return $penelitian->fresh(PenelitianResource::EAGER_RELATIONS);
        });
    }

    /**
     * Padanan permohonanpenelitian.php editData: hanya untuk draft
     * (form legacy terkunci begitu ISPENGAJUANFINAL = 1). Tim anggota
     * ditulis ulang; persetujuan anggota yang tetap ada dipertahankan.
     */
    public function update(User $ketua, Penelitian $penelitian, array $data): Penelitian
    {
        $periode = Periode::where('TAHUN', $penelitian->PERIODEKEGIATAN_TAHUN)->first() ?? Periode::aktif()->first();

        if (! $periode) {
            throw ValidationException::withMessages(['periode' => ['Belum ada periode pengajuan yang aktif.']]);
        }

        $skim = SkimPenelitian::where('KODESKIM', $data['skimKode'])->firstOrFail();

        $this->eligibility->assertBolehMengajukan(
            $periode,
            $skim,
            $ketua->kodepersonAliases(),
            $penelitian->KDPRODI,
            array_column($data['anggotaDosen'] ?? [], 'npp'),
            (float) $data['biayaUsulan'],
            $penelitian->id,
        );

        return DB::transaction(function () use ($penelitian, $data, $skim) {
            $skimBerubah = $penelitian->KDSKIMPENELITIAN !== $skim->KODESKIM;
            $persetujuan = $penelitian->tim()->where('PERAN', 'ANGGOTA')->get()->keyBy('NIKNIDN');

            $penelitian->update($this->kolomUsulan($data, $skim));

            $penelitian->tim()->where('PERAN', '<>', 'KETUA')->delete();
            $penelitian->mahasiswa()->delete();
            $penelitian->mitra()->delete();
            $this->tulisTurunan($penelitian, $data, $persetujuan);

            if ($skimBerubah) {
                $penelitian->rencanaTarget()->delete();
                $this->generateRencanaTarget($penelitian, $skim);
            }

            return $penelitian->fresh(PenelitianResource::EAGER_RELATIONS);
        });
    }

    /**
     * Padanan deleteData: hapus usulan beserta tim dan baris turunannya.
     */
    public function delete(Penelitian $penelitian): void
    {
        DB::transaction(function () use ($penelitian) {
            $penelitian->tim()->delete();
            $penelitian->mahasiswa()->delete();
            $penelitian->mitra()->delete();
            $penelitian->rencanaTarget()->delete();
            $this->hapusRab($penelitian);
            $penelitian->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function kolomUsulan(array $data, SkimPenelitian $skim): array
    {
        return [
            'KDSKIMPENELITIAN' => $data['skimKode'],
            'JUDULPENELITIAN' => $data['judul'],
            'JUDULPENELITIANX' => $data['judul'],
            'BIDANGPENELITIAN' => $data['bidangFokus'] ?? '',
            'ABDIMAS_TEMPATLOKASI' => $skim->ISABDIMAS ? ($data['tempatLokasi'] ?? null) : null,
            'KDSUMBERDANA' => $data['sumberDanaKode'] ?? $skim->DEFKDSUMBERDANA,
            'NOMINALDANA' => $data['biayaUsulan'],
            'TARGETLUARAN' => $data['targetLuaran'] ?? null,
            // Persen 0-100 seperti legacy (cetak: persen/100 x NOMINALDANA).
            'KOMPOSISIDANA_HONORARIUM' => 30,
            'KOMPOSISIDANA_BAHANPERALATAN' => $data['komposisiBahanPeralatan'],
            'KOMPOSISIDANA_BIAYAPERJALANAN' => $data['komposisiPerjalanan'],
            'KOMPOSISIDANA_LAPORAN' => $data['komposisiLaporan'],
            '__ABSTRAK' => $data['ringkasan'] ?? null,
            'ISPENGAJUANFINAL' => ($data['ajukan'] ?? true) ? 1 : 0,
        ];
    }

    /**
     * @param  Collection<string, PenelitianTim>|null  $persetujuan  baris anggota lama per NIK
     */
    private function tulisTurunan(Penelitian $penelitian, array $data, ?Collection $persetujuan = null): void
    {
        foreach (($data['anggotaDosen'] ?? []) as $index => $anggota) {
            $lama = $persetujuan?->get($anggota['npp']);

            $penelitian->tim()->create([
                'NIKNIDN' => $anggota['npp'],
                'PERAN' => 'ANGGOTA',
                'URUTAN' => $index + 2,
                'URAIANTUGAS' => $anggota['tugas'] ?? null,
                'ISAPPROVED' => $lama?->ISAPPROVED ? 1 : 0,
                'TSAPPROVED' => $lama?->TSAPPROVED,
            ]);
        }

        foreach (($data['anggotaMahasiswa'] ?? []) as $mhs) {
            $penelitian->mahasiswa()->create([
                'NIM' => $mhs['nim'],
                '_KETERANGAN' => $mhs['peran'] ?? '',
            ]);
        }

        foreach (($data['mitra'] ?? []) as $index => $mitra) {
            $penelitian->mitra()->create([
                'nama' => $mitra['nama'],
                'instansi' => $mitra['instansi'],
                'tugas' => $mitra['tugas'] ?? null,
                'urutan' => $index + 1,
            ]);
        }

    }

    private function hapusRab(Penelitian $penelitian): void
    {
        $penelitian->honorarium()->delete();
        $penelitian->pembelian()->delete();
        $penelitian->perjalanan()->delete();
        $penelitian->sewa()->delete();
    }

    /**
     * Salin master target luaran skim ke usulan (legacy
     * rencanatargetpenelitian.php generateData); item wajib langsung
     * tercentang. Melewati duplikat KATEGORI + SUBKATEGORI seperti legacy.
     */
    private function generateRencanaTarget(Penelitian $penelitian, SkimPenelitian $skim): void
    {
        $seen = $penelitian->rencanaTarget()
            ->get(['KATEGORI', 'SUBKATEGORI'])
            ->mapWithKeys(fn ($item) => [($item->KATEGORI ?? '').'|'.($item->SUBKATEGORI ?? '') => true])
            ->all();

        TabelRencanaTarget::where('KDSKIM', $skim->KODESKIM)
            ->orderBy('URUTAN')
            ->get()
            ->each(function (TabelRencanaTarget $target) use ($penelitian, &$seen) {
                $key = ($target->KATEGORI ?? '').'|'.($target->SUBKATEGORI ?? '');
                if (isset($seen[$key])) {
                    return;
                }
                $seen[$key] = true;

                $penelitian->rencanaTarget()->create([
                    'KATEGORI' => $target->KATEGORI,
                    'SUBKATEGORI' => $target->SUBKATEGORI,
                    'ISWAJIB' => $target->ISWAJIB,
                    'INDIKATORNYA' => $target->INDIKATORNYA,
                    'URUTAN' => $target->URUTAN,
                    'ISCHKTARGET' => $target->ISWAJIB ? 1 : 0,
                ]);
            });
    }

    /**
     * Requirement LPPM: kategori "Bahan Habis Pakai" dan "Perjalanan"
     * dihapus dari opsi usulan BARU (lihat StorePenelitianRequest, sudah
     * memblokir kedua kategori itu sebelum request sampai ke sini) -
     * cabang match untuk keduanya sengaja tetap ada supaya kode ini tidak
     * berubah perilaku untuk data lama yang mungkin masih diproses lewat
     * jalur lain, tapi secara praktis sudah tidak pernah tercapai dari
     * endpoint submit usulan baru.
     */
    private function simpanRab(Penelitian $penelitian, array $items): void
    {
        foreach ($items as $item) {
            match ($item['kategori']) {
                'Honorarium' => $penelitian->honorarium()->create([
                    'NAMAPELAKSANA' => $item['item'],
                    'NOMINALHONOR' => $item['biaya'],
                ]),
                'Bahan Habis Pakai' => $penelitian->pembelian()->create([
                    'NAMAMATERIAL' => $item['item'],
                    'NOMINALPEMBELIAN' => $item['biaya'],
                ]),
                'Perjalanan' => $penelitian->perjalanan()->create([
                    'NAMAMATERIAL' => $item['item'],
                    'BIAYAPERTAHUN' => $item['biaya'],
                ]),
                'Sewa Peralatan' => $penelitian->sewa()->create([
                    'NAMAMATERIAL' => $item['item'],
                    'BIAYAPERTAHUN' => $item['biaya'],
                ]),
                default => null,
            };
        }
    }
}
