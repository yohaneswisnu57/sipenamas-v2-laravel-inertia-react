# Kajian Migrasi SIPENAMAS V2 ke Laravel + Inertia.js

Status: **kajian, belum dieksekusi.** Disusun 2026-10-07 dari pembacaan kode V2, dokumentasi resmi Inertia v3, dan berkas Laravel Boost di `vendor/`. Jangan mulai sebelum deadline alur Penelitian 2026-10-13 lewat.

## 1. Ringkasan dan rekomendasi

| Hal | Rekomendasi | Alasan |
|---|---|---|
| Lanjut atau tidak | Lanjut, bertahap, setelah 2026-10-13 | Keempat tujuan pengguna (satu deploy, auth lebih aman, kurangi boilerplate, satu stack) cocok dengan Inertia |
| Versi | Inertia v3 rilis stabil + React 19 | v3 stabil sejak 2026-03-25 dan menjadi tag `latest`; v2 sudah ber-tag `legacy` (hanya perbaikan) |
| Strategi | Strangler per fase, bukan big-bang | Aplikasi tetap bisa dirilis di setiap akhir fase |
| Fase wajib | Fase 0 + Fase 1 (sekitar 2 minggu) | Sudah memberi 3 dari 4 tujuan: satu deploy, session cookie, satu stack |
| Fase 2 | Per modul, boleh oportunistik | Halaman dikonversi ke props saat halaman itu disentuh untuk fitur lain |
| API `/api/v1` | Tetap hidup sampai modul terakhir pindah | Halaman Fase 1 masih memanggil API; klien lain tetap bisa pakai Bearer |

Estimasi total 25–38 hari kerja (Fase 0: 1–2, Fase 1: 6–9, Fase 2: 17–25, Fase 3: 1–2). Ini estimasi, bukan komitmen.

## 2. Kondisi sekarang

### 2.1 Frontend (`sipenamas_v2_frontend`)

- React 18, react-router-dom 7, zustand 5, Tailwind 4, Vite 6, JavaScript (bukan TypeScript).
- 85 file JS/JSX, sekitar 16,8 ribu baris. 49 halaman di `src/features/*` plus 4 komponen fitur (modal/card).
- 108 pemanggilan `useEffect`. Hampir semua halaman memuat data sendiri: `useState` + `useEffect` + service API + state loading.
- 31 file mengimpor `react-router-dom`; 14 file memakai `useAuthStore`.
- 10 service di `src/services/api/` di atas `apiClient.js` (`request`, `requestForm`, `ambilBerkasUrl`, `unduhBerkas`).
- Token Sanctum, data user, dan sesi asli saat impersonate disimpan di `localStorage` (`src/store/authStore.js`). Token yang bisa dibaca JavaScript rawan dicuri lewat XSS.
- Unduh dan pratinjau berkas: fetch blob dengan header `Authorization`, lalu `createObjectURL`, plus trik membuka tab kosong lebih dulu supaya tidak diblokir popup blocker.
- Guard rute `src/routes/RoleRoute.jsx` memakai `activeRole` dan meloloskan `user.role === ADMIN`. Aturan ini berbeda dari backend (lihat 2.2).
- Test: 31 file vitest (PEN 15, ADM 7, DKN 3, REV 3, auth 1, layout, 1 integrasi) dan Playwright (`alur-penelitian.spec.js`, `login.spec.js`).

### 2.2 Backend (`sipenamas_v2_backend`)

