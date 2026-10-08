<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Request API (/api/v1) selalu dijawab JSON; halaman Inertia memakai
 * perilaku web Laravel (redirect + session flash/errors).
 */
$wantsJson = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Halaman Inertia memanggil /api/v1 dengan cookie session yang sama
        // (domain di SANCTUM_STATEFUL_DOMAINS); klien Bearer tetap jalan.
        $middleware->statefulApi();

        // Aplikasi berjalan di belakang nginx (container dan host) di
        // loopback; header X-Forwarded-* dari sana dipercaya.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // API tidak punya halaman login: tamu API selalu jatuh ke
        // AuthenticationException (JSON 401) di bawah. Tamu web ke /login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) use ($wantsJson): void {
        $exceptions->shouldRenderJsonWhen($wantsJson);

        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request) ? ApiResponse::error('Data yang dikirim tidak valid.', 422, $e->errors()) : null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request) ? ApiResponse::error('Belum login atau sesi sudah berakhir.', 401) : null;
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request) ? ApiResponse::error($e->getMessage() ?: 'Anda tidak memiliki akses.', 403) : null;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request) ? ApiResponse::error('Data tidak ditemukan.', 404) : null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request) ? ApiResponse::error('Terlalu banyak permintaan. Silakan coba lagi nanti.', 429) : null;
        });

        // Detail SQL (nama tabel, query, host DB) tidak boleh sampai ke klien.
        // Exception tetap tercatat di log lewat report() bawaan.
        $exceptions->render(function (QueryException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request) ? ApiResponse::error('Terjadi kesalahan pada server.', 500) : null;
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($wantsJson) {
            if ($wantsJson($request)) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                return back()->with('error', 'Sesi halaman kedaluwarsa, silakan coba lagi.');
            }

            // Error bisnis (abort_if di service/controller) pada aksi web
            // dikembalikan ke halaman asal sebagai flash, bukan halaman error.
            if (! $request->isMethod('GET') && $e instanceof HttpExceptionInterface && $status >= 400 && $status < 500) {
                // ModelNotFound (record bukan milik user) jangan membocorkan nama model.
                $pesan = $e->getPrevious() instanceof ModelNotFoundException ? 'Data tidak ditemukan.' : $e->getMessage();

                return back()->with('error', $pesan ?: 'Permintaan tidak dapat diproses.');
            }

            if ($status === 403) {
                return Inertia::render('auth/UnauthorizedPage')->toResponse($request)->setStatusCode(403);
            }

            return $response;
        });
    })->create();
