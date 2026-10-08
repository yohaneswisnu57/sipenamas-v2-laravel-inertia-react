<?php

namespace Tests\Feature\Peneliti;

use App\Models\Mahasiswa;
use App\Models\Penelitian;
use App\Models\PenelitianPenilaianproposal;
use App\Models\PenelitianPenilaianproposalRevisi;
use App\Models\PenelitianTim;
use App\Models\Periode;
use App\Models\Person;
use App\Models\SkimPenelitian;
use App\Models\Soalkuesionerpeneliti;
use App\Models\SoalkuesionerpenelitiDetail;
use App\Models\SumberDana;
use App\Models\TabelRencanaTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;
use ZipArchive;

/**
 * Satu usulan dijalankan dari draft sampai capaian & luaran lewat endpoint
 * peneliti, urutannya mengikuti docs/legacy-flow/penelitian.md. Langkah
 * peran lain (Dekan, reviewer, admin) disimulasikan lewat kolom legacy.
 */
class AlurPenelitiTest extends TestCase
{
    use RefreshDatabase;

    private const IZIN = [
        'view penelitian', 'create penelitian', 'submit revisi penelitian', 'submit laporan akhir penelitian',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        SumberDana::create(['KODESUMBERDANA' => 'INTERNAL', 'NAMASUMBERDANA' => 'Dana Internal']);

        if (! class_exists(ZipArchive::class) || ! extension_loaded('gd')) {
            $this->markTestSkipped('Butuh ekstensi zip dan gd.');
        }

        Storage::fake('legacy_res');
        foreach (self::IZIN as $izin) {
            SpatiePermission::findOrCreate($izin, 'sanctum');
        }

        SkimPenelitian::create(['KODESKIM' => 'INT01', 'NAMASKIM' => 'Penelitian Dasar', 'ISAKTIF' => 1, 'ISABDIMAS' => 0, 'STATUSPEN' => 'INTERNAL']);
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1, 'TGLBEGIN' => '2026-01-01', 'TGLEND' => '2026-12-01']);
        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'Unggah laporan penelitian', 'ISWAJIB' => 1, 'INDIKATORNYA' => 'Selesai', 'URUTAN' => 70]);
        Mahasiswa::create(['NIM' => '5303021001', 'NAMAMAHASISWA' => 'Budi']);

        $soal = Soalkuesionerpeneliti::create(['KODESOAL' => 'KP01', 'ISAKTIF' => 1]);
        SoalkuesionerpenelitiDetail::create(['IDPARENT' => $soal->id, 'NOMOR' => 1, 'KELOMPOK' => 'A', 'URAIAN' => 'Petugas ramah']);
    }

    private function peneliti(string $nama): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(self::IZIN);
        Person::create(['KODEPERSON' => $user->kodeperson, 'NAMALENGKAP' => $nama]);

        return $user;
    }

    public function test_usulan_berjalan_dari_draft_sampai_capaian_luaran(): void
    {
        $ketua = $this->peneliti('Dr. Ketua');
        $anggota = $this->peneliti('Dr. Anggota');
        $this->actingAs($ketua, 'web');

        // 2.1 Draft -> edit -> ajukan.
        $payload = ['judul' => 'Judul Draft', 'skimKode' => 'INT01', 'sumberDanaKode' => 'INTERNAL', 'biayaUsulan' => 5000000, 'komposisiBahanPeralatan' => 50, 'komposisiPerjalanan' => 15, 'komposisiLaporan' => 5, 'anggotaDosen' => [['npp' => $anggota->kodeperson]]];
        $this->post('/pen/penelitian', $payload + ['ajukan' => false])->assertRedirect('/pen/penelitian')->assertAksiBerhasil();
        $id = (int) Penelitian::max('id');
        $base = "/pen/penelitian/{$id}";

        $this->get($base)->assertOk()->assertProp('proposal.status', 'DRAFT');
        $this->put($base, ['judul' => 'Judul Final', 'ajukan' => true] + $payload)->assertAksiBerhasil();
        $this->get($base)->assertProp('proposal.status', 'SUBMITTED')->assertProp('proposal.judul', 'Judul Final');
        $this->put($base, $payload)->assertAksiDitolak();

        // 2.3 Anggota menyetujui keanggotaan.
        $unggah = fn () => $this->post("{$base}/dokumen-proposal", [
            'dokumenProposal' => UploadedFile::fake()->create('proposal.pdf', 300, 'application/pdf'),
        ]);
        $unggah()->assertAksiDitolak();

        $this->actingAs($anggota, 'web');
        $timId = PenelitianTim::where('IDPARENT', $id)->where('NIKNIDN', $anggota->kodeperson)->value('id');
        $this->post("/pen/kesediaan-tim/{$timId}/setuju")->assertAksiBerhasil();
        $this->actingAs($ketua, 'web');

        // 2.5 Dana penyertaan -> generate -> set final -> unggah -> dokumen final.
        $unggah()->assertAksiDitolak();
        $this->post("{$base}/lembar-pengesahan")->assertAksiDitolak();
        $this->put("{$base}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 0])->assertAksiBerhasil();
        $this->post("{$base}/lembar-pengesahan")->assertAksiBerhasil();
        $unggah()->assertAksiDitolak();
        $this->post("{$base}/lembar-pengesahan/final")->assertAksiBerhasil();
        $unggah()->assertAksiBerhasil();
        $this->post("{$base}/dokumen-proposal/final")->assertAksiBerhasil();
        $this->get($base)->assertProp('proposal.isDokumenProposalFinal', true);
        $this->getJson("{$base}/pengesahan")->assertStatus(422);

        // 3-4 Dekan menyetujui, reviewer menilai dengan komentar revisi, admin menunjuk verifikator.
        $penelitian = Penelitian::findOrFail($id);
        $penelitian->update(['APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1, 'STATUSPENUNJUKANREVIEWER' => 'FINAL', 'STATUSPENILAIANREVIEWER' => 'PERBAIKAN']);
        $this->get("{$base}/pengesahan")->assertOk();
        $this->delete($base)->assertAksiDitolak();

        $reviewer = $penelitian->reviewers()->create([
            'NIK' => 'REV001', 'STATUSPENILAIAN' => 'FINAL', 'HASILPENILAIAN' => 'PERBAIKAN', 'ISREVIEWERREVISI' => 1, 'STATUSPENILAIANREVISI' => 'DRAFT',
        ]);
        $penilaian = PenelitianPenilaianproposal::create(['IDPARENT' => $id, 'IDREVIEWER' => $reviewer->id]);
        $komentar = PenelitianPenilaianproposalRevisi::create(['IDPARENT' => $penilaian->id, 'KOMENREVISI' => 'Perjelas metodologi']);

        // 4 Revisi: balas komentar, unggah draft naskah, lalu set final.
        $this->get("/pen/revisi/{$id}")->assertOk()->assertProp('revisi.komentar.0.komentar', 'Perjelas metodologi');
        $this->put("{$base}/revisi/komentar/{$komentar->id}", ['respon' => 'Bab 3 ditambah'])->assertAksiBerhasil();
        $this->post("{$base}/revisi/dokumen", [
            'dokumenRevisi' => UploadedFile::fake()->create('revisi.pdf', 100, 'application/pdf'),
        ])->assertAksiBerhasil();
        $this->post("{$base}/revisi/final")->assertAksiBerhasil();
        $this->put("{$base}/revisi/komentar/{$komentar->id}", ['respon' => 'telat'])->assertAksiDitolak();
        $this->assertDatabaseHas('penelitian_penilaianproposal_revisi', ['id' => $komentar->id, 'KOMENRESPON' => 'Bab 3 ditambah']);

        // 5 Admin meloloskan.
        $this->get('/pen/laporan-akhir?kdperiode=2026')->assertPropCount(0, 'laporan.items');
        $reviewer->update(['STATUSPENILAIANREVISI' => 'FINAL', 'REVISI_HASILPENILAIAN' => 'SUDAH']);
        $penelitian->update(['STATUSFINALAPPROVAL' => 'LOLOS', 'NOMINALDANA_FINAL' => 4500000]);

        // 7.1 Kuesioner -> kelengkapan laporan -> capaian & luaran.
        $laporan = "/pen/laporan-akhir/{$id}";
        $kelengkapan = "/pen/laporan-akhir?kelengkapan={$id}";
        $capaian = "/pen/laporan-akhir?capaian={$id}";
        $this->get('/pen/laporan-akhir?kdperiode=2026')->assertProp('laporan.items.0.id', $id)->assertProp('laporan.items.0.peran', 'KETUA');
        $this->get($kelengkapan)->assertProp('kelengkapan.error', 'Silahkan melengkapi kuesioner terlebih dulu.');

        $soalId = $this->get('/pen/laporan-akhir?kdperiode=2026&kuesioner=1')->assertOk()->inertiaProp('kuesioner.pertanyaan.0.id');
        $this->put("/pen/kuesioner-penelitian/{$soalId}", ['jawab' => 'D'])->assertAksiBerhasil('Jawaban disimpan');
        $this->get('/pen/laporan-akhir?kdperiode=2026')->assertProp('laporan.isKuesionerSelesai', true);

        $this->get($kelengkapan)->assertProp('kelengkapan.id', $id);
        $this->get($capaian)->assertProp('capaian.error', 'Maaf, Laporan belum lengkap/Final.');
        $this->put("{$laporan}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 100000])->assertAksiBerhasil();
        $this->post("{$laporan}/mahasiswa", ['nim' => '5303021001'])->assertAksiBerhasil();
        $this->post("{$laporan}/lembar-pengesahan")->assertAksiBerhasil();
        $this->post("{$laporan}/lembar-pengesahan/final")->assertAksiBerhasil();
        $this->getJson("{$laporan}/lembar-pengesahan")->assertStatus(422);

        $targetId = $this->get($capaian)->assertOk()->inertiaProp('capaian.target.0.id');
        $this->put("{$laporan}/capaian/{$targetId}", ['realisasi' => true])->assertAksiDitolak();
        $this->post("{$laporan}/capaian/{$targetId}/dokumen", ['dokumen' => UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf')])->assertAksiBerhasil();
        $this->put("{$laporan}/capaian/{$targetId}", ['realisasi' => true, 'keterangan' => 'Selesai'])
            ->assertAksiBerhasil('Capaian disimpan');
        $this->get($capaian)->assertProp('capaian.target.0.isRealisasi', true);

        // Anggota melihat daftar tetapi tidak bisa membuka fungsi ketua.
        $this->actingAs($anggota, 'web');
        $this->get('/pen/laporan-akhir?kdperiode=2026')->assertProp('laporan.items.0.peran', 'ANGGOTA');
        $this->get($capaian)->assertProp('capaian.error', 'Maaf, fungsi ini hanya untuk KETUA.');
        $this->get("/pen/revisi/{$id}")->assertNotFound();

        // 7.2 Setelah Dekan menyetujui laporan akhir, lembar pengesahan bisa diunduh.
        $this->actingAs($ketua, 'web');
        $penelitian->update(['ISDEKANAPPROVELAPORANAKHIR' => 1]);
        $this->get("{$laporan}/lembar-pengesahan")->assertOk();
    }
}