- Laravel 12, PHP 8.4, Sanctum 4 (Bearer murni), Spatie Permission.
- 178 route di `routes/api.php` dengan prefix `pen`, `adm`, `rev`, `dkn`, `rkt`, `akr`. 38 controller `Api/V1/*`.
- Otorisasi data memakai `permission:` per resource (39 grup, peta di `App\Support\Rbac\PermissionCatalog`). `role:X` hanya untuk dashboard (4 route). Middleware `EnsureRole` dan `EnsurePermission` mengembalikan JSON lewat `ApiResponse`.
- Logika bisnis sudah ada di 17 service `app/Services/Proposal/*`. Validasi ada di 38 FormRequest. Bentuk data ada di 13 Resource. Controller relatif tipis, jadi bisa dipakai ulang.
- Error bisnis: 40 `abort_if`, 4 `abort_unless`, 7 `ValidationException::withMessages`, 12 `RuntimeException`, 143 pemanggilan `ApiResponse::`.
- `bootstrap/app.php`: `shouldRenderJsonWhen(fn () => true)` dan `redirectGuestsTo(fn () => null)`. Semua exception dirender sebagai JSON.
- `config/sanctum.php` guard `[]`; `config/cors.php` `supports_credentials => false`.
- Session driver default `database`, tetapi skema legacy tidak punya tabel `sessions`.
- Kerangka Vite Laravel sudah ada: `package.json` (laravel-vite-plugin ^2, vite 7, tailwind 4, axios), `vite.config.js`, `resources/js/app.js`. `routes/web.php` hanya `welcome`.
- Test: sekitar 319 method (PEN 119, ADM 62, REV 41, DKN 37, Auth 24, Middleware 9, Console 7, RKT 4, AKR 0, root 16). Semua memeriksa JSON.

### 2.3 Deploy

- Frontend: `.github/workflows/deploy.yml` build lalu scp `dist/` ke `sipenamasdev.ukwms.ac.id`.
- Backend: `api.sipenamas.ukwms.ac.id` via php-fpm, dilayani langsung dari working tree server. Commit atau ganti branch di server langsung mengubah backend live.
- QR surat memakai `DOX_URL` (default `https://sipenamasdev.ukwms.ac.id/dox`). URL ini sudah tercetak di PDF yang terbit.

### 2.4 Inventaris per modul

| Modul | File | Baris | Endpoint unik | File dengan upload | Vitest | Test PHP | Halaman terberat |
|---|---|---|---|---|---|---|---|
| ADM | 13 | 4.762 | 36 | 2 | 7 | 62 | PlottingReviewer 734, MasterPeriode 617, ManajemenUser 517, FinalApproval 441 |
| PEN | 16 | 4.692 | 55 | 7 | 15 | 119 | FormUsulanPenelitian 875, KelengkapanPengajuanCard 361 (9 endpoint), KelengkapanLaporanModal 252 (7 endpoint) |
| REV | 4 | 1.150 | 12 | 0 | 3 | 41 | PenilaianProposal 514 |
| DKN | 5 | 1.157 | 13 | 0 | 3 | 37 | DekanDashboard 436 |
| RKT | 3 | 600 | 6 | 0 | 0 | 4 | MonitoringMbkm 285 |
| AKR | 3 | 425 | 3 | 0 | 0 | 0 | ExportLkps (unduh Excel) |
| auth | 3 | 286 | 0 | 0 | 1 | 24 | LoginPage (ditulis ulang di Fase 1) |

Halaman dev (`UiKitShowcasePage`, `ApiExplorerPage`) tidak ikut dimigrasi.

## 3. Fakta Inertia v3 yang relevan

- Hanya pakai rilis stabil. Per 2026-10-07 (dicek di npm dan Packagist):

  | Paket | Stabil terbaru | Constraint yang dipakai | Catatan |
  |---|---|---|---|
  | `inertiajs/inertia-laravel` | v3.5.1 (2026-10-01) | `^3.5` | PHP ^8.2, Laravel ^11.35, ^12, atau ^13 |
  | `@inertiajs/react` | 3.8.0 (2026-10-01), tag `latest` | `^3.8` | peer `react` dan `react-dom` ^19 |
  | `@inertiajs/vite` (opsional) | 3.8.0, tag `latest` | `^3.8` | resolusi halaman dan SSR |
  | `@inertiajs/react` v2 | 2.3.28, tag `legacy` | tidak dipakai | masih menerima React 18, tetapi hanya mode perawatan |

