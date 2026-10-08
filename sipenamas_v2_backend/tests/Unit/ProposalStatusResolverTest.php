<?php

namespace Tests\Unit;

use App\Domain\Proposal\ProposalStatusResolver;
use App\Enums\ProposalStatus;
use App\Models\Penelitian;
use App\Models\PenelitianReviewer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Unit test murni (tanpa DB) untuk ProposalStatusResolver - relasi
 * di-set manual via setRelation() supaya resolver tidak query database.
 * Kasus di sini adalah baseline; validasi terhadap data produksi nyata
 * tetap wajib lewat `php artisan proposal:resolve-status-report`.
 */
class ProposalStatusResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test ini tidak mem-boot AppServiceProvider, jadi Model::unguard()
        // dari sana perlu direplikasi di sini - beberapa kolom legacy yang
        // dipakai (_MSG_PENOLAKANDEKAN) berawalan underscore dan akan diam-diam
        // hilang lewat mass assignment biasa tanpa ini.
        Model::unguard();
    }

    private function proposal(array $attributes, array $relations = []): Penelitian
    {
        $penelitian = new Penelitian($attributes);

        $defaults = [
            'reviewers' => new Collection,
            'monevHasil' => new Collection,
        ];

        foreach (array_merge($defaults, $relations) as $name => $value) {
            $penelitian->setRelation($name, $value);
        }

        return $penelitian;
    }

    public function test_draft_when_not_yet_submitted(): void
    {
        $p = $this->proposal(['ISPENGAJUANFINAL' => 0]);

        $this->assertSame(ProposalStatus::DRAFT, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_submitted_when_final_but_no_further_progress(): void
    {
        $p = $this->proposal(['ISPENGAJUANFINAL' => 1]);

        $this->assertSame(ProposalStatus::SUBMITTED, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_disetujui_dekan_when_approved_but_not_yet_plotted(): void
    {
        $p = $this->proposal(['ISPENGAJUANFINAL' => 1, 'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1]);

        $this->assertSame(ProposalStatus::DISETUJUI_DEKAN, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_ditolak_dekan_when_rejection_note_present_and_not_approved(): void
    {
        $p = $this->proposal([
            'ISPENGAJUANFINAL' => 1,
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 0,
            '_MSG_PENOLAKANDEKAN' => 'Belum sesuai RIP Fakultas',
        ]);

        $this->assertSame(ProposalStatus::DITOLAK_DEKAN, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_plotted_when_reviewer_assignment_marked(): void
    {
        // Nilai nyata di dbsipenamas untuk kolom ini cuma 'DRAFT'/'FINAL',
        // tidak pernah literal 'PLOTTED' - lihat catatan validasi data di
        // App\Domain\Proposal\ProposalStatusResolver.
        $p = $this->proposal([
            'ISPENGAJUANFINAL' => 1,
            'STATUSPENUNJUKANREVIEWER' => 'FINAL',
        ]);

        $this->assertSame(ProposalStatus::PLOTTED, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_review_when_a_reviewer_has_accepted_assignment(): void
    {
        $p = $this->proposal(
            ['ISPENGAJUANFINAL' => 1, 'STATUSPENUNJUKANREVIEWER' => 'FINAL'],
            ['reviewers' => new Collection([new PenelitianReviewer(['ISAPPROVED' => 1])])]
        );

        $this->assertSame(ProposalStatus::REVIEW, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_revisi_when_statuspenilaianreviewer_needs_perbaikan(): void
    {
        // Nilai nyata di dbsipenamas tidak pernah mengandung substring
        // 'REVISI' - yang dipakai adalah 'PERBAIKAN' / 'TOLAK (BELUM PERBAIKAN)'.
        $p = $this->proposal([
            'ISPENGAJUANFINAL' => 1,
            'STATUSPENILAIANREVIEWER' => 'PERBAIKAN',
        ]);

        $this->assertSame(ProposalStatus::REVISI, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_menunggu_verifikasi_revisi_when_active_revisi_cycle_is_menunggu_verifikasi(): void
    {
        // Peneliti sudah mengajukan ulang hasil revisi, menunggu verifikator
        // yang ditunjuk LPPM memeriksa - beda dari REVISI biasa (revisi
        // diminta tapi belum diajukan ulang).
        $p = $this->proposal(
            ['ISPENGAJUANFINAL' => 1, 'STATUSPENILAIANREVIEWER' => 'PERBAIKAN', 'ISDOKUMENPROPOSALREVISIFINAL' => 1],
            ['reviewers' => new Collection([new PenelitianReviewer(['ISREVIEWERREVISI' => 1, 'STATUSPENILAIANREVISI' => 'DRAFT'])])]
        );

        $this->assertSame(ProposalStatus::MENUNGGU_VERIFIKASI_REVISI, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_stays_revisi_when_active_revisi_cycle_is_still_menunggu_upload(): void
    {
        // Verifikator sudah ditunjuk tapi peneliti belum upload apa-apa -
        // tetap REVISI biasa, bukan MENUNGGU_VERIFIKASI_REVISI.
        $p = $this->proposal(
            ['ISPENGAJUANFINAL' => 1, 'STATUSPENILAIANREVIEWER' => 'PERBAIKAN', 'ISDOKUMENPROPOSALREVISIFINAL' => 0],
            ['reviewers' => new Collection([new PenelitianReviewer(['ISREVIEWERREVISI' => 1, 'STATUSPENILAIANREVISI' => 'DRAFT'])])]
        );

        $this->assertSame(ProposalStatus::REVISI, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_final_approval_when_all_reviewers_finalized(): void
    {
        $p = $this->proposal(
            ['ISPENGAJUANFINAL' => 1],
            ['reviewers' => new Collection([
                new PenelitianReviewer(['ISAPPROVED' => 1, 'STATUSPENILAIAN' => 'FINAL']),
                new PenelitianReviewer(['ISAPPROVED' => 1, 'STATUSPENILAIAN' => 'FINAL']),
            ])]
        );

        $this->assertSame(ProposalStatus::FINAL_APPROVAL, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_tidak_lolos_when_final_approval_rejects(): void
    {
        $p = $this->proposal(['STATUSFINALAPPROVAL' => 'TIDAK LOLOS']);

        $this->assertSame(ProposalStatus::TIDAK_LOLOS, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_lolos_when_approved_and_no_monev_yet(): void
    {
        $p = $this->proposal(['STATUSFINALAPPROVAL' => 'LOLOS']);

        $this->assertSame(ProposalStatus::LOLOS, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_monev_after_lolos_when_monev_is_filled(): void
    {
        $p = $this->proposal(
            ['STATUSFINALAPPROVAL' => 'LOLOS'],
            ['monevHasil' => new Collection([['KESIMPULAN' => 'Progres 70%']])]
        );

        $this->assertSame(ProposalStatus::MONEV, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_laporan_akhir_when_document_uploaded(): void
    {
        $p = $this->proposal([
            'STATUSFINALAPPROVAL' => 'LOLOS',
            'FILE_DOKUMENHASILPENELITIAN' => 'laporan_akhir_123.pdf',
        ]);

        $this->assertSame(ProposalStatus::LAPORAN_AKHIR, (new ProposalStatusResolver)->resolve($p));
    }

    public function test_tuntas_beats_every_other_rule(): void
    {
        $p = $this->proposal([
            'STATUSKETUNTASANPENELITIAN' => 'TUNTAS',
            'STATUSFINALAPPROVAL' => 'TIDAK LOLOS',
            'ISPENGAJUANFINAL' => 0,
        ]);

        $this->assertSame(ProposalStatus::TUNTAS, (new ProposalStatusResolver)->resolve($p));
    }
}
