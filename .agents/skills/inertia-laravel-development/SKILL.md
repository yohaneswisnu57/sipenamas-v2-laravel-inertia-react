---
name: inertia-laravel-development
description: "Server-side Inertia.js v3 for Laravel in SIPENAMAS, plus the project's rules for migrating the React SPA from /api/v1 to Inertia. Activate when writing Inertia::render, HandleInertiaRequests, page routes in routes/web.php, shared props or flash messages, Inertia redirects, assertInertia tests, session login/switch-role/impersonate for Inertia, or when converting a page from apiClient/useEffect to Inertia props. Do NOT use for current SPA + REST work while Inertia is not installed."
license: MIT
metadata:
  author: sipenamas
---

# Inertia Laravel Development (SIPENAMAS)

## Status: read first

- Per 2026-10-07, Inertia is **not installed**. The code is still a React SPA (`sipenamas_v2_frontend`) plus a REST API (`sipenamas_v2_backend/routes/api.php`).
- Use this skill only for a migration task the user has approved. The plan is `docs/plan/kajian-migrasi-inertia.md`. Follow its phases and its open decisions; do not decide them yourself.
- Before writing code, confirm the installed versions: `composer show inertiajs/inertia-laravel` and `@inertiajs/react` in `package.json`. This skill targets the stable v3 line: `inertiajs/inertia-laravel:^3.5` and `@inertiajs/react:^3.8` (latest stable on 2026-10-07). Install stable releases only; never a `beta`/`rc` tag such as `3.0.0-beta.7`. If v2 is installed, `Inertia::optional()` is `Inertia::lazy()` and `useHttp` does not exist.
- Adding `inertiajs/inertia-laravel`, `@inertiajs/react`, or React 19 needs the user's approval (AGENTS.md).
- For client-side patterns, also activate `inertia-react-development`.
- Docs: `search-docs` (Boost MCP) when available, otherwise https://inertiajs.com/docs/v3.

## Rendering pages

```php
use Inertia\Inertia;

return Inertia::render('pen/DaftarPenelitian', [
    'proposals' => PenelitianResource::collection($proposals)->resolve(),
    'stats' => Inertia::defer(fn () => $this->stats($user)),
]);
```

- Page names map to `resources/js/pages/<modul>/<Nama>.jsx`: `pen`, `rev`, `dkn`, `adm`, `rkt`, `akr`, `auth`.
- Reuse the existing Resources in `app/Http/Resources`. Passing a `JsonResource` as a prop keeps the `data` wrapper; `->resolve()` gives a plain array. Keep the same shape the page already reads, so the React component changes as little as possible.
- Prop types:
  - `Inertia::defer(fn () => ...)`: heavy dashboard statistics, loaded after the first render. The page must show a skeleton.
  - `Inertia::optional(fn () => ...)`: only sent on a partial reload that asks for it (`only: [...]`).
  - `Inertia::merge(...)`: appending pages, for infinite scroll.
  - Wrap expensive props in closures so partial reloads skip them.
- Never return JSON (`ApiResponse::success`) to an Inertia request. Inertia requests need an Inertia response or a redirect.

## Mutations

```php
public function store(StorePenelitianRequest $request, ProposalSubmissionService $service): RedirectResponse
{
    $penelitian = $service->submit($request->user(), $request->validated());

    return to_route('pen.penelitian.show', $penelitian)->with('success', 'Draft usulan tersimpan. Usulan belum diajukan');
}
```

- Reuse the FormRequests in `app/Http/Requests/*`. Field errors come back to `useForm`/`<Form>` automatically via redirect back.
- Business logic stays in `app/Services/Proposal/*`. The web controller stays thin. Do not copy logic from the API controller; move shared code into the existing service when both API and web need it during the transition.
- Business errors: keep `abort_if(..., 422, 'pesan')` and `ValidationException::withMessages([...])`. The exception handler turns a 4xx `HttpException` on a non-GET Inertia request into `back()->with('error', $message)`. Use `ValidationException` when the error belongs to one field.
- Success: `back()->with('success', '...')` or `to_route(...)->with(...)`. Messages in Indonesian, same text as the API used.
- File uploads: the client sends `multipart/form-data`. For update with files, the route must accept POST with `_method=PUT`.
- Downloads and previews: return `response()->download(...)` or `response()->file(...)` from a normal route opened by `<a href target="_blank">`, not through an Inertia visit. For a redirect to an external URL, use `Inertia::location($url)`.

