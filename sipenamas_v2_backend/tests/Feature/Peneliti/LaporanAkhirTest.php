<?php

namespace Tests\Feature\Peneliti;

use App\Models\Insentif;
use App\Models\Mahasiswa;
use App\Models\Penelitian;
use App\Models\PenelitianRencanatarget;
use App\Models\PenelitianTim;
use App\Models\Pengisiankuesionerpeneliti;
use App\Models\PengisiankuesionerpenelitiDetail;
use App\Models\Periode;
use App\Models\Soalkuesionerpeneliti;
use App\Models\SoalkuesionerpenelitiDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;
use ZipArchive;

class LaporanAkhirTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('legacy_res');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        SpatiePermission::findOrCreate('submit laporan akhir penelitian', 'sanctum');

        $this->ketua = $this->actingPeneliti();

        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1, 'TGLBEGIN' => '2026-01-01', 'TGLEND' => '2026-12-01']);

        $soal = Soalkuesionerpeneliti::create(['KODESOAL' => 'KP01', 'ISAKTIF' => 1, 'SKOR_1' => 'Sangat Tidak Puas', 'SKOR_4' => 'Sangat Puas']);
        foreach ([1, 2] as $nomor) {
            SoalkuesionerpenelitiDetail::create(['IDPARENT' => $soal->id, 'NOMOR' => $nomor, 'KELOMPOK' => 'A', 'URAIAN' => "Soal {$nomor}"]);
        }
    }

    private function actingPeneliti(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view penelitian', 'submit laporan akhir penelitian');
        $this->actingAs($user, 'web');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function penelitianLolos(array $overrides = [], string $peran = 'KETUA'): Penelitian
    {
        $penelitian = Penelitian::create(array_merge([
            'JUDULPENELITIAN' => 'Penelitian Lolos',
            'JENIS_PA' => 'PENELITIAN',
            'PERMOHONANDIBUAT_KDPERSON' => $this->ketua->kodeperson,
            'PERIODEKEGIATAN_TAHUN' => 2026,
            'STATUSFINALAPPROVAL' => 'LOLOS',
        ], $overrides));

        PenelitianTim::create(['IDPARENT' => $penelitian->id, 'NIKNIDN' => $this->ketua->kodeperson, 'PERAN' => $peran, 'ISAPPROVED' => 1]);

        return $penelitian;
    }

    private function isiKuesioner(): void
    {
        $pengisian = Pengisiankuesionerpeneliti::create([
            'KDPERIODE' => '2026', 'JENIS_PA' => 'PENELITIAN', 'NIK' => $this->ketua->kodeperson, 'KDSOAL' => 'KP01',
        ]);
        foreach ([1, 2] as $nomor) {
            PengisiankuesionerpenelitiDetail::create(['IDPARENT' => $pengisian->id, 'NOMORSOAL' => $nomor, 'JAWAB' => 'D', 'SKOR' => 4]);
        }
    }

    private function target(Penelitian $penelitian, array $overrides = []): PenelitianRencanatarget
    {
        return PenelitianRencanatarget::create(array_merge([
            'IDPARENT' => $penelitian->id, 'KATEGORI' => 'Unggah laporan penelitian', 'ISWAJIB' => 1, 'URUTAN' => 70, 'ISCHKTARGET' => 1,
        ], $overrides));
    }

    public function test_list_shows_lolos_penelitian_of_the_period_where_user_is_in_the_team(): void
    {
        $milik = $this->penelitianLolos();
        $this->penelitianLolos(['PERIODEKEGIATAN_TAHUN' => 2025]);
        $this->penelitianLolos(['STATUSFINALAPPROVAL' => 'TIDAK LOLOS']);
        Penelitian::create(['JUDULPENELITIAN' => 'Orang lain', 'JENIS_PA' => 'PENELITIAN', 'PERIODEKEGIATAN_TAHUN' => 2026, 'STATUSFINALAPPROVAL' => 'LOLOS']);

        $this->get('/pen/laporan-akhir?kdperiode=2026')
            ->assertOk()
            ->assertProp('laporan.items.*.id', [$milik->id])
            ->assertProp('laporan.items.0.peran', 'KETUA')
            ->assertProp('laporan.isKuesionerSelesai', false);
    }

    public function test_kuesioner_rows_are_created_and_completing_them_marks_done(): void
    {
        $response = $this->get('/pen/laporan-akhir?kdperiode=2026&kuesioner=1')
            ->assertOk()
            ->assertPropCount(2, 'kuesioner.pertanyaan')
            ->assertProp('kuesioner.isDone', false);

        [$pertama, $kedua] = $response->inertiaProp('kuesioner.pertanyaan.*.id');
        $kuesioner = '/pen/laporan-akhir?kdperiode=2026&kuesioner=1';

        $this->put("/pen/kuesioner-penelitian/{$pertama}", ['jawab' => 'C'])->assertAksiBerhasil('Jawaban disimpan');
        $this->get($kuesioner)->assertProp('kuesioner.isDone', false);
        $this->put("/pen/kuesioner-penelitian/{$kedua}", ['jawab' => 'A'])->assertAksiBerhasil();
        $this->get($kuesioner)->assertProp('kuesioner.isDone', true);

        $this->assertDatabaseHas('pengisiankuesionerpeneliti_detail', ['id' => $pertama, 'JAWAB' => 'C', 'SKOR' => 3]);
        $this->assertDatabaseHas('pengisiankuesionerpeneliti', ['NIK' => $this->ketua->kodeperson, 'JENIS_PA' => 'PENELITIAN', 'ISDONE' => 1]);
    }

    public function test_kelengkapan_requires_a_complete_kuesioner(): void
    {
        $penelitian = $this->penelitianLolos();

        $this->get("/pen/laporan-akhir?kelengkapan={$penelitian->id}")->assertProp('kelengkapan.error', 'Silahkan melengkapi kuesioner terlebih dulu.');

        $this->isiKuesioner();

        $this->get("/pen/laporan-akhir?kelengkapan={$penelitian->id}")->assertOk()->assertProp('kelengkapan.isLembarPengesahanFinal', false);
    }

    public function test_kelengkapan_is_only_for_ketua(): void
    {
        $penelitian = $this->penelitianLolos(peran: 'ANGGOTA');
        $this->isiKuesioner();

        $this->get("/pen/laporan-akhir?kelengkapan={$penelitian->id}")->assertProp('kelengkapan.error', 'Maaf, fungsi ini hanya untuk KETUA.');
    }

    public function test_ketua_saves_dana_penyertaan_and_mahasiswa(): void
    {
        $penelitian = $this->penelitianLolos();
        $this->isiKuesioner();
        Mahasiswa::create(['NIM' => '5303021001', 'NAMAMAHASISWA' => 'Budi']);

        $this->put("/pen/laporan-akhir/{$penelitian->id}/dana-penyertaan", ['danaMitra' => 200000, 'danaInkind' => 100000])->assertAksiBerhasil();
        $this->post("/pen/laporan-akhir/{$penelitian->id}/mahasiswa", ['nim' => '5303021001'])->assertAksiBerhasil('Mahasiswa ditambahkan');
        $this->get("/pen/laporan-akhir?kelengkapan={$penelitian->id}")->assertProp('kelengkapan.mahasiswa.0.nama', 'Budi');

        $this->assertDatabaseHas('penelitian', [
            'id' => $penelitian->id, 'LBRPENGESAHANLAPHASIL_DANAMITRA' => 200000, 'LBRPENGESAHANLAPHASIL_DANAINKIND' => 100000,
        ]);
    }

    public function test_generate_then_final_stamps_ketua_signature_and_locks_changes(): void
    {
        $penelitian = $this->penelitianLolos();
        $this->isiKuesioner();
        $this->target($penelitian);
        Mahasiswa::create(['NIM' => '5303021001', 'NAMAMAHASISWA' => 'Budi']);

        $this->post("/pen/laporan-akhir/{$penelitian->id}/lembar-pengesahan/final")->assertAksiDitolak();

        $this->post("/pen/laporan-akhir/{$penelitian->id}/lembar-pengesahan")->assertAksiBerhasil('Lembar pengesahan laporan akhir dibuat');
        $this->get("/pen/laporan-akhir?kelengkapan={$penelitian->id}")->assertProp('kelengkapan.adaLembarPengesahan', true);

        $namaFile = "lbrpengesahan_lapakhir_{$penelitian->id}.docx";
        Storage::disk('legacy_res')->assertExists('proposal/'.$namaFile);
        $this->assertSame($namaFile, $penelitian->fresh()->LBRPENGESAHANLAPHASIL_NAMAFILE);

        $this->post("/pen/laporan-akhir/{$penelitian->id}/lembar-pengesahan/final")->assertAksiBerhasil('Lembar pengesahan berstatus final');
        $this->get("/pen/laporan-akhir?kelengkapan={$penelitian->id}")->assertProp('kelengkapan.isLembarPengesahanFinal', true);

        $zip = new ZipArchive;
        $zip->open(Storage::disk('legacy_res')->path('proposal/'.$namaFile));
        $this->assertSame(filesize(resource_path('templates/checkmark.jpg')), $zip->statName('word/media/image2.jpeg')['size']);
        $this->assertSame(filesize(resource_path('templates/kosong.jpg')), $zip->statName('word/media/image1.jpeg')['size']);
        $zip->close();

        $this->post("/pen/laporan-akhir/{$penelitian->id}/lembar-pengesahan")->assertAksiDitolak();
        $this->post("/pen/laporan-akhir/{$penelitian->id}/mahasiswa", ['nim' => '5303021001'])->assertAksiDitolak();
    }

    public function test_lembar_pengesahan_download_waits_for_dekan_approval(): void
    {
        $penelitian = $this->penelitianLolos(['LBRPENGESAHANLAPHASIL_NAMAFILE' => 'lbr.docx', 'LBRPENGESAHANLAPHASIL_ISFINAL' => 1]);
        $this->isiKuesioner();
        Storage::disk('legacy_res')->put('proposal/lbr.docx', 'docx');

        $this->getJson("/pen/laporan-akhir/{$penelitian->id}/lembar-pengesahan")->assertStatus(422);

        $penelitian->update(['ISDEKANAPPROVELAPORANAKHIR' => 1]);

        $this->get("/pen/laporan-akhir/{$penelitian->id}/lembar-pengesahan")->assertOk();
    }

    public function test_capaian_opens_only_after_lembar_pengesahan_is_final(): void
    {
        $penelitian = $this->penelitianLolos();
        $this->target($penelitian);
        $this->target($penelitian, ['KATEGORI' => 'Tidak dicentang', 'ISCHKTARGET' => 0]);

        $this->get("/pen/laporan-akhir?capaian={$penelitian->id}")->assertProp('capaian.error', 'Maaf, Laporan belum lengkap/Final.');

        $penelitian->update(['LBRPENGESAHANLAPHASIL_ISFINAL' => 1]);

        $this->get("/pen/laporan-akhir?capaian={$penelitian->id}")->assertOk()->assertPropCount(1, 'capaian.target');
    }

    public function test_realisasi_needs_an_uploaded_document(): void
    {
        $penelitian = $this->penelitianLolos(['LBRPENGESAHANLAPHASIL_ISFINAL' => 1]);
        $target = $this->target($penelitian);
        $url = "/pen/laporan-akhir/{$penelitian->id}/capaian/{$target->id}";

        $this->put($url, ['realisasi' => true, 'keterangan' => 'Selesai'])->assertAksiDitolak();

        $this->post("{$url}/dokumen", ['dokumen' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')])
            ->assertAksiBerhasil('Upload selesai');
        $this->get("/pen/laporan-akhir?capaian={$penelitian->id}")->assertProp('capaian.target.0.adaDokumen', true);
        Storage::disk('legacy_res')->assertExists("penelitian/luaran_{$target->id}.pdf");

        $this->put($url, ['realisasi' => true, 'keterangan' => 'Selesai'])->assertAksiBerhasil();

        $this->assertDatabaseHas('penelitian_rencanatarget', [
            'id' => $target->id, 'ISCHKREALISASI' => 1, 'KETHASIL' => 'Selesai', 'FILE_DOC' => "luaran_{$target->id}.pdf", 'FILE_EXT' => 'PDF',
        ]);

        $this->delete("{$url}/dokumen")->assertAksiBerhasil();
        Storage::disk('legacy_res')->assertMissing("penelitian/luaran_{$target->id}.pdf");
    }

    public function test_realisasi_of_an_incentive_target_needs_a_final_insentif_submission(): void
    {
        $penelitian = $this->penelitianLolos(['LBRPENGESAHANLAPHASIL_ISFINAL' => 1]);
        $target = $this->target($penelitian, ['ISADAINSENTIF' => 1, 'FILE_DOC' => 'luaran_x.pdf']);
        $url = "/pen/laporan-akhir/{$penelitian->id}/capaian/{$target->id}";

        $this->put($url, ['realisasi' => true])->assertAksiDitolak();
        $this->put($url, ['realisasi' => false, 'statusTayang' => 'SUBMITTED'])->assertAksiBerhasil();
        $this->assertDatabaseHas('penelitian_rencanatarget', ['id' => $target->id, 'STATUSTAYANG' => 'SUBMITTED', 'ISCHKREALISASI' => 0]);

        Insentif::create(['IDPENELITIANREFF' => $penelitian->id, 'ISPENGAJUANFINAL' => 1]);

        $this->put($url, ['realisasi' => true])->assertAksiBerhasil();
    }
}
