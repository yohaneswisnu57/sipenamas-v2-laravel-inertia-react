<?php

namespace Tests\Feature\Reviewer;

use App\Models\Penelitian;
use App\Models\PenelitianPenilaianproposalRevisi;
use App\Models\Person;
use App\Models\SkimPenelitian;
use App\Models\SoalPenilaianProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

/**
 * Penilaian memakai borang per skim (legacy penilaianproposaldetail.php):
 * nilai = skor x bobot%, total maksimal 700. Total < 400 membuat
 * rekomendasi reviewer TOLAK; rekap usulan & penanda reviewer ketiga
 * mengikuti penilaianproposal.php updateStatusnya. Tidak ada penolakan
 * otomatis di final approval.
 */
class PenilaianBorangTest extends TestCase
{
    use RefreshDatabase;

    /** Komentar FINAL wajib minimal 100 karakter (legacy rev/app.js). */
    private const CATATAN_FINAL = 'Proposal sudah sesuai dengan roadmap penelitian, metodologi jelas, dan anggaran wajar untuk luaran yang ditargetkan.';

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
        SpatiePermission::findOrCreate('confirm kesediaan penugasan', 'sanctum');
        SpatiePermission::findOrCreate('submit penilaian penugasan', 'sanctum');
        SpatiePermission::findOrCreate('decide final approval penelitian', 'sanctum');
        SpatiePermission::findOrCreate('view plotting', 'sanctum');
        SpatiePermission::findOrCreate('add reviewer plotting', 'sanctum');