- Jangan memasang tag pra-rilis (`beta`, mis. `3.0.0-beta.7`) atau versi `-rc`. Saat eksekusi, cek ulang versi stabil terbaru dengan `npm view @inertiajs/react dist-tags` dan `composer show -a inertiajs/inertia-laravel`.
- Syarat: PHP 8.2+, Laravel 11+, Node 20+, **React 19+**. Proyek ini masih React 18, jadi React harus naik lebih dulu.
- Axios tidak lagi dibawa. Klien XHR bawaan otomatis membaca cookie `XSRF-TOKEN` dan mengirim header `X-XSRF-TOKEN`.
- `useHttp`: request JSON tanpa pindah halaman. Error 422 diparse otomatis, ada progress upload, cancel, dan optimistic update. Cocok untuk pencarian, autocomplete, dan Plotting.
- Fitur v2 yang tetap ada: deferred props, prefetch, `usePoll`, infinite scroll (`WhenVisible` + merge props), `<Form>`, `useForm`.
- Fitur baru v3 lain: optimistic update, instant visit, default layout di `createInertiaApp()`, layout props, penanganan exception kustom.
- Perubahan breaking v3: `Inertia::lazy()` diganti `Inertia::optional()`; event `invalid` jadi `httpException`, `exception` jadi `networkError`; `router.cancel()` jadi `router.cancelAll()`; paket ESM-only; target build ES2022.
- Upload: data berisi file otomatis dikirim sebagai `multipart/form-data`. PUT/PATCH dengan file harus dikirim sebagai POST + `_method`.
- 419 (sesi CSRF kedaluwarsa): tangani di exception handler dengan `back()->with(...)`, bukan modal error.
- Testing server: `assertInertia` dengan `component`, `has`, `where`, `missing`, `etc`, `loadDeferredProps`, `hasFlash`.
- Laravel Boost (`vendor/laravel/boost/.ai/`) punya guideline `inertia-laravel`, `inertia-react`, dan skill `inertia-react-development`. Boost hanya memasangnya setelah paket Inertia terpasang.

## 4. Arsitektur target

```
Sekarang                                    Target
--------                                    ------
Browser                                     Browser
  |  SPA statis (sipenamasdev)                |  satu domain
  |  fetch + Bearer (localStorage)            |  session cookie + CSRF
  v                                           v
api.sipenamas (Laravel, JSON)               Laravel 12
  routes/api.php                              routes/web.php -> Inertia::render(page, props)
                                              routes/api.php -> tetap selama transisi
```

- Satu aplikasi di `sipenamas_v2_backend`. Nama folder tidak diubah supaya path, hook, dan workflow tidak ikut berubah.
- Kode React pindah ke `resources/js/`:
  - `pages/<modul>/<Nama>.jsx` (huruf kecil `pages`, pola starter kit Laravel, dan Boost mendeteksi folder ini).
  - `layouts/AppLayout.jsx` sebagai default layout, jadi Sidebar dan Navbar tidak di-mount ulang saat pindah halaman.
  - `components/`, `hooks/`, `utils/` dipindah apa adanya.
- Tetap JavaScript. TypeScript bukan bagian dari migrasi ini.
- `resources/views/app.blade.php` dengan `@vite` dan `@inertia`.
- Middleware `HandleInertiaRequests` membagikan props kecil: `auth.user` (`PersonResource::withActiveRole()`, sudah memuat `allowedRoles`, `modulePermissions`, `isSuperAdmin`), `auth.impersonating`, `flash.success`, `flash.error`.
- Dependency baru (butuh persetujuan pengguna), semuanya rilis stabil: `inertiajs/inertia-laravel:^3.5`, `@inertiajs/react:^3.8`, `@vitejs/plugin-react`. Paket FE yang pindah: react 19, react-dom 19, lucide-react, clsx, tailwind-merge. Ziggy tidak dipakai; URL tetap string seperti sekarang.

## 5. Keputusan desain teknis

### 5.1 Auth dan session

