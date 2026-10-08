<?php

namespace Tests\Feature\Peneliti;

use App\Models\Penelitian;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

/**
 * Requirement LPPM: "nama anggota dan ketua harus urut" -
 * PenelitianResource::anggotaDosen() harus mengurutkan berdasarkan URUTAN,
 * bukan mengandalkan urutan retrieval DB mentah.
 */
class ProposalOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
    }

    public function test_anggota_dosen_is_returned_ordered_by_urutan_even_when_inserted_out_of_order(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view penelitian');
        $this->actingAs($user, 'web');

        Person::create(['KODEPERSON' => 'P00001', 'NAMALENGKAP' => 'Anggota Urutan 2']);
        Person::create(['KODEPERSON' => 'P00002', 'NAMALENGKAP' => 'Anggota Urutan 3']);
        Person::create(['KODEPERSON' => 'P00003', 'NAMALENGKAP' => 'Anggota Urutan 4']);

        $penelitian = Penelitian::create([
            'JUDULPENELITIAN' => 'Usulan Uji Urutan Tim',
            'PERMOHONANDIBUAT_KDPERSON' => $user->kodeperson,
            'ISPENGAJUANFINAL' => 1,
            'TAHUNUSULAN' => 2026,
        ]);

        $penelitian->tim()->create(['NIKNIDN' => $user->kodeperson, 'PERAN' => 'KETUA', 'URUTAN' => 1]);

        // Insert sengaja dengan URUTAN tidak berurutan dengan urutan insert -
        // mensimulasikan retrieval DB yang tidak dijamin urut secara alami.
        $penelitian->tim()->create(['NIKNIDN' => 'P00003', 'PERAN' => 'ANGGOTA', 'URUTAN' => 4]);
        $penelitian->tim()->create(['NIKNIDN' => 'P00001', 'PERAN' => 'ANGGOTA', 'URUTAN' => 2]);
        $penelitian->tim()->create(['NIKNIDN' => 'P00002', 'PERAN' => 'ANGGOTA', 'URUTAN' => 3]);

        $response = $this->get("/pen/penelitian/{$penelitian->id}")->assertOk();

        $npps = collect($response->inertiaProp('proposal.anggotaDosen'))->pluck('npp')->all();

        $this->assertSame(['P00001', 'P00002', 'P00003'], $npps);
    }
}
