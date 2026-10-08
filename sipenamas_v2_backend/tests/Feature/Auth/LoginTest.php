<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_user_logs_in_with_correct_paswet(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        $user = User::factory()->external('rahasia')->create();
        $user->assignRole('PEN');

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->kodeperson,
            'password' => 'rahasia',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.kodeperson', $user->kodeperson)
            ->assertJsonPath('data.user.activeRole', 'PEN');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('z_log_login', ['USR' => $user->kodeperson]);
    }

    public function test_external_user_login_fails_with_wrong_paswet(): void
    {
        $user = User::factory()->external('rahasia')->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->kodeperson,
            'password' => 'salah',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_internal_user_logs_in_via_sso(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        $user = User::factory()->create(['is_external' => false]);
        $user->assignRole('PEN');

        Http::fake([
            '*' => Http::response([
                'status' => 200,
                'data' => ['auth' => ['nama' => $user->nama]],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->kodeperson,
            'password' => 'whatever-sso-checks',
        ]);

        $response->assertOk()->assertJsonPath('data.user.kodeperson', $user->kodeperson);
    }

    public function test_internal_user_login_fails_when_sso_rejects(): void
    {
        $user = User::factory()->create(['is_external' => false]);

        Http::fake(['*' => Http::response(['status' => 401], 401)]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->kodeperson,
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_unknown_internal_username_is_created_on_the_fly_after_successful_sso(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        // No local User row exists yet for this kodeperson.
        Http::fake([
            '*' => Http::response([
                'status' => 200,
                'data' => ['auth' => ['nama' => 'Dosen Baru']],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'P99999',
            'password' => 'whatever',
        ]);

        // Freshly created users have no role yet, so login is correctly
        // refused rather than issuing a token with no accessible module.
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['kodeperson' => 'P99999', 'nama' => 'Dosen Baru']);
    }

    public function test_login_rejected_when_user_has_no_allowed_role(): void
    {
        $user = User::factory()->external('rahasia')->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->kodeperson,
            'password' => 'rahasia',
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_role_hint_is_honored_when_user_is_allowed_that_role(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        SpatieRole::findOrCreate('ADM', 'sanctum');
        $user = User::factory()->external('rahasia')->create();
        $user->assignRole(['PEN', 'ADM']);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->kodeperson,
            'password' => 'rahasia',
            'roleHint' => 'ADM',
        ]);

        $response->assertOk()->assertJsonPath('data.user.activeRole', 'ADM');
    }

    public function test_role_hint_is_ignored_when_user_is_not_allowed_that_role(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        SpatieRole::findOrCreate('ADM', 'sanctum');
        $user = User::factory()->external('rahasia')->create();
        $user->assignRole('PEN');

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->kodeperson,
            'password' => 'rahasia',
            'roleHint' => 'ADM',
        ]);

        $response->assertOk()->assertJsonPath('data.user.activeRole', 'PEN');
    }

    public function test_login_requires_username_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'spam_user',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'spam_user',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.');
    }

    public function test_expired_sanctum_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token');

        $token->accessToken->forceFill([
            'created_at' => now()->subDays(2),
        ])->save();

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
