<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Person;
use App\Models\SkimPenelitian;
use App\Models\SumberDana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class SubmitProposalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('create penelitian', 'sanctum');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');

        SkimPenelitian::create([
            'KODESKIM' => 'PDP',
            'NAMASKIM' => 'Penelitian Dosen Pemula',
            'MAXDANA' => 20000000,
            'ISAKTIF' => 1,
            'ISABDIMAS' => 0,
        ]);

        SumberDana::create([
            'KODESUMBERDANA' => 'INTERNAL',
            'NAMASUMBERDANA' => 'Dana Internal',
        ]);

        Periode::create([
            'KODEPERIODE' => '2026-1',
            'TAHUN' => 2026,
            'ISAKTIF' => 1,
        ]);
    }

    private function actingPeneliti(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create penelitian', 'view penelitian');
        $this->actingAs($user, 'web');

        return $user;
    }

    private function minimalPayload(): array
    {
        return [
            'judul' => 'Pengembangan Sensor IoT untuk Monitoring Kualitas Air',
            'skimKode' => 'PDP', 'sumberDanaKode' => 'INTERNAL',
            'biayaUsulan' => 15000000,
            'komposisiBahanPeralatan' => 50,
            'komposisiPerjalanan' => 15,
            'komposisiLaporan' => 5,
        ];
    }

    public function test_peneliti_can_submit_a_proposal_with_minimal_required_fields(): void
    {
        $user = $this->actingPeneliti();

        $response = $this->post('/pen/penelitian', $this->minimalPayload());

        $response->assertRedirect('/pen/penelitian')
            ->assertAksiBerhasil('Usulan tersimpan. Setelah semua anggota menyetujui, unggah dokumen proposal agar diteruskan ke Dekan');

        $this->get('/pen/penelitian')
            ->assertProp('proposals.0.judul', 'Pengembangan Sensor IoT untuk Monitoring Kualitas Air')
            ->assertProp('proposals.0.status', 'SUBMITTED');

        $this->assertDatabaseHas('penelitian', [
            'PERMOHONANDIBUAT_KDPERSON' => $user->kodeperson,
            'ISPENGAJUANFINAL' => 1,
        ]);
    }

    public function test_submitting_creates_the_ketua_tim_row_automatically(): void
    {
        $user = $this->actingPeneliti();

        $this->post('/pen/penelitian', $this->minimalPayload())->assertAksiBerhasil();

        $this->assertDatabaseHas('penelitian_tim', [
            'NIKNIDN' => $user->kodeperson,
            'PERAN' => 'KETUA',
            'URUTAN' => 1,
            'ISAPPROVED' => 1,
        ]);
    }

    public function test_submitting_with_dosen_anggota_creates_unapproved_team_rows(): void
    {
        $this->actingPeneliti();

        Person::create(['KODEPERSON' => 'P00001', 'NAMALENGKAP' => 'Dosen Anggota Satu']);
        Person::create(['KODEPERSON' => 'P00002', 'NAMALENGKAP' => 'Dosen Anggota Dua']);

        $payload = $this->minimalPayload();
        $payload['anggotaDosen'] = [
            ['npp' => 'P00001', 'tugas' => 'Analisis Data'],
            ['npp' => 'P00002', 'tugas' => 'Pengujian Lapangan'],
        ];

        $this->post('/pen/penelitian', $payload)->assertAksiBerhasil();

        $this->assertDatabaseHas('penelitian_tim', [
            'NIKNIDN' => 'P00001', 'PERAN' => 'ANGGOTA', 'URUTAN' => 2, 'ISAPPROVED' => 0,
        ]);
        $this->assertDatabaseHas('penelitian_tim', [
            'NIKNIDN' => 'P00002', 'PERAN' => 'ANGGOTA', 'URUTAN' => 3, 'ISAPPROVED' => 0,
        ]);
    }

    public function test_submitting_with_mahasiswa_creates_penelitian_mhs_rows(): void
    {
        $this->actingPeneliti();

        $payload = $this->minimalPayload();
        $payload['anggotaMahasiswa'] = [
            ['nim' => '12345678', 'peran' => 'Asisten Riset'],
        ];

        $this->post('/pen/penelitian', $payload)->assertAksiBerhasil();

        $this->assertDatabaseHas('penelitian_mhs', [
            'NIM' => '12345678',
            '_KETERANGAN' => 'Asisten Riset',
        ]);
    }

    public function test_submitting_with_mitra_creates_v2_penelitian_mitra_rows(): void
    {
        $this->actingPeneliti();

        $payload = $this->minimalPayload();
        $payload['mitra'] = [
            ['nama' => 'Dr. John Doe', 'instansi' => 'Universitas Mitra ABC', 'tugas' => 'Konsultan metodologi'],
        ];

        $this->post('/pen/penelitian', $payload)->assertAksiBerhasil();
        $id = (int) Penelitian::max('id');

        $this->assertDatabaseHas('v2_penelitian_mitra', [
            'penelitian_id' => $id,
            'nama' => 'Dr. John Doe',
            'instansi' => 'Universitas Mitra ABC',
            'tugas' => 'Konsultan metodologi',
        ]);

        $this->get("/pen/penelitian/{$id}")
            ->assertProp('proposal.mitra.0.nama', 'Dr. John Doe')
            ->assertProp('proposal.mitra.0.instansi', 'Universitas Mitra ABC');
    }

    public function test_mitra_requires_nama_and_instansi(): void
    {
        $this->actingPeneliti();

        $payload = $this->minimalPayload();
        $payload['mitra'] = [['tugas' => 'Konsultan']];

        $this->post('/pen/penelitian', $payload)
            ->assertSessionHasErrors(['mitra.0.nama', 'mitra.0.instansi']);
    }

    public function test_submitting_stores_komposisi_dana_as_percent_columns(): void
    {
        $this->actingPeneliti();

        $this->post('/pen/penelitian', $this->minimalPayload())->assertAksiBerhasil();
        $id = (int) Penelitian::max('id');

        $this->assertDatabaseHas('penelitian', [
            'id' => $id,
            'KOMPOSISIDANA_HONORARIUM' => 30,
            'KOMPOSISIDANA_BAHANPERALATAN' => 50,
            'KOMPOSISIDANA_BIAYAPERJALANAN' => 15,
            'KOMPOSISIDANA_LAPORAN' => 5,
        ]);
        $this->assertDatabaseCount('penelitian_janggaran_1honorarium', 0);
    }

    public function test_submission_fails_when_komposisi_exceeds_category_limit(): void
    {
        $this->actingPeneliti();

        foreach ([
            ['komposisiBahanPeralatan', ['komposisiBahanPeralatan' => 71, 'komposisiPerjalanan' => 0, 'komposisiLaporan' => 0]],
            ['komposisiPerjalanan', ['komposisiBahanPeralatan' => 25, 'komposisiPerjalanan' => 41, 'komposisiLaporan' => 4]],
            ['komposisiLaporan', ['komposisiBahanPeralatan' => 60, 'komposisiPerjalanan' => 4, 'komposisiLaporan' => 6]],
        ] as [$field, $komposisi]) {
            $this->post('/pen/penelitian', array_merge($this->minimalPayload(), $komposisi))
                ->assertSessionHasErrors([$field]);
        }
    }

    public function test_submission_fails_when_komposisi_total_is_not_100_percent(): void
    {
        $this->actingPeneliti();

        $this->post('/pen/penelitian', array_merge($this->minimalPayload(), [
            'komposisiBahanPeralatan' => 50, 'komposisiPerjalanan' => 10, 'komposisiLaporan' => 5,
        ]))->assertSessionHasErrors(['komposisiBahanPeralatan']);
    }

    public function test_submission_fails_validation_without_required_fields(): void
    {
        $this->actingPeneliti();

        $this->post('/pen/penelitian', [])
            ->assertSessionHasErrors(['judul', 'skimKode', 'biayaUsulan']);
    }

    public function test_submission_fails_validation_with_unknown_skim_kode(): void
    {
        $this->actingPeneliti();

        $payload = $this->minimalPayload();
        $payload['skimKode'] = 'TIDAK-ADA';

        $this->post('/pen/penelitian', $payload)
            ->assertSessionHasErrors(['skimKode']);
    }

    public function test_submission_fails_validation_with_unknown_dosen_anggota_npp(): void
    {
        $this->actingPeneliti();

        $payload = $this->minimalPayload();
        $payload['anggotaDosen'] = [['npp' => 'TIDAK-ADA']];

        $this->post('/pen/penelitian', $payload)
            ->assertSessionHasErrors(['anggotaDosen.0.npp']);
    }

    public function test_user_without_create_penelitian_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->post('/pen/penelitian', $this->minimalPayload())->assertAksiDitolak();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->post('/pen/penelitian', $this->minimalPayload())->assertRedirect('/login');

        $this->assertDatabaseCount('penelitian', 0);
    }

    public function test_index_returns_only_the_authenticated_peneliti_own_proposals(): void
    {
        $userA = $this->actingPeneliti();
        $this->post('/pen/penelitian', $this->minimalPayload())->assertAksiBerhasil();

        $this->actingAs($userB = User::factory()->create(), 'web');
        $userB->givePermissionTo('create penelitian', 'view penelitian');
        $payloadB = $this->minimalPayload();
        $payloadB['judul'] = 'Judul Milik Peneliti Lain';
        $this->post('/pen/penelitian', $payloadB)->assertAksiBerhasil();

        $this->actingAs($userA, 'web');
        $response = $this->get('/pen/penelitian');

        $response->assertOk();
        $judulList = $response->inertiaProp('proposals.*.judul');

        $this->assertContains('Pengembangan Sensor IoT untuk Monitoring Kualitas Air', $judulList);
        $this->assertNotContains('Judul Milik Peneliti Lain', $judulList);
    }

    public function test_show_returns_404_for_a_proposal_owned_by_someone_else(): void
    {
        $userA = $this->actingPeneliti();
        $this->post('/pen/penelitian', $this->minimalPayload())->assertAksiBerhasil();
        $proposalId = (int) Penelitian::max('id');

        $this->actingAs($userB = User::factory()->create(), 'web');
        $userB->givePermissionTo('create penelitian', 'view penelitian');

        $this->get("/pen/penelitian/{$proposalId}")->assertStatus(404);
    }

    public function test_list_omits_pengesahan_qr_while_detail_includes_it(): void
    {
        $this->actingPeneliti();
        $this->post('/pen/penelitian', $this->minimalPayload())->assertAksiBerhasil();
        $proposalId = (int) Penelitian::max('id');

        $this->get('/pen/penelitian')
            ->assertOk()
            ->assertProp('proposals.0.pengesahan', null);

        $this->get("/pen/penelitian/{$proposalId}")
            ->assertOk()
            ->assertProp('proposal.pengesahan.ketua.qr', fn ($qr) => str_starts_with($qr, 'data:image/svg+xml'));
    }
}
