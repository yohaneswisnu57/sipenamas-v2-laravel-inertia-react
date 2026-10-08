<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Penelitian;
use App\Models\PenelitianTim;
use App\Models\Periode;
use App\Models\Person;
use App\Models\Prodi;
use App\Models\SkimPenelitian;
use App\Models\Soalkuesionerpeneliti;
use App\Models\SoalkuesionerpenelitiDetail;
use App\Models\SoalMonevPenelitian;
use App\Models\SoalPenilaianProposal;
use App\Models\SumberDana;
use App\Models\TabelKodeAnggaran;
use App\Models\TabelRencanaTarget;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;
use ZipArchive;

/**
 * Satu usulan penelitian dijalankan dari pengajuan sampai TUNTAS dan monev,
 * seluruhnya lewat endpoint HTTP tiap peran (peneliti, Dekan, admin LPPM,
 * reviewer) - urutan docs/legacy-flow/penelitian.md §0. Status usulan
 * dicek di tiap perpindahan tahap.
 */
class AlurPenelitianLintasPeranTest extends TestCase
{
    use RefreshDatabase;

    /** Komentar FINAL wajib minimal 100 karakter (legacy rev/app.js). */
    private const CATATAN_FINAL = 'Proposal sudah sesuai dengan roadmap penelitian, metodologi jelas, dan anggaran wajar untuk luaran yang ditargetkan.';

    private User $ketua;

    private int $id;

