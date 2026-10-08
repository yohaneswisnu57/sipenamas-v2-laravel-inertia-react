<?php

namespace App\Http\Resources;

use App\Domain\Proposal\ProposalStatusResolver;
use App\Models\Person;
use App\Services\Proposal\PengesahanQrService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Bentuk output mengikuti mockPenelitian di
 * sipenamas_v2_frontend/src/mock/db.js. Dipakai untuk list maupun detail -
 * frontend memang tidak membedakan dua bentuk terpisah untuk resource ini.
 *
 * Beberapa field derived/tidak punya kolom legacy langsung:
 * - kodeUsulan: disintesis dari tahun+fakultas+id (legacy tidak punya
 *   kolom kode usulan formal).
 * - status: dihasilkan App\Domain\Proposal\ProposalStatusResolver, BUKAN
 *   dibaca dari satu kolom.
 * - reviewer statusKesediaan: lihat PenelitianReviewer::statusKesediaan().
 */
class PenelitianResource extends JsonResource
{
    /**
     * Semua relasi yang toArray() di bawah ini sentuh - WAJIB di-eager-load
     * lewat ini di controller manapun yang memanggil PenelitianResource
     * atas lebih dari satu baris. Tanpa ini, tiap baris memicu hingga 10
     * query lazy-load terpisah (tim, mahasiswa, honorarium, pembelian,
     * perjalanan, sewa, dst) - untuk ratusan proposal ini nyata bikin
     * request timeout/crash (lihat catatan insiden di rencana implementasi:
     * /adm/plotting sempat matikan seluruh proses `php artisan serve`
     * setelah 30 detik karena N+1 ini).
     */
    public const EAGER_RELATIONS = [
        'skim', 'ketua', 'prodi.fakultas', 'tim.person.prodi', 'mahasiswa.mahasiswa',
        'honorarium', 'pembelian', 'perjalanan', 'sewa', 'reviewers.reviewer',
        'monevHasil', 'mitra',
    ];

    /**
     * Lembar pengesahan berisi 2 QR SVG (mahal di-generate) dan hanya dipakai
     * halaman detail - list mematikannya lewat collection()/withoutPengesahan().
     */
    public bool $includePengesahan = true;

    public static function collection($resource)
    {
        $collection = parent::collection($resource);
        $collection->collection->each(fn (self $item) => $item->withoutPengesahan());

        return $collection;
    }

