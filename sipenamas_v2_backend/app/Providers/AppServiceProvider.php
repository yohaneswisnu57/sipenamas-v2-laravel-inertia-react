<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Skema legacy dbsipenamas punya puluhan kolom berawalan underscore
        // (__ABSTRAK, _MSG_PENOLAKANDEKAN, _STATUSINSENTIF, dst - lihat
        // .agents/tables_extracted.json). Laravel diam-diam menolak kolom
        // berawalan "_" saat mass assignment meskipun $guarded = [] (lihat
        // GuardsAttributes::isFillable()). Proteksi mass-assignment tidak
        // relevan di sini karena tidak ada controller yang mass-assign
        // langsung dari request mentah (Model::create($request->all()))
        // - semua data sudah melalui FormRequest tervalidasi dan dipetakan
        // eksplisit per kolom di Service/Controller.
        Model::unguard();

        // Satu tempat untuk bypass superuser, menggantikan pengecekan
        // "$person->ISSUPERUSER ||" yang sebelumnya berulang manual di
        // EnsurePermission dan tempat lain. Middleware/kode yang memakai
        // $user->can()/Gate otomatis dapat bypass ini tanpa duplikasi.
        // Sumbernya role Spatie "Super Admin" (User::SUPER_ADMIN_ROLE),
        // dicek di App\Models\User (bukan lagi Person - lihat catatan
        // pemisahan auth/RBAC di User.php dan Person.php).
        Gate::before(fn (User $user, string $ability) => $user->hasRole(User::SUPER_ADMIN_ROLE) ? true : null);

        RateLimiter::for('login', function (Request $request) {
            $username = (string) $request->string('username')->trim()->lower();

            return [
                Limit::perMinute(5)->by($username.'|'.$request->ip())->response(function () {
                    return ApiResponse::error('Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.', 429);
                }),
                Limit::perMinute(60)->by($request->ip())->response(function () {
                    return ApiResponse::error('Terlalu banyak permintaan login dari IP ini. Silakan coba lagi nanti.', 429);
                }),
            ];
        });

        // Stand-ins for legacy tables (`person`, `prodi`, `fakultas`,
        // `z_log_login`) that exist in the real dbsipenamas MySQL schema
        // but have no migration under database/migrations - see those
        // test-only migrations for why. Never runs outside `testing`.
        if ($this->app->environment('testing')) {
            $this->loadMigrationsFrom(database_path('migrations/testing'));
        }
    }
}
