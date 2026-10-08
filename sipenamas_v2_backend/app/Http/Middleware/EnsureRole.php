<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Padanan pengecekan $_SESSION['LPPM_LOGINCENTER_IS<MODUL>'] di legacy,
 * tapi dibaca langsung dari flag GROUPAKSES_* pada `person` tiap request
 * (stateless, tidak ada sesi server). Dipakai sebagai alias route
 * `role:ADM,PEN` - lihat bootstrap/app.php.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Belum login.', 401);
        }

        $allowed = collect($roles)
            ->map(fn (string $role) => Role::from($role))
            ->contains(fn (Role $role) => $user->hasRole(User::SUPER_ADMIN_ROLE) || $user->hasRole($role->value));

        if (! $allowed) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                abort(403, 'Anda tidak memiliki akses ke modul ini.');
            }

            return ApiResponse::error('Anda tidak memiliki akses ke modul ini.', 403);
        }

        return $next($request);
    }
}