    public function withoutPengesahan(): static
    {
        $this->includePengesahan = false;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $reviewers = $this->relationLoaded('reviewers')
            ? $this->reviewers
            : $this->reviewers()->get();

        // Pisahkan reviewer reguler dari pembanding (ISREVIEWERPEMBANDING=1)
        $regularReviewers = $reviewers->where('ISREVIEWERPEMBANDING', 0)->values();
        $pembanding = $reviewers->firstWhere('ISREVIEWERPEMBANDING', 1);

        [$reviewer1, $reviewer2, $reviewer3] = [$regularReviewers->get(0), $regularReviewers->get(1), $regularReviewers->get(2)];

        // Nilai nyata HASILPENILAIAN di dbsipenamas: PERBAIKAN/LOLOS/TOLAK/'-'
        // (bukan 'REVISI') - lihat catatan validasi data di
        // App\Domain\Proposal\ProposalStatusResolver.
        // Hanya penilaian FINAL - draf reviewer belum boleh terlihat pihak lain.
        $revisiReviewer = $reviewers->first(fn ($r) => $r->STATUSPENILAIAN === 'FINAL' && $r->HASILPENILAIAN === 'PERBAIKAN');

        return [
            'id' => (string) $this->id,
            'kodeUsulan' => $this->syntheticKodeUsulan(),
            'judul' => $this->JUDULPENELITIAN,
            'kodeperiode' => $this->KDPERIODE,
            // Legacy hanya mengisi PERIODEKEGIATAN_TAHUN; TAHUNUSULAN kosong di baris lama.
            'tahun' => (string) ($this->TAHUNUSULAN ?: $this->PERIODEKEGIATAN_TAHUN),
            'skimKode' => $this->KDSKIMPENELITIAN,
            'skimNama' => $this->skim?->NAMASKIM,
            'fakultasKode' => $this->prodi?->fakultas?->KODEFAKULTAS,
            'fakultasNama' => $this->prodi?->fakultas?->NAMAFAKULTAS,
            'prodiNama' => $this->prodi?->NAMAPRODI,
            'ketuaPersonId' => $this->PERMOHONANDIBUAT_KDPERSON,
            'ketuaNama' => $this->ketua?->NAMALENGKAP,
            'ketuaNpp' => $this->ketua?->NIDN ?: $this->PERMOHONANDIBUAT_KDPERSON,
            'biayaUsulan' => (float) $this->NOMINALDANA,
            'biayaDisetujui' => $this->NOMINALDANA_FINAL !== null ? (float) $this->NOMINALDANA_FINAL : null,
            'status' => app(ProposalStatusResolver::class)->resolve($this->resource)->value,
            'tglSubmit' => optional($this->PERMOHONANDIBUAT_TIMESTAMP)->format('Y-m-d'),
            'reviewer1' => $this->reviewerSummary($reviewer1),
            'reviewer2' => $this->reviewerSummary($reviewer2),
            'reviewer3' => $this->reviewerSummary($reviewer3),
            'reviewerPembanding' => $this->reviewerSummary($pembanding),
            'skorReviewer1' => $reviewer1 && $reviewer1->STATUSPENILAIAN === 'FINAL' ? $reviewer1->TOTALSKOR : null,
            'skorReviewer2' => $reviewer2 && $reviewer2->STATUSPENILAIAN === 'FINAL' ? $reviewer2->TOTALSKOR : null,
            'skorReviewer3' => $reviewer3 && $reviewer3->STATUSPENILAIAN === 'FINAL' ? $reviewer3->TOTALSKOR : null,
            'skorPembanding' => $pembanding && $pembanding->STATUSPENILAIAN === 'FINAL' ? $pembanding->TOTALSKOR : null,
            'skorRataRata' => $this->SKORAKHIR,
            'statusFinalApproval' => $this->STATUSFINALAPPROVAL,
            'suratTugas' => [
                'nomor' => $this->CETAKSURATTUGAS_NOMORSURAT,
                'tanggal' => $this->CETAKSURATTUGAS_TANGGALSURAT,
                'isFinal' => $this->CETAKSURATTUGAS_STATUS === 'FINAL',
            ],
            'suratDana' => [
                'nomor' => $this->CETAKSURATDANA_NOMORSURAT,
                'tanggal' => $this->CETAKSURATDANA_TANGGALSURAT,
                'isFinal' => $this->CETAKSURATDANA_STATUS === 'FINAL',
            ],
            'ringkasan' => $this->__ABSTRAK,
            'bidangFokus' => $this->BIDANGPENELITIAN,
            'targetLuaran' => $this->TARGETLUARAN,
            'tempatLokasi' => $this->ABDIMAS_TEMPATLOKASI,
            'catatanRevisi' => $revisiReviewer?->KOMENTAR,
            'rekomendasiStatus' => $revisiReviewer?->HASILPENILAIAN,
            'rekomendasiDana' => $revisiReviewer?->REKOMENDASIBIAYA,
            'anggotaDosen' => $this->anggotaDosen(),
            'anggotaMahasiswa' => $this->anggotaMahasiswa(),
            'mitra' => $this->mitraList(),
            'rabItems' => $this->rabItems(),
            'komposisiHonorarium' => (float) $this->KOMPOSISIDANA_HONORARIUM,
            'komposisiBahanPeralatan' => (float) $this->KOMPOSISIDANA_BAHANPERALATAN,
            'komposisiPerjalanan' => (float) $this->KOMPOSISIDANA_BIAYAPERJALANAN,
            'komposisiLaporan' => (float) $this->KOMPOSISIDANA_LAPORAN,
            'catatanDekan' => $this->catatanDekan(),
            'isApprovedByLppm' => (bool) $this->ISAPPROVEDBYLPPM,
            // ISAPPROVEDBYLPPM hanya diisi V2; baris legacy bernilai 0 walau
            // Dekan sudah menyetujui, jadi gerbang plotting pakai kolom Dekan.
            'isApprovedByDekan' => (bool) $this->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN,
            'isPengajuanFinal' => (bool) $this->ISPENGAJUANFINAL,
            'sumberDanaKode' => $this->KDSUMBERDANA,
            'isDokumenProposalFinal' => (bool) $this->ISDOKUMENPROPOSALFINAL,
            'adaDokumenProposal' => filled($this->FILE_DOKUMENPROPOSAL_FINAL),
            'danaMitra' => $this->LBRPENGESAHANPROPOSAL_DANAMITRA !== null ? (float) $this->LBRPENGESAHANPROPOSAL_DANAMITRA : null,
            'danaInkind' => $this->LBRPENGESAHANPROPOSAL_DANAINKIND !== null ? (float) $this->LBRPENGESAHANPROPOSAL_DANAINKIND : null,
            'adaLembarPengesahan' => filled($this->LBRPENGESAHANPROPOSAL_NAMAFILE),
            'isLembarPengesahanFinal' => (bool) $this->LBRPENGESAHANPROPOSAL_ISFINAL,
            'isButuhReviewerKetiga' => (bool) $this->ISBUTUHREVIEWERKETIGA,
            'statusPenilaianReviewer' => $this->STATUSPENILAIANREVIEWER,
            'isKetua' => in_array($this->PERMOHONANDIBUAT_KDPERSON, $request->user()?->kodepersonAliases() ?? [], true),
            'pengesahan' => $this->when($this->includePengesahan, fn () => $this->pengesahan()),
            'statusPenunjukanReviewer' => $this->STATUSPENUNJUKANREVIEWER,
            'revisiVerifikator' => $this->revisiVerifikator(),
        ];
    }

