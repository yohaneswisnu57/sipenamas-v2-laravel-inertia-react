<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditPersonUserLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_kodeperson_codes_that_cannot_be_resolved_to_any_user(): void
    {
        $activeUser = User::factory()->create(['kodeperson' => 'P_ACTIVE']);
        DB::table('user_kodeperson_aliases')->insert([
            'kodeperson' => 'P_ALIAS',
            'user_id' => $activeUser->id,
        ]);

        // Resolvable langsung by kodeperson.
        DB::table('penelitian')->insert(['PERMOHONANDIBUAT_KDPERSON' => 'P_ACTIVE']);
        // Resolvable lewat alias NIP lama.
        DB::table('hki_peserta')->insert(['NIP' => 'P_ALIAS']);
        // TIDAK resolvable - tidak ada User maupun alias untuk kode ini.
        DB::table('insentif')->insert(['KDPERSONPENGAJU' => 'P_GHOST']);

        $this->artisan('rbac:audit-person-user-links')
            ->assertExitCode(0)
            ->expectsOutputToContain('P_GHOST')
            ->expectsOutputToContain('1 tidak resolvable ke User manapun');
    }

    public function test_reports_zero_unresolved_when_everything_matches(): void
    {
        $user = User::factory()->create(['kodeperson' => 'P_OK']);
        DB::table('penelitian')->insert(['PERMOHONANDIBUAT_KDPERSON' => $user->kodeperson]);

        $this->artisan('rbac:audit-person-user-links')
            ->assertExitCode(0)
            ->expectsOutputToContain('0 tidak resolvable ke User manapun');
    }
}