    protected function setUp(): void
    {
        parent::setUp();

        SumberDana::create(['KODESUMBERDANA' => 'INTERNAL', 'NAMASUMBERDANA' => 'Dana Internal']);

        if (! class_exists(ZipArchive::class) || ! extension_loaded('gd')) {
            $this->markTestSkipped('Butuh ekstensi zip dan gd.');
        }

        Storage::fake('legacy_res');
        foreach (PermissionCatalog::allPermissionNames() as $izin) {
            SpatiePermission::findOrCreate($izin, 'sanctum');
        }

        Fakultas::create(['KODEFAKULTAS' => 'FT', 'NAMAFAKULTAS' => 'Teknik', 'KDDEKAN' => 'DKN01']);
        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT']);
        Periode::create([
            'KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1,
            'TGLBEGIN' => '2026-01-01', 'TGLEND' => '2026-12-01',
            'TGLPELAKSANAANBEGIN' => '2026-06-01', 'TGLPELAKSANAANEND' => '2026-11-30',
        ]);

        TabelKodeAnggaran::create(['NOMORKODE' => '612.01.2439', 'KETERANGAN' => 'Hibah Penelitian']);
        SkimPenelitian::create([
            'KODESKIM' => 'INT01', 'NAMASKIM' => 'Penelitian Dasar', 'ISAKTIF' => 1, 'ISABDIMAS' => 0,
            'STATUSPEN' => 'INTERNAL', 'KDSOALPENILAIANPROPOSAL' => 'S01', 'NOMORKODEANGGARAN' => '612.01.2439',
        ]);
        $borang = SoalPenilaianProposal::create(['KODESOAL' => 'S01', 'DESKRIPSI' => 'Borang reguler']);
        $borang->detail()->create(['NOMOR' => 1, 'KRITERIAPENILAIAN' => 'Perumusan masalah', 'BOBOTPERSEN' => 60]);
        $borang->detail()->create(['NOMOR' => 2, 'KRITERIAPENILAIAN' => 'Metode', 'BOBOTPERSEN' => 40]);

        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'Unggah laporan penelitian', 'ISWAJIB' => 1, 'INDIKATORNYA' => 'Selesai', 'URUTAN' => 70]);
        Mahasiswa::create(['NIM' => '5303021001', 'NAMAMAHASISWA' => 'Budi']);

        $kuesioner = Soalkuesionerpeneliti::create(['KODESOAL' => 'KP01', 'ISAKTIF' => 1]);
        SoalkuesionerpenelitiDetail::create(['IDPARENT' => $kuesioner->id, 'NOMOR' => 1, 'KELOMPOK' => 'A', 'URAIAN' => 'Petugas ramah']);

        foreach (range(1, 8) as $nomor) {
            SoalMonevPenelitian::create(['NOMOR' => $nomor, 'ASPEKPENILAIAN' => "Aspek {$nomor}", 'PIL01' => 'Tidak', 'PIL02' => 'Ya']);
        }
    }

    /**
     * @param  list<string>  $izin
     */
    private function pengguna(string $kodeperson, string $nama, array $izin, array $person = []): User
    {
        $user = User::factory()->create(['kodeperson' => $kodeperson, 'kodeprodi' => 'TI']);
        $user->givePermissionTo($izin);
        Person::create(['KODEPERSON' => $kodeperson, 'NAMALENGKAP' => $nama, 'KDPRODI' => 'TI'] + $person);

        return $user;
    }

    /**
     * Ganti pengguna aktif untuk halaman web (guard session) dan API modul
     * lain (guard sanctum). Guard sanctum menyimpan user hasil resolusi
     * sebelumnya selama satu test, jadi perlu diset ulang tiap ganti peran.
     */
    private function masuk(User $user): void
    {
        $this->actingAs($user, 'web');
        Sanctum::actingAs($user);
    }

    private function assertStatus(string $status): void
    {
        $this->masuk($this->ketua);
        $this->get("/pen/penelitian/{$this->id}")->assertOk()->assertProp('proposal.status', $status);
    }

    public function test_usulan_berjalan_dari_pengajuan_sampai_tuntas_dan_monev(): void
    {
        $izinPeneliti = [
            'view penelitian', 'create penelitian', 'submit revisi penelitian', 'submit laporan akhir penelitian', 'submit monev penelitian',
        ];
        $izinReviewer = [
            'view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan', 'view revisi queue penugasan', 'verifikasi revisi penugasan',
        ];

        $this->ketua = $ketua = $this->pengguna('PEN01', 'Dr. Ketua', $izinPeneliti);
        $anggota = $this->pengguna('PEN02', 'Dr. Anggota', $izinPeneliti);
        $gjm = $this->pengguna('GJM01', 'Dr. GJM', $izinPeneliti, ['ISGJM' => 1]);
        $dekan = $this->pengguna('DKN01', 'Prof. Dekan', ['view penelitian', 'approve penelitian', 'reject penelitian']);
        $admin = $this->pengguna('ADM01', 'Admin LPPM', [
            'view plotting', 'assign reviewer plotting', 'finalize reviewer plotting', 'assign revisi verifikator plotting',
            'view final approval', 'decide final approval penelitian',
        ]);
        $reviewer1 = $this->pengguna('REV01', 'Reviewer Satu', $izinReviewer);
        $reviewer2 = $this->pengguna('REV02', 'Reviewer Dua', $izinReviewer);
        // Verifikator revisi harus orang terpisah dari reviewer asli usulan
        // (RevisiCycleService::tunjukVerifikator menolak reviewer asli).
        $verifikator = $this->pengguna('VER01', 'Verifikator Revisi', ['view revisi queue penugasan', 'verifikasi revisi penugasan']);

        // --- 2. Peneliti: usulan, persetujuan anggota, lembar pengesahan, proposal.
        $this->masuk($ketua);
        $this->post('/pen/penelitian', [
            'judul' => 'Sensor IoT Kualitas Air', 'skimKode' => 'INT01', 'sumberDanaKode' => 'INTERNAL', 'biayaUsulan' => 5000000, 'komposisiBahanPeralatan' => 50, 'komposisiPerjalanan' => 15, 'komposisiLaporan' => 5,
            'anggotaDosen' => [['npp' => 'PEN02']],
        ])->assertAksiBerhasil();
        $this->id = $id = (int) Penelitian::max('id');
        $pen = "/pen/penelitian/{$id}";
        $this->assertStatus('SUBMITTED');

        $this->masuk($dekan);
        $this->getJson('/api/v1/dkn/proposal')->assertOk()->assertJsonCount(0, 'data');

        $this->masuk($anggota);
        $timId = PenelitianTim::where('IDPARENT', $id)->where('NIKNIDN', 'PEN02')->value('id');
        $this->get('/pen/kesediaan-tim')->assertOk()->assertPropCount(1, 'items');
        $this->post("/pen/kesediaan-tim/{$timId}/setuju")->assertAksiBerhasil();

        $this->masuk($ketua);
        $this->put("{$pen}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 0])->assertAksiBerhasil();
        $this->post("{$pen}/lembar-pengesahan")->assertAksiBerhasil();
        $this->post("{$pen}/lembar-pengesahan/final")->assertAksiBerhasil();
        $this->post("{$pen}/dokumen-proposal", [
            'dokumenProposal' => UploadedFile::fake()->create('proposal.pdf', 300, 'application/pdf'),
        ])->assertAksiBerhasil();
        $this->post("{$pen}/dokumen-proposal/final")->assertAksiBerhasil();

        // --- 3. Dekan menyetujui usulan.
        $this->masuk($dekan);
        $this->getJson('/api/v1/dkn/proposal')->assertOk()->assertJsonPath('data.0.id', (string) $id);
        $this->postJson("/api/v1/dkn/proposal/{$id}/approve", ['catatan' => 'Sesuai roadmap fakultas'])->assertOk();

        // --- 4. Admin memplot reviewer; reviewer bersedia dan menilai.
        $this->masuk($admin);
        $this->getJson('/api/v1/adm/plotting')->assertOk()->assertJsonFragment(['id' => (string) $id]);
        $this->postJson("/api/v1/adm/plotting/{$id}/reviewer", ['reviewer1Id' => 'REV01', 'reviewer2Id' => 'REV02'])->assertOk();
        $this->postJson("/api/v1/adm/plotting/{$id}/finalize")->assertOk();
        $this->assertStatus('PLOTTED');

        $skor = [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 6]];
        $this->masuk($reviewer1);
        $this->getJson('/api/v1/rev/penugasan')->assertOk()->assertJsonPath('data.0.id', (string) $id);
        $this->postJson("/api/v1/rev/penugasan/{$id}/kesediaan", ['bersedia' => true])->assertOk();
        $this->assertStatus('REVIEW');

        $this->masuk($reviewer1);
        $this->getJson("/api/v1/rev/penugasan/{$id}/borang")->assertOk()->assertJsonCount(2, 'data');
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'REVISI',
            'komentarRevisi' => ['Perjelas metodologi'],
        ])->assertOk()->assertJsonPath('data.hasilPenilaian', 'PERBAIKAN');

        $this->masuk($reviewer2);
        $this->postJson("/api/v1/rev/penugasan/{$id}/kesediaan", ['bersedia' => true])->assertOk();
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
        ])->assertOk()->assertJsonPath('data.hasilPenilaian', 'LOLOS');
        $this->assertStatus('REVISI');

        // --- 4. Revisi: admin menunjuk verifikator, ketua membalas komentar, reviewer memverifikasi.
        $this->masuk($ketua);
        $this->post("{$pen}/revisi/final")->assertAksiDitolak();

        $this->masuk($admin);
        $this->postJson("/api/v1/adm/plotting/{$id}/revisi-verifikator", ['reviewerId' => 'VER01'])->assertOk();

        $this->masuk($ketua);
        $komentarId = $this->get("/pen/revisi/{$id}")->assertOk()
            ->assertProp('revisi.komentar.0.komentar', 'Perjelas metodologi')
            ->inertiaProp('revisi.komentar.0.id');
        $this->put("{$pen}/revisi/komentar/{$komentarId}", ['respon' => 'Bab 3 ditambah'])->assertAksiBerhasil();
        $this->post("{$pen}/revisi/dokumen", [
            'dokumenRevisi' => UploadedFile::fake()->create('revisi.pdf', 300, 'application/pdf'),
        ])->assertAksiBerhasil();
        $this->assertStatus('REVISI');
        $this->post("{$pen}/revisi/final")->assertAksiBerhasil();
        $this->assertStatus('MENUNGGU_VERIFIKASI_REVISI');

        $this->masuk($verifikator);
        $this->getJson('/api/v1/rev/penugasan/revisi')->assertOk()->assertJsonPath('data.0.id', (string) $id);
        $this->getJson("/api/v1/rev/penugasan/{$id}/komentar-revisi")->assertOk()->assertJsonPath('data.0.respon', 'Bab 3 ditambah');
        $this->postJson("/api/v1/rev/penugasan/{$id}/verifikasi-revisi", ['status' => 'DISETUJUI', 'catatan' => 'Sudah sesuai'])->assertOk();
        $this->assertStatus('FINAL_APPROVAL');

        // --- 5. Admin meloloskan.
        $this->masuk($admin);
        $this->getJson('/api/v1/adm/final-approval')->assertOk()->assertJsonFragment(['id' => (string) $id]);
        $this->postJson("/api/v1/adm/final-approval/{$id}", ['status' => 'LOLOS', 'biayaDisetujui' => 4500000])->assertOk();
        $this->assertStatus('LOLOS');

        // --- 7.1 Peneliti: kuesioner, kelengkapan laporan, capaian & luaran.
        $laporan = "/pen/laporan-akhir/{$id}";
        $soalId = $this->get('/pen/laporan-akhir?kdperiode=2026&kuesioner=1')->assertOk()->inertiaProp('kuesioner.pertanyaan.0.id');
        $this->put("/pen/kuesioner-penelitian/{$soalId}", ['jawab' => 'D'])->assertAksiBerhasil();
        $this->put("{$laporan}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 100000])->assertAksiBerhasil();
        $this->post("{$laporan}/mahasiswa", ['nim' => '5303021001'])->assertAksiBerhasil();
        $this->post("{$laporan}/lembar-pengesahan")->assertAksiBerhasil();

        $this->masuk($dekan);
        $this->postJson("/api/v1/dkn/laporan-akhir/{$id}/approve")->assertStatus(422);

        $this->masuk($ketua);
        $this->post("{$laporan}/lembar-pengesahan/final")->assertAksiBerhasil();
        $this->assertStatus('LAPORAN_AKHIR');
        $targetId = $this->get("/pen/laporan-akhir?capaian={$id}")->assertOk()->inertiaProp('capaian.target.0.id');
        $this->post("{$laporan}/capaian/{$targetId}/dokumen", ['dokumen' => UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf')])->assertAksiBerhasil();
        $this->put("{$laporan}/capaian/{$targetId}", ['realisasi' => true, 'keterangan' => 'Selesai'])->assertAksiBerhasil();
        $this->getJson("{$laporan}/lembar-pengesahan")->assertStatus(422);

        // --- 7.2 Dekan menyetujui laporan akhir; admin menetapkan ketuntasan.
        $this->masuk($dekan);
        $this->getJson('/api/v1/dkn/laporan-akhir?kdperiode=2026')->assertOk()
            ->assertJsonPath('data.items.0.id', $id)
            ->assertJsonPath('data.items.0.jumlahRealisasi', 1)
            ->assertJsonPath('data.items.0.isDisetujuiDekan', false);
        $this->getJson('/api/v1/dkn/monev')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/dkn/laporan-akhir/{$id}/approve")->assertOk();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('legacy_res')->path("proposal/lbrpengesahan_lapakhir_{$id}.docx"));
        $this->assertSame(filesize(resource_path('templates/checkmark.jpg')), $zip->statName('word/media/image1.jpeg')['size']);
        $zip->close();

        $this->masuk($ketua);
        $this->get("{$laporan}/lembar-pengesahan")->assertOk();

        $this->masuk($admin);
        $this->getJson('/api/v1/adm/ketuntasan?kdperiode=2026')->assertOk()
            ->assertJsonPath('data.items.0.id', $id)
            ->assertJsonPath('data.items.0.isDisetujuiDekan', true);
        $this->putJson("/api/v1/adm/ketuntasan/{$id}", ['status' => 'TUNTAS'])->assertOk();
        $this->assertStatus('TUNTAS');

        // --- 6. Monev hasil: Dekan menunjuk dosen GJM, reviewer monev mengisi borang.
        $this->masuk($dekan);
        $this->getJson('/api/v1/dkn/monev')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/v1/dkn/monev/{$id}/kandidat")->assertOk()->assertJsonPath('data.*.kodeperson', ['GJM01']);
        $this->postJson("/api/v1/dkn/monev/{$id}/penunjukan", ['kodeperson' => 'GJM01'])->assertOk();

        $this->masuk($gjm);
        $this->get('/pen/monev-hasil')->assertOk()->assertProp('items.0.id', $id);
        foreach (range(1, 8) as $nomor) {
            $this->put("/pen/monev-hasil/{$id}/jawaban", ['nomor' => $nomor, 'jawaban' => 'B'])->assertAksiBerhasil();
        }
        $this->post("/pen/monev-hasil/{$id}/kesimpulan", ['kesimpulan' => 'Sesuai target', 'isFinal' => true])
            ->assertAksiBerhasil('Data sudah disimpan');
        $this->assertDatabaseHas('penelitian_monevhasil', ['IDPARENT' => $id, 'ISFINAL' => 1]);

        $this->assertStatus('TUNTAS');
    }

    /**
     * Helper: buat usulan lengkap (tim setuju, lembar pengesahan final, naskah final)
     * dan kembalikan id-nya. Ketua dan anggota harus sudah dibuat sebelumnya
     * dan $this->ketua harus menunjuk ketua.
     */
    private function usulanSiapDekan(User $anggota): int
    {
        $this->masuk($this->ketua);
        $this->post('/pen/penelitian', [
            'judul' => 'Sensor IoT Kualitas Air', 'skimKode' => 'INT01', 'sumberDanaKode' => 'INTERNAL', 'biayaUsulan' => 5000000, 'komposisiBahanPeralatan' => 50, 'komposisiPerjalanan' => 15, 'komposisiLaporan' => 5,
            'anggotaDosen' => [['npp' => $anggota->kodeperson]],
        ])->assertAksiBerhasil();
        $id = (int) Penelitian::max('id');

        $this->masuk($anggota);
        $timId = PenelitianTim::where('IDPARENT', $id)->where('NIKNIDN', $anggota->kodeperson)->value('id');
        $this->post("/pen/kesediaan-tim/{$timId}/setuju")->assertAksiBerhasil();

        $this->masuk($this->ketua);
        $pen = "/pen/penelitian/{$id}";
        $this->put("{$pen}/dana-penyertaan", ['danaMitra' => 0, 'danaInkind' => 0])->assertAksiBerhasil();
        $this->post("{$pen}/lembar-pengesahan")->assertAksiBerhasil();
        $this->post("{$pen}/lembar-pengesahan/final")->assertAksiBerhasil();
        $this->post("{$pen}/dokumen-proposal", [
            'dokumenProposal' => UploadedFile::fake()->create('proposal.pdf', 300, 'application/pdf'),
        ])->assertAksiBerhasil();
        $this->post("{$pen}/dokumen-proposal/final")->assertAksiBerhasil();

        return $id;
    }

    /**
     * Helper: Dekan setujui, admin plot rev1+rev2, keduanya bersedia.
     * Kembalikan id setelah status REVIEW.
     *
     * @param  array{User, User, User, User}  $aktor  [dekan, admin, rev1, rev2]
     */
    private function usulanSiapPenilaian(int $id, User $dekan, User $admin, User $rev1, User $rev2): void
    {
        $this->masuk($dekan);
        $this->postJson("/api/v1/dkn/proposal/{$id}/approve", ['catatan' => 'OK'])->assertOk();

        $this->masuk($admin);
        $this->postJson("/api/v1/adm/plotting/{$id}/reviewer", [
            'reviewer1Id' => $rev1->kodeperson, 'reviewer2Id' => $rev2->kodeperson,
        ])->assertOk();
        $this->postJson("/api/v1/adm/plotting/{$id}/finalize")->assertOk();

        foreach ([$rev1, $rev2] as $rev) {
            $this->masuk($rev);
            $this->postJson("/api/v1/rev/penugasan/{$id}/kesediaan", ['bersedia' => true])->assertOk();
        }
    }

    public function test_alur_kedua_reviewer_lolos_langsung_tanpa_revisi(): void
    {
        $izinPeneliti = ['view penelitian', 'create penelitian', 'submit laporan akhir penelitian', 'submit monev penelitian'];
        $izinReviewer = ['view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan'];

        $this->ketua = $ketua = $this->pengguna('PEN01', 'Dr. Ketua', $izinPeneliti);
        $anggota = $this->pengguna('PEN02', 'Dr. Anggota', $izinPeneliti);
        $dekan = $this->pengguna('DKN01', 'Prof. Dekan', ['view penelitian', 'approve penelitian', 'reject penelitian']);
        $admin = $this->pengguna('ADM01', 'Admin LPPM', ['view plotting', 'assign reviewer plotting', 'finalize reviewer plotting']);
        $reviewer1 = $this->pengguna('REV01', 'Reviewer Satu', $izinReviewer);
        $reviewer2 = $this->pengguna('REV02', 'Reviewer Dua', $izinReviewer);

        $this->id = $id = $this->usulanSiapDekan($anggota);
        $this->usulanSiapPenilaian($id, $dekan, $admin, $reviewer1, $reviewer2);

        // Kedua reviewer memberi skor LOLOS tanpa komentar revisi.
        $skor = [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 7]];
        $this->masuk($reviewer1);
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
        ])->assertOk();

        $this->masuk($reviewer2);
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
        ])->assertOk();

        // Tidak ada revisi — langsung ke FINAL_APPROVAL.
        $this->assertStatus('FINAL_APPROVAL');
    }

    public function test_alur_konflik_skor_butuh_reviewer_pembanding(): void
    {
        $izinPeneliti = ['view penelitian', 'create penelitian'];
        $izinReviewer = ['view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan'];

        $this->ketua = $ketua = $this->pengguna('PEN01', 'Dr. Ketua', $izinPeneliti);
        $anggota = $this->pengguna('PEN02', 'Dr. Anggota', $izinPeneliti);
        $dekan = $this->pengguna('DKN01', 'Prof. Dekan', ['view penelitian', 'approve penelitian', 'reject penelitian']);
        $admin = $this->pengguna('ADM01', 'Admin LPPM', [
            'view plotting', 'assign reviewer plotting', 'finalize reviewer plotting', 'add reviewer plotting',
        ]);
        $reviewer1 = $this->pengguna('REV01', 'Reviewer Satu', $izinReviewer);
        $reviewer2 = $this->pengguna('REV02', 'Reviewer Dua', $izinReviewer);
        $pembanding = $this->pengguna('REV03', 'Reviewer Pembanding', $izinReviewer);

        $this->id = $id = $this->usulanSiapDekan($anggota);
        $this->usulanSiapPenilaian($id, $dekan, $admin, $reviewer1, $reviewer2);

        // Rev1 skor tinggi (700), Rev2 skor rendah (480) — selisih ≥200 → ISBUTUHREVIEWERKETIGA.
        $this->masuk($reviewer1);
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 7]],
            'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
        ])->assertOk();

        $this->masuk($reviewer2);
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 5], ['nomor' => 2, 'skor' => 5]],
            'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'REVISI',
        ])->assertOk();

        // Selisih skor ≥ 200 → ISBUTUHREVIEWERKETIGA harus true.
        $this->masuk($this->ketua);
        $this->get("/pen/penelitian/{$id}")
            ->assertOk()
            ->assertProp('proposal.isButuhReviewerKetiga', true);

        // Admin tambah pembanding; pembanding bersedia dan menilai — conflict resolved.
        $this->masuk($admin);
        $this->postJson("/api/v1/adm/plotting/{$id}/reviewer/tambah", [
            'reviewerBaruId' => 'REV03', 'isPembanding' => true,
        ])->assertOk();

        $this->masuk($pembanding);
        $this->postJson("/api/v1/rev/penugasan/{$id}/kesediaan", ['bersedia' => true])->assertOk();
        $res = $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 7]],
            'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
        ])->assertOk();

        // Pembanding memutuskan LOLOS → status FINAL_APPROVAL (bukan REVISI/TOLAK).
        $this->assertStatus('FINAL_APPROVAL');
    }

    public function test_alur_revisi_ditolak_siklus_baru_lalu_disetujui(): void
    {
        $izinPeneliti = ['view penelitian', 'create penelitian', 'submit revisi penelitian'];
        $izinReviewer = [
            'view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan',
            'view revisi queue penugasan', 'verifikasi revisi penugasan',
        ];

        $this->ketua = $ketua = $this->pengguna('PEN01', 'Dr. Ketua', $izinPeneliti);
        $anggota = $this->pengguna('PEN02', 'Dr. Anggota', $izinPeneliti);
        $dekan = $this->pengguna('DKN01', 'Prof. Dekan', ['view penelitian', 'approve penelitian', 'reject penelitian']);
        $admin = $this->pengguna('ADM01', 'Admin LPPM', [
            'view plotting', 'assign reviewer plotting', 'finalize reviewer plotting', 'assign revisi verifikator plotting',
        ]);
        $reviewer1 = $this->pengguna('REV01', 'Reviewer Satu', $izinReviewer);
        $reviewer2 = $this->pengguna('REV02', 'Reviewer Dua', $izinReviewer);
        $verifikator1 = $this->pengguna('VER01', 'Verifikator Pertama', ['view revisi queue penugasan', 'verifikasi revisi penugasan']);
        $verifikator2 = $this->pengguna('VER02', 'Verifikator Kedua', ['view revisi queue penugasan', 'verifikasi revisi penugasan']);

        $this->id = $id = $this->usulanSiapDekan($anggota);
        $this->usulanSiapPenilaian($id, $dekan, $admin, $reviewer1, $reviewer2);

        // Rev1 REVISI, Rev2 LOLOS → status REVISI.
        $skor = [['nomor' => 1, 'skor' => 6], ['nomor' => 2, 'skor' => 6]];
        $this->masuk($reviewer1);
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'REVISI',
            'komentarRevisi' => ['Perjelas latar belakang'],
        ])->assertOk();

        $this->masuk($reviewer2);
        $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
            'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
        ])->assertOk();
        $this->assertStatus('REVISI');

        // Siklus 1: peneliti submit revisi, verifikator1 TOLAK.
        $pen = "/pen/penelitian/{$id}";

        $this->masuk($admin);
        $this->postJson("/api/v1/adm/plotting/{$id}/revisi-verifikator", ['reviewerId' => 'VER01'])->assertOk();

        $this->masuk($ketua);
        $komentarId = $this->get("/pen/revisi/{$id}")->assertOk()->inertiaProp('revisi.komentar.0.id');
        $this->put("{$pen}/revisi/komentar/{$komentarId}", ['respon' => 'Sudah diperbaiki'])->assertAksiBerhasil();
        $this->post("{$pen}/revisi/dokumen", [
            'dokumenRevisi' => UploadedFile::fake()->create('revisi_v1.pdf', 300, 'application/pdf'),
        ])->assertAksiBerhasil();
        $this->post("{$pen}/revisi/final")->assertAksiBerhasil();
        $this->assertStatus('MENUNGGU_VERIFIKASI_REVISI');

        $this->masuk($verifikator1);
        $this->postJson("/api/v1/rev/penugasan/{$id}/verifikasi-revisi", [
            'status' => 'DITOLAK', 'catatan' => 'Revisi belum memadai',
        ])->assertOk();
        $this->assertStatus('REVISI');

        // Siklus 2: admin tunjuk verifikator2 (verifikator1 tidak bisa lagi),
        // peneliti upload ulang, verifikator2 setujui.
        $this->masuk($admin);
        $this->postJson("/api/v1/adm/plotting/{$id}/revisi-verifikator", ['reviewerId' => 'VER01'])
            ->assertStatus(422);
        $this->postJson("/api/v1/adm/plotting/{$id}/revisi-verifikator", ['reviewerId' => 'VER02'])->assertOk();

        $this->masuk($ketua);
        $this->post("{$pen}/revisi/dokumen", [
            'dokumenRevisi' => UploadedFile::fake()->create('revisi_v2.pdf', 300, 'application/pdf'),
        ])->assertAksiBerhasil();
        $this->post("{$pen}/revisi/final")->assertAksiBerhasil();

        $this->masuk($verifikator2);
        $this->postJson("/api/v1/rev/penugasan/{$id}/verifikasi-revisi", [
            'status' => 'DISETUJUI', 'catatan' => 'Revisi sudah sesuai',
        ])->assertOk();

        $this->assertStatus('FINAL_APPROVAL');
    }

    public function test_alur_final_approval_tidak_lolos(): void
    {
        $izinPeneliti = ['view penelitian', 'create penelitian'];
        $izinReviewer = ['view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan'];

        $this->ketua = $ketua = $this->pengguna('PEN01', 'Dr. Ketua', $izinPeneliti);
        $anggota = $this->pengguna('PEN02', 'Dr. Anggota', $izinPeneliti);
        $dekan = $this->pengguna('DKN01', 'Prof. Dekan', ['view penelitian', 'approve penelitian', 'reject penelitian']);
        $admin = $this->pengguna('ADM01', 'Admin LPPM', [
            'view plotting', 'assign reviewer plotting', 'finalize reviewer plotting',
            'view final approval', 'decide final approval penelitian',
        ]);
        $reviewer1 = $this->pengguna('REV01', 'Reviewer Satu', $izinReviewer);
        $reviewer2 = $this->pengguna('REV02', 'Reviewer Dua', $izinReviewer);

        $this->id = $id = $this->usulanSiapDekan($anggota);
        $this->usulanSiapPenilaian($id, $dekan, $admin, $reviewer1, $reviewer2);

        // Kedua reviewer LOLOS → FINAL_APPROVAL.
        $skor = [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 6]];
        foreach ([$reviewer1, $reviewer2] as $rev) {
            $this->masuk($rev);
            $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
                'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
            ])->assertOk();
        }
        $this->assertStatus('FINAL_APPROVAL');

        // Admin putuskan TIDAK LOLOS — tidak perlu biayaDisetujui.
        $this->masuk($admin);
        $this->postJson("/api/v1/adm/final-approval/{$id}", ['status' => 'TIDAK_LOLOS'])->assertOk();
        $this->assertStatus('TIDAK_LOLOS');

        // Peneliti tidak bisa mengakses surat tugas (hanya setelah SK FINAL).
        $this->masuk($ketua);
        $this->getJson("/pen/penelitian/{$id}/surat-tugas")->assertStatus(404);
    }

    public function test_alur_generate_sk_dan_set_final_setelah_lolos(): void
    {
        $izinPeneliti = ['view penelitian', 'create penelitian'];
        $izinReviewer = ['view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan'];

        $this->ketua = $ketua = $this->pengguna('PEN01', 'Dr. Ketua', $izinPeneliti);
        $anggota = $this->pengguna('PEN02', 'Dr. Anggota', $izinPeneliti);
        $dekan = $this->pengguna('DKN01', 'Prof. Dekan', ['view penelitian', 'approve penelitian', 'reject penelitian']);
        $admin = $this->pengguna('ADM01', 'Admin LPPM', [
            'view plotting', 'assign reviewer plotting', 'finalize reviewer plotting',
            'view final approval', 'decide final approval penelitian',
        ]);
        $reviewer1 = $this->pengguna('REV01', 'Reviewer Satu', $izinReviewer);
        $reviewer2 = $this->pengguna('REV02', 'Reviewer Dua', $izinReviewer);

        $this->id = $id = $this->usulanSiapDekan($anggota);
        $this->usulanSiapPenilaian($id, $dekan, $admin, $reviewer1, $reviewer2);

        // Kedua reviewer LOLOS → FINAL_APPROVAL → admin setujui LOLOS.
        $skor = [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 7]];
        foreach ([$reviewer1, $reviewer2] as $rev) {
            $this->masuk($rev);
            $this->postJson("/api/v1/rev/penugasan/{$id}/penilaian", [
                'skor' => $skor, 'catatan' => self::CATATAN_FINAL, 'rekomendasiDana' => 10000000, 'rekomendasiStatus' => 'LOLOS',
            ])->assertOk();
        }

        $this->masuk($admin);
        $this->postJson("/api/v1/adm/final-approval/{$id}", [
            'status' => 'LOLOS', 'biayaDisetujui' => 4500000,
        ])->assertOk();
        $this->assertStatus('LOLOS');

        // Peneliti belum bisa unduh surat — belum FINAL.
        $this->masuk($ketua);
        $this->getJson("/pen/penelitian/{$id}/surat-tugas")->assertStatus(404);

        // Admin generate SK lalu set FINAL.
        $this->masuk($admin);
        $this->postJson('/api/v1/adm/surat/generate', [
            'ids' => [$id], 'nomor' => 100, 'tanggal' => '2026-10-01',
        ])->assertOk();

        // SK belum FINAL — peneliti masih tidak bisa unduh.
        $this->masuk($ketua);
        $this->getJson("/pen/penelitian/{$id}/surat-tugas")->assertStatus(404);

        // Admin set FINAL.
        $this->masuk($admin);
        $this->postJson('/api/v1/adm/surat/final', ['ids' => [$id]])->assertOk();

        // Setelah FINAL, peneliti bisa unduh Surat Tugas.
        $this->masuk($ketua);
        $this->getJson("/pen/penelitian/{$id}/surat-tugas")->assertOk();
    }
}