## SIPENAMAS rules

- **Routes.** `routes/web.php` mirrors the current SPA paths (`/pen/penelitian/{id}`, `/adm/plotting`, ...) so old links keep working. Name routes by module (`pen.penelitian.index`).
- **Authorization.** A page route uses the same middleware as the API endpoint that feeds its data: `permission:<aksi> <resource>` for data pages (see `App\Support\Rbac\PermissionCatalog`), `role:X` only for dashboards. The old SPA `RoleRoute` rule (every ADMIN passes) is not used. `EnsureRole` and `EnsurePermission` return JSON for `api/*` and `abort(403, ...)` for web.
- **Shared props** (`HandleInertiaRequests::share`): keep them small. `auth.user` = `PersonResource::withActiveRole($user, $activeRole)` (it already holds `allowedRoles`, `modulePermissions`, `isSuperAdmin`), `auth.impersonating`, `flash.success`, `flash.error`. Use closures.
- **Login.** Same rules as `AuthController::login()`: `is_external` checks `Dencoder::encode3t`, others go through `UkwmsSsoClient`; write `ZLogLogin`; keep `throttle:login`; resolve role hint or `defaultRole()`; 403 when the account has no module. Then `Auth::login($user)` and `$request->session()->regenerate()`. Extract shared logic; do not duplicate it.
- **Active role.** Stored in session key `active_role`. Switch-role is a POST that checks `hasRole`, stores the role, and redirects to that role's dashboard.
- **Impersonate.** Only Super Admin and Administrator LPPM. Session key `impersonator_id` holds the real account. Reject nested impersonation when the key exists. Keep `ImpersonationLog` and the same error messages (they hide which KODEPERSON exists). Regenerate the session on every switch. "Kembali ke akun asli" only works when the key exists.
- **Logout.** `Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()`.
- **Transition.** Until a module is converted, its pages call `/api/v1` with the session cookie (Sanctum `statefulApi()`, guard `web`). Delete an API route only after its last consumer has moved, and convert its tests in the same commit.
- **Exceptions.** `bootstrap/app.php` renders JSON only for `api/*` or `expectsJson()`. Do not reintroduce `shouldRenderJsonWhen(fn () => true)`.
- **Public QR.** `/dox/{jenis}/{kode}` keeps its path. Printed QR codes point at it.
- **Legacy rules.** Behavior follows legacy code, not assumptions. Read `docs/legacy-flow/penelitian.md` and `sipenamas_v2_backend/.ai/rules/` before touching Penelitian flow controllers.

## Testing

Read `testing-best-practices` first. Tests use PHPUnit.

```php
use Inertia\Testing\AssertableInertia as Assert;

public function test_peneliti_melihat_daftar_penelitiannya(): void
{
    $this->actingAs($peneliti)
        ->get('/pen/penelitian')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pen/DaftarPenelitian')
            ->has('proposals', 2)
            ->where('proposals.0.judul', 'Judul A'));
}

public function test_simpan_usulan_tanpa_judul_ditolak(): void
{
    $this->actingAs($peneliti)
        ->post('/pen/penelitian', [])
        ->assertSessionHasErrors('judul');
}
```

- Mutations: `assertRedirect(...)`, `assertSessionHas('success')`, `assertSessionHasErrors([...])`.
- Deferred props: `->loadDeferredProps(fn (Assert $reload) => ...)`.
- Guard tests: a user without the permission gets 403 on the page route.
- Keep the existing API tests green while their routes exist.

## Checklist per converted page

1. Web route with the same middleware as the API data endpoint.
2. Controller renders the page with props from existing Resources; heavy parts deferred.
3. Mutations redirect with flash; errors come from FormRequest or `ValidationException`.
4. React page reads props instead of `useEffect` + service (see `inertia-react-development`).
5. PHP test with `assertInertia`; vitest updated; old API route and its tests removed only when unused.
6. Manual check at 375px and desktop.
7. Update the status table in `docs/legacy-flow/penelitian.md` when a Penelitian feature changes.

## Common pitfalls

- Returning JSON or a Resource response to an Inertia request.
- Gating a page with a different rule than its data endpoint.
- Putting large lists in shared props.
- Using `Inertia::lazy()` on v3 (removed; use `Inertia::optional()`).
- Sending PUT with files instead of POST + `_method`.
- Forgetting `session()->regenerate()` after login or impersonate.
- Opening downloads with `Link` or `router.visit` instead of a plain `<a href>`.
