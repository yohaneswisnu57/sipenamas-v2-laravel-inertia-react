<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Padanan EnsureRole.php tapi untuk permission granular CRUD per modul
 * (mis. "PEN.create"). Dipakai sebagai alias route `permission:PEN.read`
 * - lihat bootstrap/app.php. Superuser bypass ditangani terpusat lewat
 * Gate::before di AppServiceProvider (can() memanggil Gate), jadi tidak
 * perlu dicek manual di sini lagi.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $person = $request->user();

        if (! $person) {
            return ApiResponse::error('Belum login.', 401);
        }

        $allowed = collect($permissions)->contains(fn (string $permission) => $person->can($permission));

        if (! $allowed) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                abort(403, 'Anda tidak memiliki hak akses untuk aksi ini.');
            }

            return ApiResponse::error('Anda tidak memiliki hak akses untuk aksi ini.', 403);
        }

        return $next($request);
    }
}