    private function revisiVerifikator(): ?array
    {
        $siklus = $this->activeRevisiCycle();

        if (! $siklus) {
            return null;
        }

        $verifikator = $siklus->verifikator;

        return [
            'nik' => $verifikator->NIK,
            'nama' => $verifikator->relationLoaded('reviewer')
                ? $verifikator->reviewer?->NAMALENGKAP
                : Person::where('KODEPERSON', $verifikator->NIK)->value('NAMALENGKAP'),
            'status' => $siklus->status->value,
            'catatan' => $verifikator->REVISI_KOMENTAR,
        ];
    }

    /**
     * Keputusan Dekan dari kolom legacy: approve -> APPROVALPERMOHONAN_*,
     * tolak -> _MSG_PENOLAKANDEKAN.
     */
    private function catatanDekan(): ?array
    {
        if ($this->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN) {
            return [
                'keputusan' => 'SETUJU',
                'catatan' => $this->APPROVALPERMOHONAN_CATATANDEKAN,
                'tglKeputusan' => $this->APPROVALPERMOHONAN_TIMESTAMP ? Carbon::parse($this->APPROVALPERMOHONAN_TIMESTAMP)->format('Y-m-d H:i') : null,
            ];
        }

        if (filled($this->_MSG_PENOLAKANDEKAN)) {
            return ['keputusan' => 'TOLAK', 'catatan' => $this->_MSG_PENOLAKANDEKAN, 'tglKeputusan' => null];
        }

        return null;
    }

    /**
     * Requirement LPPM: lembar pengesahan pakai QR code berisi NIK + nama
     * Dekan dan Ketua - lihat App\Services\Proposal\PengesahanQrService.
     * QR Dekan baru ada setelah Dekan memutuskan (approve/reject).
     */
    private function pengesahan(): array
    {
        $qr = app(PengesahanQrService::class);

        $ketuaNik = $this->PERMOHONANDIBUAT_KDPERSON;
        $ketuaNama = $this->ketua?->NAMALENGKAP;

        $dekan = null;
        $dekanNik = $this->APPROVALPERMOHONAN_KDDEKAN;

        if ($this->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN && filled($dekanNik)) {
            $dekanNama = Person::where('KODEPERSON', $dekanNik)->value('NAMALENGKAP');

            $dekan = [
                'nik' => $dekanNik,
                'nama' => $dekanNama,
                'qr' => $qr->generate($dekanNik, (string) $dekanNama),
            ];
        }

        return [
            'ketua' => [
                'nik' => $ketuaNik,
                'nama' => $ketuaNama,
                'qr' => $qr->generate((string) $ketuaNik, (string) $ketuaNama),
            ],
            'dekan' => $dekan,
        ];
    }