        SkimPenelitian::create(['KODESKIM' => 'INT01', 'NAMASKIM' => 'Penelitian Dasar', 'KDSOALPENILAIANPROPOSAL' => 'S01']);
        $soal = SoalPenilaianProposal::create(['KODESOAL' => 'S01', 'DESKRIPSI' => 'Borang reguler']);
        $soal->detail()->create(['NOMOR' => 1, 'KRITERIAPENILAIAN' => 'Perumusan masalah', 'BOBOTPERSEN' => 60]);
        $soal->detail()->create(['NOMOR' => 2, 'KRITERIAPENILAIAN' => 'Metode', 'BOBOTPERSEN' => 40]);
    }

    private function proposalWithTwoReviewers(): Penelitian
    {
        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Uji Penilaian',
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'KDSKIMPENELITIAN' => 'INT01',
            'ISPENGAJUANFINAL' => 1,
            'STATUSPENUNJUKANREVIEWER' => 'FINAL',
        ]);

        $penelitian->reviewers()->create(['NIK' => 'REV001']);
        $penelitian->reviewers()->create(['NIK' => 'REV002']);

        return $penelitian;
    }

    private function actingAsReviewer(string $kodeperson): User
    {
        $user = User::factory()->create(['kodeperson' => $kodeperson]);
        $user->givePermissionTo('view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan');
        Sanctum::actingAs($user);

        return $user;
    }

    private function nilai(Penelitian $penelitian, int $skor, string $rekomendasi = 'LOLOS')
    {
        return $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => $skor], ['nomor' => 2, 'skor' => $skor]],
            'catatan' => self::CATATAN_FINAL,
            'rekomendasiDana' => 10000000,
            'rekomendasiStatus' => $rekomendasi,
        ]);
    }

    public function test_reviewer_gets_the_borang_of_the_proposal_skim(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}/borang")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.bobot', 60)
            ->assertJsonPath('data.0.skor', null);
    }

    public function test_total_is_skor_times_bobot_and_details_are_stored(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->nilai($penelitian, 7)->assertOk()->assertJsonPath('data.totalSkor', 700);

        $this->assertDatabaseHas('penelitian_reviewer', ['NIK' => 'REV001', 'TOTALSKOR' => 700, 'HASILPENILAIAN' => 'LOLOS', 'STATUSPENILAIAN' => 'FINAL']);
        $this->assertDatabaseHas('penelitian_penilaianproposal_detail', ['NOMORSOAL' => 1, 'SKOR' => 7, 'NILAI' => 420]);
        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}/borang")->assertJsonPath('data.1.skor', 7);
    }

    public function test_penilaian_can_only_be_submitted_once_and_is_shown_read_only(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}")->assertJsonPath('data.penilaianSaya.isFinal', false);

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 6], ['nomor' => 2, 'skor' => 6]],
            'catatan' => self::CATATAN_FINAL,
            'rekomendasiDana' => 10000000,
            'rekomendasiStatus' => 'LOLOS',
            'komentarRevisi' => ['Perjelas metodologi'],
        ])->assertOk();

        $this->nilai($penelitian, 7)->assertStatus(422);

        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}")
            ->assertJsonPath('data.penilaianSaya.isFinal', true)
            ->assertJsonPath('data.penilaianSaya.totalSkor', 600)
            ->assertJsonPath('data.penilaianSaya.hasil', 'PERBAIKAN')
            ->assertJsonPath('data.penilaianSaya.catatan', self::CATATAN_FINAL)
            ->assertJsonPath('data.penilaianSaya.komentarRevisi', ['Perjelas metodologi']);
        $this->assertDatabaseHas('penelitian_reviewer', ['NIK' => 'REV001', 'TOTALSKOR' => 600]);
    }

    public function test_draft_penilaian_is_not_locked_and_not_aggregated(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $draft = $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 6], ['nomor' => 2, 'skor' => 6]],
            'catatan' => 'Draf awal',
            'rekomendasiStatus' => 'LOLOS',
            'status' => 'DRAFT',
        ]);
        $draft->assertOk()->assertJsonPath('data.status', 'DRAFT');

        $this->assertDatabaseHas('penelitian_reviewer', ['NIK' => 'REV001', 'STATUSPENILAIAN' => 'DRAFT']);
        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}")->assertJsonPath('data.penilaianSaya.isFinal', false);

        // Draf masih bisa diubah (tidak 422) dan belum masuk rekap.
        $this->nilai($penelitian, 7)->assertOk();
        $this->assertDatabaseHas('penelitian_reviewer', ['NIK' => 'REV001', 'STATUSPENILAIAN' => 'FINAL', 'TOTALSKOR' => 700]);
    }

    public function test_draft_may_be_partial_and_is_hidden_from_proposal_resource(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 6]],
            'status' => 'DRAFT',
            'komentarRevisi' => ['Perjelas metodologi'],
        ])->assertOk()->assertJsonPath('data.totalSkor', 360);

        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}/borang")
            ->assertJsonPath('data.0.skor', 6)
            ->assertJsonPath('data.1.skor', null);

        // Draf berhasil PERBAIKAN tetapi belum boleh tampil sebagai catatan revisi.
        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}")->assertJsonPath('data.catatanRevisi', null);

        // FINAL tetap wajib lengkap.
        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 6]],
            'catatan' => self::CATATAN_FINAL,
            'rekomendasiDana' => 10000000,
            'rekomendasiStatus' => 'LOLOS',
        ])->assertUnprocessable()->assertJsonValidationErrors('skor');
    }

    public function test_panduan_penilaian_is_downloadable_when_file_exists(): void
    {
        Storage::fake('legacy_res');
        $this->actingAsReviewer('REV001');

        $this->get('/api/v1/rev/panduan-penilaian')->assertNotFound();

        Storage::disk('legacy_res')->put('BUTIRPENILAIAN.docx', 'rubrik');
        $this->get('/api/v1/rev/panduan-penilaian')->assertOk()->assertDownload('Panduan_Penilaian.docx');
    }

    public function test_every_criterion_must_be_scored(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 7]],
            'catatan' => self::CATATAN_FINAL,
            'rekomendasiDana' => 10000000,
            'rekomendasiStatus' => 'LOLOS',
        ])->assertUnprocessable()->assertJsonValidationErrors('skor');
    }

    public function test_final_penilaian_needs_a_comment_of_at_least_100_characters(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 7]],
            'catatan' => str_repeat('a', 99),
            'rekomendasiDana' => 10000000,
            'rekomendasiStatus' => 'LOLOS',
        ])->assertUnprocessable()->assertJsonValidationErrors(['catatan' => 'Komentar minimal berisi 100 karakter.']);
    }

    #[TestWith([0])]
    #[TestWith([null])]
    public function test_final_penilaian_needs_a_rekomendasi_biaya(?int $rekomendasiDana): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", [
            'skor' => [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 7]],
            'catatan' => self::CATATAN_FINAL,
            'rekomendasiDana' => $rekomendasiDana,
            'rekomendasiStatus' => 'LOLOS',
        ])->assertUnprocessable()->assertJsonValidationErrors(['rekomendasiDana' => 'Mohon diisi rekomendasi biayanya.']);
    }

    public function test_skor_4_is_not_a_valid_option(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->nilai($penelitian, 4)->assertUnprocessable()->assertJsonValidationErrors('skor.0.skor');
    }

    public function test_total_below_400_forces_tolak_recommendation(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->nilai($penelitian, 3)->assertOk()->assertJsonPath('data.hasilPenilaian', 'TOLAK');
    }

    public function test_revisi_recommendation_is_stored_as_perbaikan(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->nilai($penelitian, 6, 'REVISI')->assertOk()->assertJsonPath('data.hasilPenilaian', 'PERBAIKAN');
    }

    public function test_proposal_status_is_aggregated_once_all_reviewers_finish(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();

        $this->actingAsReviewer('REV001');
        $this->nilai($penelitian, 7);
        $this->assertNull($penelitian->fresh()->STATUSPENILAIANREVIEWER);

        $this->actingAsReviewer('REV002');
        $this->nilai($penelitian, 5, 'REVISI');

        $penelitian->refresh();
        $this->assertSame('PERBAIKAN', $penelitian->STATUSPENILAIANREVIEWER);
        $this->assertEquals(600, $penelitian->SKORAKHIR);
        $this->assertNull($penelitian->STATUSFINALAPPROVAL);
    }

    public function test_one_tolak_reviewer_makes_the_proposal_tolak_but_admin_still_decides(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();

        $this->actingAsReviewer('REV001');
        $this->nilai($penelitian, 7);
        $this->actingAsReviewer('REV002');
        $this->nilai($penelitian, 2);

        $this->assertSame('TOLAK', $penelitian->fresh()->STATUSPENILAIANREVIEWER);

        $admin = User::factory()->create();
        $admin->givePermissionTo('decide final approval penelitian');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/adm/final-approval/{$penelitian->id}", [
            'status' => 'LOLOS',
            'biayaDisetujui' => 10000000,
        ])->assertOk();
    }

    public function test_score_gap_of_200_flags_the_need_for_a_third_reviewer(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();

        $this->actingAsReviewer('REV001');
        $this->nilai($penelitian, 7); // 700
        $this->actingAsReviewer('REV002');
        $this->nilai($penelitian, 5); // 500

        $this->assertTrue($penelitian->fresh()->ISBUTUHREVIEWERKETIGA);
    }

    public function test_declined_reviewer_is_excluded_from_aggregation(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();

        $this->actingAsReviewer('REV001');
        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/kesediaan", ['bersedia' => false])->assertOk();

        Person::create(['KODEPERSON' => 'REV003', 'NAMALENGKAP' => 'Reviewer REV003']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('view plotting', 'add reviewer plotting');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/adm/plotting/{$penelitian->id}/reviewer/tambah", ['reviewerBaruId' => 'REV003'])->assertOk();

        $this->actingAsReviewer('REV002');
        $this->nilai($penelitian, 7);
        $this->assertNull($penelitian->fresh()->SKORAKHIR);

        $this->actingAsReviewer('REV003');
        $this->nilai($penelitian, 6);

        $penelitian->refresh();
        $this->assertEquals(650, $penelitian->SKORAKHIR);
        $this->assertSame('LANJUT', $penelitian->STATUSPENILAIANREVIEWER);
    }

    public function test_reviewer_revises_proposal_title_and_old_title_is_backed_up(): void
    {
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001');

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/revisi-judul", ['judulBaru' => 'Judul Hasil Revisi'])
            ->assertOk()
            ->assertJsonPath('data.judulBaru', 'Judul Hasil Revisi')
            ->assertJsonPath('data.judulLama', 'Usulan Uji Penilaian');

        $this->assertDatabaseHas('penelitian', [
            'id' => $penelitian->id,
            'JUDULPENELITIAN' => 'Judul Hasil Revisi',
            'JUDULPENELITIAN_YGLAMA' => 'Usulan Uji Penilaian',
        ]);

        // Revisi kedua: cadangan judul asli dipertahankan, tidak ditimpa.
        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/revisi-judul", ['judulBaru' => 'Judul Revisi Kedua'])
            ->assertOk()
            ->assertJsonPath('data.judulLama', 'Usulan Uji Penilaian');

        $this->assertDatabaseHas('penelitian', [
            'id' => $penelitian->id,
            'JUDULPENELITIAN' => 'Judul Revisi Kedua',
            'JUDULPENELITIAN_YGLAMA' => 'Usulan Uji Penilaian',
        ]);
    }

    public function test_komentar_revisi_are_stored_per_item_and_make_the_result_perbaikan(): void
    {
        SpatiePermission::findOrCreate('view revisi queue penugasan', 'sanctum');
        $penelitian = $this->proposalWithTwoReviewers();
        $this->actingAsReviewer('REV001')->givePermissionTo('view revisi queue penugasan');
        $payload = [
            'skor' => [['nomor' => 1, 'skor' => 7], ['nomor' => 2, 'skor' => 7]],
            'catatan' => self::CATATAN_FINAL,
            'rekomendasiDana' => 10000000,
            'rekomendasiStatus' => 'LOLOS',
        ];

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", $payload + [
            'komentarRevisi' => ['Perjelas metodologi', 'Rasionalisasi anggaran'],
        ])->assertOk()->assertJsonPath('data.hasilPenilaian', 'PERBAIKAN');

        $this->assertDatabaseCount('penelitian_penilaianproposal_revisi', 2);
        PenelitianPenilaianproposalRevisi::where('KOMENREVISI', 'Perjelas metodologi')->update(['KOMENRESPON' => 'Sudah']);

        $this->postJson("/api/v1/rev/penugasan/{$penelitian->id}/penilaian", $payload + [
            'komentarRevisi' => ['Perjelas metodologi', 'Tambah pustaka'],
        ])->assertStatus(422);

        $this->getJson("/api/v1/rev/penugasan/{$penelitian->id}/komentar-revisi")
            ->assertOk()
            ->assertJsonPath('data.*.komentar', ['Perjelas metodologi', 'Rasionalisasi anggaran'])
            ->assertJsonPath('data.0.respon', 'Sudah');
    }
}
