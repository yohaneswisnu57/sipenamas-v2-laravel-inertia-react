<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ImpersonateRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SwitchRoleRequest;
use App\Models\ImpersonationLog;
use App\Models\User;
use App\Services\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login, ganti peran, dan login as untuk halaman Inertia. Sesi disimpan di
 * session server (guard `web`), bukan token Sanctum di localStorage seperti
 * SPA lama. Aturan dan pesannya sama dengan Api\V1\Auth\AuthController.
 */
class AuthController extends Controller
{
    /** Session key peran aktif yang dipilih user. */
    public const ACTIVE_ROLE = 'active_role';

    /** Session key akun asli selama login as. */
    public const IMPERSONATOR = 'impersonator_id';

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return $this->redirectToDashboard($this->activeRole($request));
        }

        return Inertia::render('auth/LoginPage');
    }

    public function store(LoginRequest $request, LoginService $login): RedirectResponse
    {
        $username = $request->string('username')->trim()->toString();
        $user = $login->authenticate($username, $request->string('password')->toString());

        if (! $user) {
            return back()->withErrors(['username' => 'Username atau password salah.'])->onlyInput('username');
        }

        $login->recordLogin($username, $request->ip());

        $activeRole = $login->resolveActiveRole($user, $request->string('roleHint')->toString());

        if (! $activeRole) {
            return back()->withErrors(['username' => 'Akun ini belum memiliki hak akses ke modul manapun.'])->onlyInput('username');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::ACTIVE_ROLE, $activeRole->value);

        return $this->redirectToDashboard($activeRole);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    public function switchRole(SwitchRoleRequest $request): RedirectResponse
    {
        $role = Role::from($request->string('role')->toString());

        if (! in_array($role, $request->user()->allowedRoles(), true)) {
            return back()->withErrors(['role' => 'Peran tidak diizinkan untuk akun ini.']);
        }

        $request->session()->put(self::ACTIVE_ROLE, $role->value);

        return $this->redirectToDashboard($role);
    }

    /**
     * Login as user lain untuk testing manual (Super Admin dan Administrator
     * LPPM). Guard dijalankan sebelum lookup user supaya respons tidak
     * membocorkan KODEPERSON mana yang valid.
     */
    public function impersonate(string $kodeperson, ImpersonateRequest $request): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->hasRole(User::SUPER_ADMIN_ROLE) && ! $actor->hasRole(Role::ADMIN->value)) {
            return back()->withErrors(['impersonate' => 'Hanya Super Admin dan Administrator LPPM yang dapat menggunakan fitur ini.']);
        }

        if ($request->session()->has(self::IMPERSONATOR)) {
            return back()->withErrors(['impersonate' => 'Tidak bisa impersonate saat sedang impersonate. Kembali dulu ke akun asli.']);
        }

        if ($kodeperson === $actor->kodeperson) {
            return back()->withErrors(['impersonate' => 'Tidak bisa impersonate akun sendiri.']);
        }

        $user = User::where('kodeperson', $kodeperson)->first();
        $role = $request->filled('role') ? Role::from($request->string('role')->toString()) : null;
        $role ??= $user?->defaultRole();

        if (! $user || ! $role || ! $user->hasRole($role->value)) {
            return back()->withErrors(['impersonate' => 'User tidak ditemukan atau tidak memiliki peran tersebut.']);
        }

        ImpersonationLog::create([
            'actor_kodeperson' => $actor->kodeperson,
            'target_kodeperson' => $user->kodeperson,
            'role' => $role->value,
            'ip_address' => $request->ip(),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put([
            self::IMPERSONATOR => $actor->getKey(),
            self::ACTIVE_ROLE => $role->value,
        ]);

        return $this->redirectToDashboard($role);
    }

    /** Kembali ke akun asli, lalu ke Manajemen Pengguna tempat login as dimulai. */
    public function leaveImpersonation(Request $request): RedirectResponse
    {
        $original = User::find($request->session()->get(self::IMPERSONATOR));
        abort_unless($original, 403, 'Anda tidak sedang login sebagai pengguna lain.');

        Auth::guard('web')->login($original);
        $request->session()->regenerate();
        $request->session()->forget(self::IMPERSONATOR);
        $request->session()->put(self::ACTIVE_ROLE, $original->defaultRole()?->value);

        return redirect('/adm/users');
    }

    public function home(Request $request): RedirectResponse
    {
        return $this->redirectToDashboard($this->activeRole($request));
    }

    /**
     * Peran aktif dari session, jatuh ke peran default bila session kosong
     * atau peran itu sudah dicabut.
     */
    public static function activeRole(Request $request): ?Role
    {
        $user = $request->user();
        $role = Role::tryFrom((string) $request->session()->get(self::ACTIVE_ROLE));

        return $role && $user && in_array($role, $user->allowedRoles(), true) ? $role : $user?->defaultRole();
    }

    private function redirectToDashboard(?Role $role): RedirectResponse
    {
        return redirect('/'.strtolower(($role ?? Role::ADMIN)->value).'/dashboard');
    }
}
