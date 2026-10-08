<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Web\AuthController;
use App\Http\Resources\PersonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $this->auth($request),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            // Pengganti useParams() react-router: parameter route halaman ini.
            'routeParams' => fn () => (object) $this->routeParams($request),
        ];
    }

    /**
     * Hanya placeholder URL ({id}, {slug}); defaults Route::inertia
     * (component, props) ikut tersimpan di parameters() dan dibuang.
     *
     * @return array<string, mixed>
     */
    private function routeParams(Request $request): array
    {
        $route = $request->route();

        return $route ? Arr::only($route->parameters(), $route->parameterNames()) : [];
    }

    /**
     * @return array{user: array<string, mixed>|null, impersonating: bool}
     */
    private function auth(Request $request): array
    {
        $user = $request->user();
        $activeRole = AuthController::activeRole($request);

        return [
            'user' => $user && $activeRole
                ? PersonResource::withActiveRole($user, $activeRole)->resolve($request)
                : null,
            'impersonating' => $request->session()->has(AuthController::IMPERSONATOR),
        ];
    }
}
