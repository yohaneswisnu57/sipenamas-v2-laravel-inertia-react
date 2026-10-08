<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\PenelitianMonevHasil;
use App\Models\SoalMonevAbdimas;
use App\Models\SoalMonevPenelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class MonevHasilTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('submit monev penelitian', 'sanctum');

        $this->reviewer = User::factory()->create();
        $this->reviewer->givePermissionTo('submit monev penelitian');
        $this->actingAs($this->reviewer, 'web');

        foreach (range(1, 8) as $nomor) {
            SoalMonevPenelitian::create($this->soal($nomor));
        }

        foreach (range(1, 7) as $nomor) {
            SoalMonevAbdimas::create($this->soal($nomor));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function soal(int $nomor): array
    {
        return [
            'NOMOR' => $nomor,
            'ASPEKPENILAIAN' => "Aspek {$nomor}",
            'PIL01' => 'Tidak bisa menjawab',
            'PIL02' => 'Tidak konsisten',
            'PIL03' => 'Konsisten',
            'PIL04' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $jawaban
     */
    private function penugasan(?string $reviewer = null, array $jawaban = [], string $jenis = 'PENELITIAN'): Penelitian
    {
        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Penelitian Dimonev',
            'JENIS_PA' => $jenis,
            'PERMOHONANDIBUAT_KDPERSON' => 'P00099',
            'MONEVHASILBY' => $reviewer ?? $this->reviewer->kodeperson,
        ]);

        PenelitianMonevHasil::create(array_merge(['IDPARENT' => $penelitian->id], $jawaban));

        return $penelitian;
    }

    /**
     * @return array<string, string>
     */
    private function semuaJawaban(): array
    {
        return collect(range(1, 8))->mapWithKeys(fn (int $nomor) => [sprintf('JAWAB%02d', $nomor) => 'C'])->all();
    }

    public function test_reviewer_monev_sees_only_penelitian_assigned_to_them(): void
    {
        $milikSaya = $this->penugasan();
        $this->penugasan('P00077');

        $response = $this->get('/pen/monev-hasil')->assertOk();

        $this->assertSame([$milikSaya->id], $response->inertiaProp('items.*.id'));
    }

    public function test_show_returns_questions_with_non_blank_choices_and_saved_answers(): void
    {
        $penelitian = $this->penugasan(jawaban: ['JAWAB02' => 'B']);

        $this->get("/pen/monev-hasil?borang={$penelitian->id}")
            ->assertOk()
            ->assertPropCount(8, 'borang.soal')
            ->assertProp('borang.soal.0.pilihan.*.kode', ['A', 'B', 'C'])
            ->assertProp('borang.soal.0.jawaban', null)
            ->assertProp('borang.soal.1.jawaban', 'B');
    }

    public function test_reviewer_monev_saves_an_answer_per_question(): void
    {
        $penelitian = $this->penugasan();

        $this->put("/pen/monev-hasil/{$penelitian->id}/jawaban", ['nomor' => 3, 'jawaban' => 'C'])
            ->assertAksiBerhasil();

        $this->assertDatabaseHas('penelitian_monevhasil', ['IDPARENT' => $penelitian->id, 'JAWAB03' => 'C']);
    }

    public function test_final_is_refused_while_an_answer_is_empty_but_kesimpulan_is_kept(): void
    {
        $jawaban = $this->semuaJawaban();
        unset($jawaban['JAWAB08']);
        $penelitian = $this->penugasan(jawaban: $jawaban);

        $this->post("/pen/monev-hasil/{$penelitian->id}/kesimpulan", [
            'kesimpulan' => 'Sesuai target', 'isFinal' => true,
        ])->assertAksiBerhasil('Kesimpulan disimpan, tetapi belum final karena masih ada jawaban kosong');

        $this->assertDatabaseHas('penelitian_monevhasil', [
            'IDPARENT' => $penelitian->id, 'KESIMPULAN' => 'Sesuai target', 'ISFINAL' => 0,
        ]);
    }

    public function test_final_succeeds_when_all_eight_answers_are_filled(): void
    {
        $penelitian = $this->penugasan(jawaban: $this->semuaJawaban());

        $this->post("/pen/monev-hasil/{$penelitian->id}/kesimpulan", [
            'kesimpulan' => 'Sesuai target', 'isFinal' => true,
        ])->assertAksiBerhasil('Data sudah disimpan');

        $this->assertDatabaseHas('penelitian_monevhasil', ['IDPARENT' => $penelitian->id, 'ISFINAL' => 1]);
    }

    public function test_abdimas_borang_uses_its_own_master_soal(): void
    {
        $penelitian = $this->penugasan(jenis: 'ABDIMAS');

        $this->get("/pen/monev-hasil?borang={$penelitian->id}&jenis=ABDIMAS")
            ->assertOk()
            ->assertPropCount(7, 'borang.soal')
            ->assertProp('borang.jenis', 'ABDIMAS');
    }

    public function test_abdimas_final_needs_only_its_seven_answers(): void
    {
        $jawaban = $this->semuaJawaban();
        unset($jawaban['JAWAB08']);
        $penelitian = $this->penugasan(jawaban: $jawaban, jenis: 'ABDIMAS');

        $this->post("/pen/monev-hasil/{$penelitian->id}/kesimpulan", [
            'kesimpulan' => 'Sesuai target', 'isFinal' => true, 'jenis' => 'ABDIMAS',
        ])->assertRedirect('/pen/monev-hasil?jenis=ABDIMAS')->assertAksiBerhasil('Data sudah disimpan');

        $this->assertDatabaseHas('penelitian_monevhasil', ['IDPARENT' => $penelitian->id, 'ISFINAL' => 1]);
    }

    public function test_abdimas_penugasan_is_hidden_from_the_penelitian_list(): void
    {
        $penelitian = $this->penugasan();
        $abdimas = $this->penugasan(jenis: 'ABDIMAS');

        $this->assertSame([$penelitian->id], $this->get('/pen/monev-hasil')->inertiaProp('items.*.id'));
        $this->assertSame([$abdimas->id], $this->get('/pen/monev-hasil?jenis=ABDIMAS')->inertiaProp('items.*.id'));
    }

    public function test_non_assigned_user_cannot_fill_monev(): void
    {
        $penelitian = $this->penugasan('P00077');

        $this->put("/pen/monev-hasil/{$penelitian->id}/jawaban", ['nomor' => 1, 'jawaban' => 'A'])
            ->assertAksiDitolak('Data tidak ditemukan.');
    }
}
