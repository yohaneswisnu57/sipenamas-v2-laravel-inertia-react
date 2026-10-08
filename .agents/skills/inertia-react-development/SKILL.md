---
name: inertia-react-development
description: "Develops Inertia.js v3 React client-side pages in SIPENAMAS. Activates when creating React pages, forms, or navigation with Inertia; using <Link>, <Form>, useForm, useHttp, router, or usePage; working with deferred props, prefetching, polling, partial reloads, or optimistic updates; or when converting a SPA page from useEffect + apiClient to Inertia props. Do NOT use for current react-router SPA work while Inertia is not installed."
license: MIT
metadata:
  author: laravel
---

# Inertia React Development

## Status: read first

- Per 2026-10-07, Inertia is **not installed**. The frontend is still a react-router SPA in `sipenamas_v2_frontend/src`. Use this skill only for an approved migration task (`docs/plan/kajian-migrasi-inertia.md`).
- This skill targets the stable `@inertiajs/react` v3 line (`^3.8`, npm tag `latest` on 2026-10-07), which needs React 19. Install stable releases only; never a `beta`/`rc` tag. Check `package.json` before coding. On v2, `useHttp`, optimistic updates, and default layouts do not exist.
- Project rules (routes, auth, shared props, tests) live in `inertia-laravel-development`. Activate it too.
- Laravel Boost ships a skill with this same name. After the Inertia packages are installed, `php artisan boost:update` can overwrite this file. Re-check the "SIPENAMAS notes" section after any Boost update.
- Docs: `search-docs` (Boost MCP) when available, otherwise https://inertiajs.com/docs/v3.

## Pages

Pages live in `resources/js/pages/<modul>/<Nama>.jsx` and receive server props.

```jsx
import { Head, Link } from '@inertiajs/react'

export default function DaftarPenelitian({ proposals }) {
  return (
    <>
      <Head title="Daftar Penelitian" />
      {proposals.map((p) => (
        <Link key={p.id} href={`/pen/penelitian/${p.id}`}>{p.judul}</Link>
      ))}
    </>
  )
}
```

- No `useState` + `useEffect` + service call for the initial data. The props are the data.
- Shared props: `const { auth, flash } = usePage().props`. Current URL: `usePage().url`.
- The default layout (`AppLayout`) is set in `createInertiaApp()`, so the Sidebar and Navbar stay mounted between visits.

## Navigation

```jsx
import { Link, router } from '@inertiajs/react'

<Link href="/pen/penelitian" prefetch>Penelitian</Link>
<Link href="/logout" method="post" as="button">Keluar</Link>

router.visit('/pen/dashboard')
router.get('/pen/penelitian', { status: 'DRAFT' }, { preserveState: true, replace: true })
router.reload({ only: ['proposals'] })
```

- Use `<Link>` for internal pages, never a plain `<a>` (that reloads the whole app).
- Filters that live in the URL: `router.get(url, params, { preserveState: true, replace: true })` instead of `useSearchParams`.
- Partial reload: `router.reload({ only: [...] })` fetches only the named props.

## Forms

`<Form>` for simple forms:

```jsx
import { Form } from '@inertiajs/react'

<Form action="/pen/penelitian" method="post" resetOnSuccess>
  {({ errors, processing }) => (
    <>
      <input name="judul" />
      {errors.judul && <p className="text-xs text-red-600">{errors.judul}</p>}
      <button type="submit" disabled={processing}>Simpan</button>
    </>
  )}
</Form>
```

`useForm` for programmatic control:

```jsx
import { useForm } from '@inertiajs/react'

const form = useForm({ judul: '', dokumen: null })

function submit(e) {
  e.preventDefault()
  form.post(`/pen/penelitian/${id}`, { forceFormData: true })
}

// form.data, form.setData('judul', v), form.errors, form.processing, form.progress, form.reset()
```

- Files are sent as `multipart/form-data` automatically. To update with a file, POST with `_method: 'put'`.
- Validation errors arrive in `errors` keyed by field. Business errors arrive as `flash.error`.

## Requests without navigation (`useHttp`)

For search, autocomplete, and interactive widgets that need JSON without a page visit:

```jsx
import { useHttp } from '@inertiajs/react'

const http = useHttp({ q: '' })

function cari(q) {
  http.setData('q', q)
  http.get('/adm/plotting/reviewer', {
    onSuccess: (response) => setHasil(response.data),
  })
}
// http.processing, http.errors (parsed from 422), http.cancel()
```

## Inertia v2/v3 features

- **Deferred props:** the prop is `undefined` at first. Always render a pulsing skeleton until it arrives.
- **Polling:** `usePoll(5000, { only: ['stats'] })`.
- **Infinite scroll:** `<WhenVisible data="items" params={{ page: next }} fallback={...} />` with merge props on the server.
- **Optimistic updates:** `router.optimistic(...)` before a visit; props roll back on failure.
- **Prefetch:** `<Link prefetch>` on menus the user is likely to open.

## v3 breaking changes

- Axios is not bundled. The built-in client sends `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie automatically.
- Events: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` is now `router.cancelAll()`.
- `hideProgress()`/`revealProgress()` removed; use `progress.hide()`/`progress.reveal()`.
- Packages are ESM-only. React `<Deferred>` no longer resets to its fallback during a partial reload.

## SIPENAMAS notes

- Reuse `components/ui/*`, `components/common/*`, and the Adminto palette described in `sipenamas_v2_frontend/CLAUDE.md`. Do not invent a new design.
- Every page must work at 375px: card list on mobile, table on desktop.
- Files (PDF, docx, Excel): use `<a href="..." target="_blank" rel="noopener">`. The session cookie authenticates the download. Never use `Link` or `router` for files.
- UI text is Indonesian.
- Keep client-side filters that already exist; moving them to the server is a separate decision.

## Testing with vitest

Mock the Inertia adapter and pass props directly:

```jsx
vi.mock('@inertiajs/react', async (importOriginal) => ({
  ...(await importOriginal()),
  usePage: () => ({ url: '/pen/penelitian', props: { auth: { user: { activeRole: 'PEN' } }, flash: {} } }),
  router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), reload: vi.fn() },
  Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}))

render(<DaftarPenelitian proposals={[{ id: 1, judul: 'Judul A' }]} />)
```

## Common pitfalls

- Using `<a>` for internal pages instead of `<Link>`.
- Fetching initial data in `useEffect` after the page already has it as props.
- Not handling the `undefined` state of deferred props.
- Submitting a `<form>` without `e.preventDefault()` when using `useForm`.
- Using `useHttp` for something that should be a page visit (it does not update props).
