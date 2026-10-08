<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\PenelitianTim;
use App\Models\Periode;
use App\Models\Person;
use App\Models\SkimPenelitian;
use App\Models\SumberDana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class DraftProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        SumberDana::create(['KODESUMBERDANA' => 'INTERNAL', 'NAMASUMBERDANA' => 'Dana Internal']);

        SpatiePermission::findOrCreate('create penelitian', 'sanctum');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');

        SkimPenelitian::create(['KODESKIM' => 'PDP', 'NAMASKIM' => 'Penelitian Dosen Pemula', 'ISAKTIF' => 1, 'ISABDIMAS' => 0]);
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1]);
        Person::create(['KODEPERSON' => 'A001', 'NAMALENGKAP' => 'Anggota Satu']);
        Person::create(['KODEPERSON' => 'A002', 'NAMALENGKAP' => 'Anggota Dua']);

        $this->ketua = User::factory()->create();
        $this->ketua->givePermissionTo('create penelitian', 'view penelitian');
        $this->actingAs($this->ketua, 'web');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['judul' => 'Judul Awal', 'skimKode' => 'PDP', 'sumberDanaKode' => 'INTERNAL', 'biayaUsulan' => 5000000, 'komposisiBahanPeralatan' => 50, 'komposisiPerjalanan' => 15, 'komposisiLaporan' => 5], $overrides);
    }

    private function buatDraft(array $overrides = []): int
    {
        return $this->simpanUsulan($this->payload(['ajukan' => false] + $overrides));
    }

    /**
     * Simpan usulan lewat form; id usulan baru diambil dari database karena
     * aksi web menjawab redirect, bukan data usulan.
     *
     * @param  array<string, mixed>  $payload
     */
    private function simpanUsulan(array $payload): int
    {
        $this->post('/pen/penelitian', $payload)->assertAksiBerhasil();

        return (int) Penelitian::max('id');
    }

    public function test_usulan_can_be_saved_as_draft(): void
    {
        $id = $this->buatDraft();

        $this->assertDatabaseHas('penelitian', ['id' => $id, 'ISPENGAJUANFINAL' => 0]);
        $this->get("/pen/penelitian/{$id}")
            ->assertProp('proposal.status', 'DRAFT')
            ->assertProp('proposal.isPengajuanFinal', false);
    }

    public function test_ketua_edits_a_draft_and_submits_it(): void
    {
        $id = $this->buatDraft(['anggotaDosen' => [['npp' => 'A001']]]);

        $this->put("/pen/penelitian/{$id}", $this->payload([
            'judul' => 'Judul Baru',
            'ajukan' => true,
            'anggotaDosen' => [['npp' => 'A002', 'tugas' => 'Analisis']],
        ]))->assertAksiBerhasil('Usulan diperbarui');

        $this->assertDatabaseHas('penelitian', ['id' => $id, 'JUDULPENELITIAN' => 'Judul Baru', 'ISPENGAJUANFINAL' => 1]);
        $this->assertDatabaseMissing('penelitian_tim', ['IDPARENT' => $id, 'NIKNIDN' => 'A001']);
        $this->assertDatabaseHas('penelitian_tim', ['IDPARENT' => $id, 'NIKNIDN' => 'A002', 'PERAN' => 'ANGGOTA', 'ISAPPROVED' => 0]);
        $this->assertSame(1, PenelitianTim::where('IDPARENT', $id)->where('PERAN', 'KETUA')->count());
    }

    public function test_submitted_usulan_can_no_longer_be_edited(): void
    {
        $id = $this->simpanUsulan($this->payload());

        $this->put("/pen/penelitian/{$id}", $this->payload(['judul' => 'Ubah']))->assertAksiDitolak();

        $this->assertDatabaseHas('penelitian', ['id' => $id, 'JUDULPENELITIAN' => 'Judul Awal']);
    }

    public function test_draft_cannot_upload_dokumen_proposal(): void
    {
        Storage::fake('legacy_res');
        $id = $this->buatDraft();

        $this->post("/pen/penelitian/{$id}/dokumen-proposal", [
            'dokumenProposal' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ])->assertAksiDitolak();
    }

    public function test_ketua_deletes_usulan_with_its_team(): void
    {
        $id = $this->buatDraft(['anggotaDosen' => [['npp' => 'A001']]]);

        $this->delete("/pen/penelitian/{$id}")->assertAksiBerhasil();

        $this->assertDatabaseMissing('penelitian', ['id' => $id]);
        $this->assertDatabaseMissing('penelitian_tim', ['IDPARENT' => $id]);
    }

    public function test_usulan_approved_by_dekan_cannot_be_deleted(): void
    {
        $id = $this->simpanUsulan($this->payload());
        Penelitian::find($id)->update(['APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 1]);

        $this->delete("/pen/penelitian/{$id}")->assertAksiDitolak();

        $this->assertDatabaseHas('penelitian', ['id' => $id]);
    }

    public function test_anggota_cannot_edit_or_delete_the_usulan(): void
    {
        $id = $this->buatDraft();

        $anggota = User::factory()->create();
        $anggota->givePermissionTo('create penelitian', 'view penelitian');
        PenelitianTim::create(['IDPARENT' => $id, 'NIKNIDN' => $anggota->kodeperson, 'PERAN' => 'ANGGOTA']);
        $this->actingAs($anggota, 'web');

        $this->put("/pen/penelitian/{$id}", $this->payload(['judul' => 'Ubah']))->assertAksiDitolak('Data tidak ditemukan.');
        $this->delete("/pen/penelitian/{$id}")->assertAksiDitolak('Data tidak ditemukan.');
    }
}