    private function syntheticKodeUsulan(): string
    {
        $fakultasKode = $this->prodi?->fakultas?->KODEFAKULTAS ?? 'XX';

        return sprintf('PR-%s-%s-%03d', $this->TAHUNUSULAN ?: $this->PERIODEKEGIATAN_TAHUN, $fakultasKode, $this->id);
    }

    private function reviewerSummary($reviewer): ?array
    {
        if (! $reviewer) {
            return null;
        }

        return [
            'id' => $reviewer->NIK,
            'penugasanId' => (string) $reviewer->id,
            'nama' => $reviewer->reviewer?->NAMALENGKAP,
            'statusKesediaan' => $reviewer->statusKesediaan(),
            'isPembanding' => (bool) $reviewer->ISREVIEWERPEMBANDING,
            // Legacy tidak melarang, tapi V2 mencegah verifikator revisi
            // yang sudah pernah menyelesaikan verifikasi dipilih lagi -
            // lihat RevisiCycleService::tunjukVerifikator().
            'sudahVerifikasiRevisi' => $reviewer->STATUSPENILAIANREVISI === 'FINAL',
        ];
    }

    private function anggotaDosen(): array
    {
        $tim = $this->relationLoaded('tim') ? $this->tim : $this->tim()->get();

        return $tim->where('PERAN', '!=', 'KETUA')->sortBy('URUTAN')->map(fn ($t) => [
            'nama' => $t->person?->NAMALENGKAP,
            'npp' => $t->NIKNIDN,
            'prodi' => $t->person?->prodi?->NAMAPRODI,
            'tugas' => $t->URAIANTUGAS,
            'isApproved' => (bool) $t->ISAPPROVED,
        ])->values()->all();
    }

    private function anggotaMahasiswa(): array
    {
        $mhs = $this->relationLoaded('mahasiswa') ? $this->mahasiswa : $this->mahasiswa()->get();

        return $mhs->map(fn ($m) => [
            'nama' => $m->mahasiswa?->NAMAMAHASISWA ?: $m->_NAMAMAHASISWA,
            'nim' => $m->NIM,
            'prodi' => $m->mahasiswa?->NAMAPRODI,
            'peran' => $m->_KETERANGAN,
        ])->values()->all();
    }

    private function mitraList(): array
    {
        $mitra = $this->relationLoaded('mitra') ? $this->mitra : $this->mitra()->get();

        return $mitra->map(fn ($m) => [
            'nama' => $m->nama,
            'instansi' => $m->instansi,
            'tugas' => $m->tugas,
        ])->values()->all();
    }

    private function rabItems(): array
    {
        $items = [];

        foreach (($this->relationLoaded('honorarium') ? $this->honorarium : $this->honorarium()->get()) as $row) {
            $items[] = ['kategori' => 'Honorarium', 'item' => $row->NAMAPELAKSANA, 'biaya' => (float) $row->NOMINALHONOR];
        }

        foreach (($this->relationLoaded('pembelian') ? $this->pembelian : $this->pembelian()->get()) as $row) {
            $items[] = ['kategori' => 'Bahan Habis Pakai', 'item' => $row->NAMAMATERIAL, 'biaya' => (float) $row->NOMINALPEMBELIAN];
        }

        foreach (($this->relationLoaded('perjalanan') ? $this->perjalanan : $this->perjalanan()->get()) as $row) {
            $items[] = ['kategori' => 'Perjalanan', 'item' => $row->NAMAMATERIAL, 'biaya' => (float) $row->BIAYAPERTAHUN];
        }

        foreach (($this->relationLoaded('sewa') ? $this->sewa : $this->sewa()->get()) as $row) {
            $items[] = ['kategori' => 'Sewa Peralatan', 'item' => $row->NAMAMATERIAL, 'biaya' => (float) $row->BIAYAPERTAHUN];
        }

        return $items;
    }
}