- Guard session `web`. Login web memakai aturan yang sama dengan `AuthController::login()`:
  - `is_external = 1`: cek `Dencoder::encode3t($password)`.
  - Lainnya: `UkwmsSsoClient::login()`, buat `User` bila belum ada.
  - Catat `ZLogLogin`, tetap `throttle:login`, role hint atau `defaultRole()`, tolak 403 bila tidak punya modul.
  - Lalu `Auth::login($user)` dan `session()->regenerate()`.
- Logika bersama login API dan web diekstrak ke satu tempat (pola yang sama dengan langkah 3 di `sso-ukwms-app-integrasi.md`), bukan diduplikasi.
- `active_role` disimpan di session. Switch-role menjadi POST web: validasi `hasRole`, simpan ke session, redirect ke dashboard peran.
- Logout: `Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()`.
- Impersonate (Super Admin dan Administrator LPPM):
  - Session key `impersonator_id` menyimpan akun asli; `Auth::login($target)`.
  - Impersonate bertingkat ditolak bila key sudah ada (pengganti cek ability token `impersonation`).
  - `ImpersonationLog` tetap ditulis. Pesan error tetap sama supaya tidak membocorkan KODEPERSON yang valid.
  - Session id diregenerasi di setiap transisi. Route "kembali ke akun asli" hanya bisa dipakai bila key ada.
  - Data sesi asli tidak lagi disimpan di `localStorage`.
- Session driver `file` supaya skema DB legacy tidak berubah. Cookie `secure`, `http_only`, `same_site=lax`.

### 5.2 Route dan otorisasi

- `routes/web.php` mencerminkan path FE sekarang (`/pen/penelitian`, `/pen/penelitian/{id}`, `/adm/plotting`, dan seterusnya), jadi bookmark dan tautan lama tetap jalan.
- Middleware route halaman sama dengan middleware endpoint data utamanya: `permission:view penelitian` untuk daftar penelitian, `role:PEN` untuk dashboard PEN, dan seterusnya. Aturan `RoleRoute` di FE (meloloskan semua ADMIN) tidak dipakai lagi; backend adalah sumber aturan, sesuai legacy.
- `EnsureRole` dan `EnsurePermission`: tetap JSON untuk `api/*`; untuk request web, `abort(403, pesan)`.
- Route publik `/dox/{jenis}/{kode}` tetap dengan path yang sama.

### 5.3 Exception handler

- `shouldRenderJsonWhen()` hanya bernilai true untuk `api/*` atau `expectsJson()`.
- Render closure JSON yang ada sekarang hanya berlaku untuk request API.
- Request Inertia:
  - `ValidationException`: perilaku default Laravel (redirect back + errors per field).
  - `HttpException` 4xx dengan pesan (dari `abort_if` di service/controller) pada request non-GET: `back()->with('error', pesan)`.
  - GET: halaman error Inertia (`errors/Status`).
  - 419: `back()->with('error', 'Sesi halaman kedaluwarsa, silakan coba lagi.')`.
  - Tamu: redirect ke `/login` (`redirectGuestsTo` berbeda untuk web dan API).

### 5.4 Masa transisi API

- Sanctum `statefulApi()`, guard `['web']`, `SANCTUM_STATEFUL_DOMAINS` = domain final. Halaman yang belum dikonversi tetap memanggil `/api/v1` dengan cookie session.
- `apiClient.js`: hapus token dari `localStorage`, kirim `credentials: 'same-origin'` dan header `X-XSRF-TOKEN` dari cookie.
- Bearer tetap didukung untuk klien non-browser.

### 5.5 Berkas, QR, dan SSO

- Unduh dan pratinjau menjadi `<a href="..." target="_blank">` biasa, karena cookie session ikut terkirim. `ambilBerkasUrl`, `unduhBerkas`, dan trik popup dihapus.
- QR: path `/dox/{jenis}/{kode}` tetap. Bila domain final berbeda dari `sipenamasdev.ukwms.ac.id`, domain lama wajib redirect 301 permanen, karena URL lama sudah tercetak di PDF.
- SSO `app.ukwms.ac.id`: callback menjadi route web server-side (validasi token SSO, `Auth::login`, redirect). Ini lebih sederhana dari Desain B di `sso-ukwms-app-integrasi.md`. Jangan kerjakan SSO dua kali: putuskan dulu apakah SSO masuk Fase 1.

