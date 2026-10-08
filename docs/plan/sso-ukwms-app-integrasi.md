# Rencana Integrasi SSO app.ukwms.ac.id ke SIPENAMAS V2

Status: **rencana, belum dieksekusi.** Disusun 2026-10-01 berdasarkan pembacaan kode V2, bukan asumsi.

> Catatan 2026-10-07: bila migrasi ke Inertia (`kajian-migrasi-inertia.md`) disetujui, Desain B (callback di SPA) diganti callback server-side lewat route web. Putuskan urutannya dulu supaya SSO tidak dikerjakan dua kali.

## 1. Ringkasan keputusan

| Hal | Keputusan | Alasan |
|---|---|---|
| Provider SSO | `https://app.ukwms.ac.id` (`GET /api/user`, `POST /api/logout`) | Sama seperti eOffice dan survey.ukwms.ac.id |
| Identitas | `userid` dari SSO dicocokkan ke `User.kodeperson` | Sudah terbukti sama saat integrasi survey.ukwms.ac.id |
| Desain alur | Callback di frontend (Desain B) | Paling sedikit perubahan; tidak butuh state server |
| Pemilihan peran | **Tidak ada role picker** | `EnsureRole` stateless; `activeRole` hanya state UI |
| Jalur login lama | Dipertahankan | 5 akun eksternal (`is_external = 1`) memakai password lokal |

Alur yang dituju:

```
SPA /login
  -> redirect ke https://app.ukwms.ac.id/login?redirect=<FE_ORIGIN>/sso/callback
  -> SSO autentikasi
  -> kembali ke <FE_ORIGIN>/sso/callback?token=SSO_BEARER
  -> SPA POST /api/v1/auth/sso/callback { ssoToken }
  -> backend validasi GET app.ukwms.ac.id/api/user -> { userid }
  -> backend cari User by kodeperson, terbitkan token Sanctum
  -> SPA simpan token, navigate ke intended path atau dashboard defaultRole()
```

## 2. Kenapa tidak perlu role picker

- `app/Http/Middleware/EnsureRole.php` memutuskan akses dari `$user->hasRole()` tiap request. Tidak ada peran aktif yang disimpan server.
- `AuthController::switchRole()` hanya mengembalikan payload `PersonResource::withActiveRole()`; tidak membuat token baru, tidak mengubah state server.
- `src/features/auth/LoginPage.jsx` sudah memakai `res.data.user.activeRole` untuk menentukan dashboard tujuan, tanpa menanyakan peran.
- `src/components/layout/RoleSwitcher.jsx` sudah menangani pergantian peran di dalam aplikasi.

Jadi callback SSO cukup menerbitkan token; peran pendaratan diambil dari `defaultRole()`.

Sebaran peran di DB lokal: 409 user satu peran, 243 dua peran, 98 tiga, 27 empat, 20 lima sampai sembilan. Per modul: PEN 999, AKR 157, REV 138, RKT 52, DKN 40, ADM 22, Super Admin 6.

## 3. Langkah backend

Urutan ini disusun supaya tiap langkah bisa diuji sendiri.

1. **Config.** Tambah di `config/services.php`:
   ```php
   'app_sso' => ['url' => env('APP_SSO_URL', 'https://app.ukwms.ac.id')],
   ```
   dan `APP_SSO_URL=https://app.ukwms.ac.id` di `.env`. Nama key dibedakan dari `ukwms_sso` yang sudah ada (API pegawai `api.ukwms.ac.id:8100`) supaya dua jalur bisa hidup bersama selama transisi.
   Setelah edit `.env`: `php artisan config:cache` lalu `chown www-data:www-data bootstrap/cache/config.php`.

2. **Client baru** `app/Services/Auth/AppSsoClient.php`, mencontoh pola `UkwmsSsoClient`:
   - `Http::withToken($ssoToken)->timeout(8)->get($base.'/api/user')`
   - tangkap `ConnectionException` dan kembalikan hasil gagal yang rapi, jangan biarkan jadi 500
   - kembalikan array shape `array{success: bool, userid: ?string}`
   - satu kali retry untuk cold-start DNS (seperti versi eOffice) boleh ditambahkan

3. **Refactor kecil di `AuthController`.** Ekstrak ekor `login()` (baris 62-81) menjadi satu private method, mis. `issueSession(User $user, ?string $roleHint)`, yang mengerjakan: insert `ZLogLogin`, resolusi `roleHint`/`defaultRole()`, error 403 `'Akun ini belum memiliki hak akses ke modul manapun.'`, `createToken('sipenamas_v2')`, dan response `PersonResource::withActiveRole()`. Dipakai oleh `login()` dan callback SSO. Jangan menduplikasi logika ini.

4. **Endpoint callback.** Method baru `ssoCallback(SsoCallbackRequest $request, AppSsoClient $sso)`:
   - validasi `ssoToken` wajib string
   - panggil client; gagal -> 401 `'Sesi SSO tidak valid atau sudah berakhir.'`
   - `User::where('kodeperson', $userid)->first()`; tidak ada -> 403 `'Akun tidak terdaftar di SIPENAMAS.'`
     **Jangan** auto-create seperti `login()`, karena `GET /api/user` hanya mengembalikan `userid` tanpa nama, sehingga baris baru akan lahir tanpa `nama`. 899 akun internal sudah disuplai `SyncUsersFromUwmsdm`.
   - lanjut ke `issueSession()`

5. **Route** di `routes/api.php`, di luar grup `auth:sanctum`, dengan throttle:
   ```php
   Route::post('/auth/sso/callback', [AuthController::class, 'ssoCallback'])->middleware('throttle:login');
   ```

