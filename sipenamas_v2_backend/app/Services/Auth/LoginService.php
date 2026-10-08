<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Models\ZLogLogin;
use App\Support\Legacy\Dencoder;

/**
 * Aturan login yang dipakai bersama login API (token Sanctum) dan login web
 * Inertia (session). Padanan appz/ONAIR/login/myphp/dologin.php.
 */
class LoginService
{
    public function __construct(private UkwmsSsoClient $sso) {}

    /**
     * Verifikasi kredensial. Akun eksternal memakai password lokal, akun
     * internal lewat SSO pegawai UKWMS. Null bila kredensial salah.
     */
    public function authenticate(string $username, string $password): ?User
    {
        $user = User::where('kodeperson', $username)->first();

        if ($user && $user->is_external) {
            return hash_equals((string) $user->paswet, Dencoder::encode3t($password)) ? $user : null;
        }

        $result = $this->sso->login($username, $password);

        if (! $result['success']) {
            return null;
        }

        // Orang yang baru saja lolos SSO tapi belum pernah disuplai
        // SyncUsersFromUwmsdm (jarang - kandidat baru banget) tetap
        // dibuatkan baris User on-the-fly di sini, TANPA query uwmsdm
        // real-time (biar jalur login tidak nambah dependency network).
        return $user ?? User::create([
            'kodeperson' => $username,
            'nama' => $result['namaLengkap'],
            'is_external' => false,
        ]);
    }

    public function recordLogin(string $username, ?string $ip): void
    {
        ZLogLogin::create([
            'USR' => $username,
            'TS' => now(),
            'IP' => $ip,
        ]);
    }

    /**
     * Peran aktif setelah login: role hint bila dimiliki user, selain itu
     * peran default. Null bila user tidak punya modul apa pun.
     */
    public function resolveActiveRole(User $user, ?string $roleHint): ?Role
    {
        return $roleHint && Role::tryFrom($roleHint) && $user->hasRole(Role::from($roleHint))
            ? Role::from($roleHint)
            : $user->defaultRole();
    }
}