### 5.6 Contoh sebelum dan sesudah

Sebelum (`src/features/pen/DaftarPenelitianPage.jsx`, ringkas):

```jsx
const [proposals, setProposals] = useState([])
const [isLoading, setIsLoading] = useState(true)

useEffect(() => {
  async function load() {
    setIsLoading(true)
    try {
      const res = await penelitiApi.getPenelitianList()
      setProposals(res.data)
    } finally {
      setIsLoading(false)
    }
  }
  load()
}, [])
```

Sesudah (Fase 2):

```php
// routes/web.php
Route::middleware(['auth', 'permission:view penelitian'])
    ->get('/pen/penelitian', [Web\Peneliti\PenelitianController::class, 'index']);

// Controller: query sama dengan PenelitianController@index API sekarang.
return Inertia::render('pen/DaftarPenelitian', [
    'proposals' => PenelitianResource::collection($proposals)->resolve(),
]);
```

```jsx
import { Link } from '@inertiajs/react'

export default function DaftarPenelitian({ proposals }) {
  // Tanpa useState, useEffect, service API, dan state loading.
  // Filter tetap di klien seperti sekarang.
}
```

## 6. Fase, langkah, estimasi

### Fase 0: Persiapan (1–2 hari)

- [ ] Pengguna menyetujui dependency baru (bagian 4).
- [ ] Kerjakan di clone atau worktree terpisah, bukan working tree server yang live.
- [ ] Siapkan staging: direktori terpisah + subdomain + DNS + sertifikat (perlu pengguna).
- [ ] Naikkan React 18 ke 19 di SPA sekarang: `react`, `react-dom`, `@types/react*`. Cek `peerDependencies` `lucide-react` 0.344, zustand 5, `@testing-library/react` 16.
- [ ] `npx vitest run tests/features`, `npx vite build --outDir <tmp>`, dan Playwright hijau.

Selesai bila SPA berjalan di React 19 tanpa regresi.

### Fase 1: Satu aplikasi, halaman belum diubah (6–9 hari)

- [ ] Pindah `src/` ke `resources/js/`; gabungkan `package.json` dan `vite.config.js` (laravel-vite-plugin + react + tailwind).
- [ ] Pasang Inertia v3 stabil (bagian 3): `app.blade.php`, `HandleInertiaRequests`, `createInertiaApp` dengan default layout `AppLayout`.
- [ ] Route web untuk 47 halaman (tanpa 2 halaman dev). Props awal hanya parameter route (pengganti `useParams`).
- [ ] Ganti react-router di 31 file: `Link` -> `Link` Inertia, `useNavigate` -> `router.visit`, `useSearchParams` -> query dari `usePage().url`, highlight Sidebar dari `usePage().url`. Hapus `BrowserRouter`, `PrivateRoute`, `RoleRoute`, `RootRedirect` (pindah ke route `/`).
- [ ] Auth session: login, logout, switch-role, impersonate, kembali ke akun asli. Ganti `authStore` dengan `usePage().props.auth`.
- [ ] Sanctum stateful + CSRF di `apiClient.js` (5.4).
- [ ] Exception handler dan middleware peran (5.2, 5.3).
- [ ] Unduhan via link biasa (5.5).
- [ ] Vitest pindah ke backend + helper mock `@inertiajs/react`.
- [ ] CI satu workflow; nginx satu server block dengan root `public/`; redirect domain lama.
- [ ] Cutover di staging, lalu produksi.

Selesai bila semua test PHP, vitest, dan e2e hijau, dan uji manual semua peran di 375px dan desktop lulus.

### Fase 2: Halaman memakai props, per modul (17–25 hari)