6. **Logout.** `/auth/logout` sekarang hanya menghapus token Sanctum. Untuk mencabut token SSO perlu keputusan (lihat bagian 6). Opsi paling kecil: terima `ssoToken` opsional di body logout, lalu `POST {APP_SSO_URL}/api/logout` secara fire-and-forget dengan timeout 3 detik dan `try/catch` — logout lokal tetap jalan walau SSO tak terjangkau.

7. **Jangan sentuh** `UkwmsSsoClient`, jalur `is_external`, dan `/auth/login`.

## 4. Langkah frontend

1. **Env.** `VITE_SSO_URL=https://app.ukwms.ac.id` di `sipenamas_v2_frontend/.env.production`. Frontend produksi statis, jadi wajib `npm run build` lalu `chown -R www-data:www-data dist` setelah diubah.

2. **Route** `/sso/callback` di `src/routes/index.jsx`, sejajar `/login`, di luar `ProtectedRoute`.

3. **Halaman callback** `src/features/auth/SsoCallbackPage.jsx`:
   - baca `token` dari query
   - segera `history.replaceState` untuk membuang token dari URL dan history
   - panggil `authApi.ssoCallback(token)`
   - sukses: navigate ke intended path (lihat poin 5) atau dashboard sesuai `activeRole`
   - gagal: ke `/login` dengan pesan error

4. **API + store.** `authApi.ssoCallback()` di `src/services/api/authApi.js`, memakai key localStorage yang sudah ada (`sipenamas_token`, `sipenamas_user`); action padanan di `src/store/authStore.js` mencontoh `login()`.

5. **Intended path.** Sebelum redirect ke SSO, simpan path tujuan di `localStorage`. Sesudah callback, navigate ke sana dan turunkan `activeRole` dari prefix route (`/adm`, `/pen`, `/rev`, `/dkn`, `/rkt`, `/akr`). `localStorage` bertahan melewati perjalanan ke SSO karena origin frontend tidak berubah. Ini yang membuat link undangan langsung bekerja tanpa user memilih peran.

6. **LoginPage.** Tambah tombol "Masuk dengan SSO UKWMS" untuk akun internal; form username/password tetap ada untuk akun mitra eksternal.

## 5. Test

- Tambah `tests/Feature/Auth/SsoCallbackTest.php` dengan `Http::fake()`, pola mengikuti `tests/Feature/Auth/LoginTest.php`:
  - SSO valid + user ada -> 200, token terbit, `activeRole` = `defaultRole()`
  - SSO menolak token -> 401
  - SSO tidak terjangkau (`ConnectionException`) -> 401, bukan 500
  - `userid` tidak ada di tabel user -> 403, tidak ada user baru dibuat
  - user tanpa peran apa pun -> 403 dengan pesan yang sama seperti `login()`
  - throttle aktif
- 11 test di `tests/Feature/Auth/LoginTest.php` harus tetap hijau: jalur lama tidak berubah.
- Jalankan sempit: `php artisan test --compact tests/Feature/Auth`.
- `vendor/bin/pint --dirty --format agent` sebelum selesai.

## 6. Yang harus dikonfirmasi ke admin SSO sebelum eksekusi

1. Daftarkan callback URL: `https://sipenamasdev.ukwms.ac.id/sso/callback`.
2. Apakah parameter `redirect` divalidasi exact-match di sisi SSO.
3. Masa hidup token SSO. Kalau berumur panjang, pindah ke Desain A (callback di backend + one-time code), karena token di URL jadi risiko nyata.
4. Apakah `GET /api/user` bisa mengembalikan nama juga — menentukan apakah auto-create user bisa dihidupkan.
5. Semantik `POST /api/logout`: mematikan sesi SSO global atau hanya token itu. Ini menentukan apakah logout SIPENAMAS boleh memanggilnya.
6. Apakah akun mitra eksternal dikenal SSO. Perkiraan: tidak, jadi jalur password lokal tetap perlu.

## 7. Risiko dan catatan

- Token SSO lewat query string: dimitigasi `history.replaceState`, tapi tetap tercatat di access log nginx frontend. Kalau ini tidak diterima, pakai Desain A.
- `defaultRole()` memakai prioritas enum `ADM -> PEN -> REV -> DKN -> RKT -> AKR`, jadi dosen yang juga ADM selalu mendarat di `/adm/dashboard`. Ini perilaku legacy (`z_modulmodul`). Mengubahnya adalah keputusan produk, di luar lingkup rencana ini.
- Role `Super Admin` tidak ada di enum `Role` tapi ditangani khusus oleh `allowedRoles()`. Role `Wakil Rektor 2` punya 0 user.
- CLAUDE.md mewajibkan tabel "Status implementasi V2" di `docs/legacy-flow/penelitian.md` diperbarui karena ini mengubah aturan login.
- Tidak ada perubahan infrastruktur: tanpa port baru, tanpa perubahan CORS (origin frontend sudah diizinkan), firewall dan fail2ban tidak tersentuh.
- Rencana ini tidak menyelesaikan blocker login 899 akun internal untuk demo jangka pendek, karena langkah 6.1 menunggu admin SSO.

## 8. Urutan eksekusi yang disarankan

1. Konfirmasi butir 6.1 sampai 6.3 ke admin SSO.
2. Backend langkah 1-5 + test, dengan `Http::fake` — bisa selesai tanpa menunggu SSO hidup.
3. Frontend langkah 1-6.
4. Uji end-to-end memakai satu akun nyata setelah callback URL terdaftar.
5. Perbarui `docs/legacy-flow/penelitian.md`.
6. Putuskan nasib jalur `ukwms_sso` lama: dipertahankan atau dibuang.
