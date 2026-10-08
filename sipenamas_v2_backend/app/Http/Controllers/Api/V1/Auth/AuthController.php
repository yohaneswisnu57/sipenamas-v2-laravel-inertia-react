<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ImpersonateRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SwitchRoleRequest;
use App\Http\Resources\PersonResource;
use App\Models\ImpersonationLog;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Padanan appz/ONAIR/login/myphp/dologin.php, tapi stateless (Sanctum
 * Bearer token, bukan $_SESSION). Identitas login sekarang App\Models\User
 * (lihat catatan pemisahan auth/RBAC di User.php) - disuplai dari
 * `SyncUsersFromUwmsdm`, bukan lagi dari `person` legacy. PASWET user
 * internal tidak ditulis ulang di sini, dan bypass username hardcoded
 * tidak diport.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginService $login)
    {
        $username = $request->string('username')->trim()->toString();
        $user = $login->authenticate($username, $request->string('password')->toString());

        if (! $user) {
            return ApiResponse::error('Username atau password salah.', 401);
        }

        $login->recordLogin($username, $request->ip());

        $activeRole = $login->resolveActiveRole($user, $request->string('roleHint')->toString());

        if (! $activeRole) {
            return ApiResponse::error('Akun ini belum memiliki hak akses ke modul manapun.', 403);
        }

        $token = $user->createToken('sipenamas_v2')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => PersonResource::withActiveRole($user, $activeRole),
        ], 'Login berhasil diverifikasi');
    }

    public function me(Request $request)
    {
        return ApiResponse::success(PersonResource::withActiveRole($request->user(), $request->user()->defaultRole()));
    }

    public function switchRole(SwitchRoleRequest $request)
    {
        $user = $request->user();
        $role = Role::from($request->string('role')->toString());

        if (! $user->hasRole($role->value)) {
            return ApiResponse::error('Peran tidak diizinkan untuk akun ini.', 403);
        }

        return ApiResponse::success(PersonResource::withActiveRole($user, $role));
    }

    /**
     * Impersonate user lain untuk keperluan testing manual, dibatasi
     * hanya untuk Super Admin. Token yang dibuat diberi ability
     * 'impersonation' supaya bisa dideteksi & dicegah bertingkat
     * (impersonate saat sedang impersonate).
     */
    public function impersonate(string $kodeperson, ImpersonateRequest $request)
    {
        $actor = $request->user();

        // Guard Superadmin & anti-chaining dilakukan SEBELUM lookup user -
        // route ini sengaja tidak pakai implicit model binding (User $user),
        // karena binding gagal (404) sebelum guard di bawah ini sempat
        // jalan akan membocorkan KODEPERSON mana yang valid kepada *siapa
        // pun* yang sudah login (403=ada, 404=tidak ada).
        if (! $actor->hasRole(User::SUPER_ADMIN_ROLE) && ! $actor->hasRole(Role::ADMIN->value)) {
            return ApiResponse::error('Hanya Super Admin dan Administrator LPPM yang dapat menggunakan fitur ini.', 403);
        }

        // tokenCan()/PersonalAccessToken::can() menganggap ability '*' (token
        // login biasa) otomatis lolos untuk ability apa pun, jadi deteksi
        // "sedang impersonate" harus baca daftar abilities mentah, bukan
        // tokenCan(), supaya token login biasa tidak ikut ke-flag.
        $currentAbilities = $actor->currentAccessToken()?->abilities ?? [];
        if (in_array('impersonation', $currentAbilities, true)) {
            return ApiResponse::error('Tidak bisa impersonate saat sedang impersonate. Kembali dulu ke akun asli.', 403);
        }

        if ($kodeperson === $actor->kodeperson) {
            return ApiResponse::error('Tidak bisa impersonate akun sendiri.', 422);
        }

        $user = User::where('kodeperson', $kodeperson)->first();
        $role = $request->filled('role') ? Role::from($request->string('role')->toString()) : null;
        $role ??= $user?->defaultRole();

        // Pesan & status disamakan untuk "user tidak ada" dan "user ada
        // tapi tidak punya role tsb", supaya actor Superadmin sekalipun tidak
        // bisa membedakan dua kondisi ini lewat respons.
        if (! $user || ! $role || ! $user->hasRole($role->value)) {
            return ApiResponse::error('User tidak ditemukan atau tidak memiliki peran tersebut.', 422);
        }

        $token = $user->createToken('impersonation', ['impersonation'])->plainTextToken;

        ImpersonationLog::create([
            'actor_kodeperson' => $actor->kodeperson,
            'target_kodeperson' => $user->kodeperson,
            'role' => $role->value,
            'ip_address' => $request->ip(),
        ]);

        return ApiResponse::success([
            'token' => $token,
            'user' => PersonResource::withActiveRole($user, $role),
        ], 'Impersonation berhasil');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout berhasil');
    }
}