| Urutan | Modul | Estimasi | Catatan |
|---|---|---|---|
| 1 | RKT | 1–1,5 hari | Dashboard read-only; tempat membakukan pola |
| 2 | AKR | 1 hari | Belum ada test PHP; tambahkan |
| 3 | DKN | 2–3 hari | Approve/reject proposal |
| 4 | REV | 2–3 hari | PenilaianProposal: borang + rubrik |
| 5 | PEN | 6–9 hari | **Selesai di uji coba (branch `refactor/inertia`, 2026-10-08).** Lihat catatan di bawah |
| 6 | ADM | 5–7 hari | Plotting dan ManajemenUser paling interaktif |

Catatan pelaksanaan modul PEN (uji coba, 2026-10-08):

- Controller `Api/V1/Peneliti/*` dipindah ke `Web/Peneliti/*` (bukan diduplikasi). Route `/api/v1/pen/*` dihapus, karena konsumennya hanya halaman PEN.
- Semua halaman PEN menerima data sebagai props. Dashboard memakai `Inertia::defer` untuk statistik dan daftar usulan.
- Jendela di halaman (kuesioner, kelengkapan laporan, capaian luaran, borang monev) dimuat lewat query string (`?kelengkapan={id}`, `?borang={id}`), jadi redirect sesudah aksi memuat ulang isi jendela yang sama.
- Aksi memakai `kirim()` di `resources/js/lib/inertiaRequest.js`, yaitu `router.visit` yang menghasilkan promise dengan bentuk hasil sama seperti `request()` lama. Handler halaman hampir tidak berubah.
- Master data dropdown diambil dari `App\Support\MasterData\MasterDataOptions`, sumber yang sama dengan API master data yang masih dipakai modul lain.
- `User::$guard_name = 'sanctum'` wajib: tanpa itu Spatie mencari permission ber-guard `web` saat request memakai guard session.
- Test PEN dikonversi ke `assertInertia`/redirect, dengan macro `assertAksiBerhasil`, `assertAksiDitolak`, `assertProp`, `assertPropCount`, `inertiaProp` di `tests/TestCase.php`.

Pola per halaman:

- GET menjadi props dari controller web, memakai Resource yang ada. Statistik dashboard yang berat memakai `Inertia::defer()` + skeleton.
- Mutasi memakai `useForm` atau `<Form>`, FormRequest yang ada, lalu `back()->with('success', ...)`.
- Pencarian, autocomplete, dan Plotting memakai `useHttp` atau partial reload `router.reload({ only: [...] })`. Hasil akhir boleh hybrid.
- Setelah halaman pindah dan tidak ada klien lain, route API modul itu dihapus. Test PHP-nya dikonversi ke `assertInertia`, `assertRedirect`, dan `assertSessionHasErrors`.
- Aturan bisnis tidak berubah. Logika tetap di `app/Services/Proposal/*`.

### Fase 3: Bersih-bersih (1–2 hari)

- [ ] Hapus `react-router-dom`, `zustand` (bila tidak dipakai), service API yang mati, `config/cors.php` yang tidak perlu, `VITE_API_BASE_URL`.
- [ ] Hapus folder `sipenamas_v2_frontend`, `deploy.yml`, dan branch `frontend`.
- [ ] Perbarui `AGENTS.md`, `CLAUDE.md`, ketiga `.claude/settings.json`, hook, skill, dan memory.

## 7. Strategi test

- PHP: test web baru di `tests/Feature/Web/*` untuk login, logout, switch-role, impersonate (termasuk tolak bertingkat), guard permission, shared props, 419, dan redirect tamu. Test API tetap sampai route API-nya dihapus.
- Per halaman yang dikonversi: `assertInertia(fn (AssertableInertia $page) => $page->component('pen/DaftarPenelitian')->has('proposals', 3))`.
- Vitest: helper `renderPage(Page, props)` yang me-mock `usePage`, `router`, dan `Link` dari `@inertiajs/react`.
- Playwright menjadi gerbang di akhir setiap fase.
- Matriks uji manual: PEN, REV, DKN, ADM, RKT, AKR, dan Super Admin (termasuk login as), masing-masing di 375px dan desktop.

## 8. Deploy dan rollback

- CI: `composer install`, PHPUnit, vitest, `npm run build` (hasil ke `public/build`), lalu kirim artefak build ke server. Kode PHP tetap ditarik dengan git di server.
- nginx: satu server block dengan root `public/`. Domain lama redirect 301.
- Build tidak pernah dijalankan di working tree server.
- Rollback: simpan build dan konfigurasi nginx lama; kembali dengan mengganti symlink dan reload nginx.

## 9. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Working tree server adalah backend live | Ganti branch langsung mengubah produksi | Kerjakan di clone terpisah; cutover terjadwal |
| Upgrade React 19 | Paket lama (lucide-react 0.344) bisa tidak kompatibel | Fase 0 terpisah, diuji sebelum Inertia |
| Aturan peran FE dan BE berbeda | Halaman tampil tapi data 403, atau sebaliknya | Route web memakai middleware yang sama dengan endpoint datanya |
| Pemisahan exception handler | 319 test API bisa pecah | Ubah dengan test dulu; jalankan suite penuh |
| CSRF dan 419 | Form gagal setelah sesi lama | Handler 419 + XSRF otomatis Inertia + header di `apiClient.js` |
| QR yang sudah tercetak | QR lama mati bila domain berubah | Redirect 301 permanen untuk `/dox/*` |
| Sesi agent lain di working tree yang sama | Konflik dan WIP tercampur | Worktree terpisah; stage hanya file sendiri |
| Skill Boost menimpa skill kustom | Aturan proyek hilang | Aturan proyek disimpan di `inertia-laravel-development` (nama tidak dipakai Boost) |
| Deadline 2026-10-13 | Fokus terpecah | Tidak mulai sebelum deadline |

## 10. Keputusan terbuka untuk pengguna

1. Domain final aplikasi gabungan: tetap `sipenamasdev.ukwms.ac.id`, `api.sipenamas.ukwms.ac.id`, atau domain baru?
2. `/api/v1` dipertahankan permanen (untuk integrasi lain) atau dihapus setelah Fase 2?
3. Session driver `file`, atau buat tabel `sessions` (mengubah skema)?
4. SSO `app.ukwms.ac.id` dikerjakan di Fase 1 atau sesudahnya?
5. Siapa yang menyiapkan staging (DNS, sertifikat, nginx)?
6. Fase 2 dikerjakan berurutan per modul, atau oportunistik saat halaman disentuh?

## 11. Skill agent

Dua skill disiapkan di `.claude/skills/` dan `.agents/skills/`:

- `inertia-laravel-development`: sisi server dan aturan migrasi khusus SIPENAMAS.
- `inertia-react-development`: sisi klien Inertia v3 React. Namanya sama dengan skill Boost, jadi bisa ditimpa `php artisan boost:update` setelah paket Inertia terpasang.

Keduanya hanya berlaku untuk tugas migrasi yang sudah disetujui.

## 12. Sumber

- Inertia v3 upgrade guide: https://inertiajs.com/docs/v3/getting-started/upgrade-guide
- Inertia v3 CSRF: https://inertiajs.com/docs/v3/security/csrf-protection
- Inertia v3 testing: https://inertiajs.com/docs/v3/advanced/testing
- Inertia v3 HTTP requests (`useHttp`): https://inertiajs.com/docs/v3/the-basics/http-requests
- Inertia v3 file uploads: https://inertiajs.com/docs/v3/the-basics/file-uploads
- Laravel News, Inertia 3.0.0: https://laravel-news.com/inertia-3-0-0
- npm `@inertiajs/react` (dist-tags dan tanggal rilis): https://www.npmjs.com/package/@inertiajs/react
- Packagist `inertiajs/inertia-laravel`: https://packagist.org/packages/inertiajs/inertia-laravel
- Laravel Boost: `sipenamas_v2_backend/vendor/laravel/boost/.ai/inertia-*` dan `src/Mcp/Prompts/UpgradeInertiav3/`
