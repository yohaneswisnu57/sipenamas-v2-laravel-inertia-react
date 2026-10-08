# SIPENAMAS Development Guidelines

Sumber aturan tunggal untuk semua agent (Claude, Gemini, Codex, dll). `CLAUDE.md` hanya mengimpor file ini plus catatan khusus Claude. Ubah aturan di sini, bukan di salinan.

## Peta Proyek

- `sipenamas_v2_backend/`: Laravel 12, PHP 8.4, REST API saja (`/api/v1/{pen,rev,dkn,adm,rkt,akr}`). Aturan tambahan: `sipenamas_v2_backend/CLAUDE.md` dan `sipenamas_v2_backend/.ai/rules/`.
- `sipenamas_v2_frontend/`: React 18 (JavaScript), Vite, Tailwind 4. Aturan + desain sistem: `sipenamas_v2_frontend/CLAUDE.md`.
- `docs/legacy-flow/penelitian.md`: alur bisnis legacy + tabel "Status implementasi V2".
- `docs/plan/`: rencana kerja aktif (`tugas-YYYY-MM-DD.md`).
- `docs/reference/`: panduan PDF resmi (tidak di-commit, `*.pdf` di-gitignore).
- Kode legacy (read-only): `/home/wisnu/sipenamas_legacy_code/appz/ONAIR/{pen,dkn,adm,rev,akr,rkt}/`. Aturan bisnis mengikuti legacy, bukan asumsi.

## Core Principles

- Implement the smallest reasonable solution.
- Preserve existing business rules. Do not refactor unrelated code.
- Do not modify unrelated files. Do not introduce new architecture without a clear reason.
- Prefer existing project conventions. Avoid unnecessary abstractions.
- Do not change API contracts or the database schema unless the task requires it.
- Do not add composer/npm dependencies without approval.

## Session Bootstrap (Anti-Amnesia)

Sesi baru selalu mulai dengan konteks kosong.

1. Di awal sesi atau saat menerima tugas lanjutan, periksa `git status` dan `git log -n 5`, lalu baca rencana terbaru di `docs/plan/`. (Claude Code: hook `SessionStart` sudah menampilkan ringkasan ini otomatis.)
2. Jangan bertanya "apa yang harus dilakukan" jika status git atau rencana sudah menunjukkan posisi task.
3. `docs/plan/*.md` dan riwayat git adalah memori proyek. Perbarui checklist rencana setelah task di-commit.

## Task Scope

1. Pahami task. Baca hanya file yang relevan.
2. Fitur Penelitian/Abdimas: baca `docs/legacy-flow/penelitian.md` dulu, lalu kode legacy yang terkait. Perbarui tabel "Status implementasi V2" bila fitur V2 berubah.
3. Sebut asumsi bila requirement tidak jelas. Jangan eksplorasi seluruh repo kecuali diminta.
4. Gunakan skill hanya bila jelas bermanfaat. Jangan merantai banyak skill untuk perubahan kecil.

## Workflow & Autonomy

- Task yang disetujui atau bertanda `Agent? Ya` di rencana: jalankan siklus penuh tanpa berhenti di tengah:
  `analisis -> perubahan kode -> tulis/perbarui test -> jalankan test -> fix bila gagal -> format/build -> Activity Report`.
- Berhenti HANYA bila: task selesai dan terverifikasi; ada blocker kritis atau ambiguitas aturan bisnis legacy; atau pengguna meminta review/konfirmasi.
- Bila task terhalang: jangan menebak. Catat alasannya, lewati, lanjut.

## Verifikasi (perintah pasti)

| Area | Perintah | Kapan |
| --- | --- | --- |
| Backend format | `vendor/bin/pint --dirty --format agent` | Setiap ubah PHP (hook Claude menjalankan Pint per file) |
| Backend test | `php artisan test --compact <path atau --filter=...>` | Test yang terdampak; suite penuh sebelum push |
| Frontend test | `npx vitest run tests/<path>` | Test yang terdampak; `npx vitest run tests/features` sebelum push |
| Frontend build | `npx vite build` | Sebelum push bila frontend berubah |

- Frontend tidak memakai ESLint. Jangan menambah linter tanpa persetujuan.
- Test backend memakai sqlite + migrasi `sipenamas_v2_backend/database/migrations/testing/`. Kolom legacy yang belum ada: tambah migrasi testing baru.
- Jangan menghapus/melemahkan test supaya lulus. Jangan klaim test lulus bila tidak dijalankan.

## Keamanan & Larangan

- DB produksi tidak boleh disentuh sama sekali. DB lokal `dbsipenamas` (127.0.0.1) adalah salinan dev: baca/tulis hanya bila pengguna meminta.
- Jangan membaca/mengubah `.env`, `ukwms_ssl/`, `backup_sipenamas_production/`. Cek konfigurasi lewat `php artisan config:show <key>`.
- Jangan menyentuh nginx, konfigurasi server, sertifikat, atau `dist/` hasil build.
- Git: jangan force-push, jangan push ke `main` kecuali diminta, jangan `git reset --hard`/`git clean` (sesi lain bisa punya WIP di working tree yang sama). Stage hanya file milik task sendiri. Satu commit per task: `feat(...)`, `fix(...)`, `chore(...)`, `docs(...)`.
- Jangan commit file besar/biner (PDF, SQL dump, backup, docx upload).

## Communication & Activity Report

Jawaban ringkas. Laporkan blocker, jangan menebak. Setelah task selesai, tampilkan satu laporan (ini menggantikan ringkasan terpisah):

- **Task:** tujuan perubahan.
- **Changes:** file yang diubah + perubahan utama (jangan ulang seluruh diff).
- **Tests:** perintah yang dijalankan + hasil persis (lulus/gagal/jumlah).
- **Risks/Assumptions:** hal yang belum diverifikasi atau asumsi.
- **Next Step:** langkah terpenting berikutnya. Bila masih dalam proses, tulis kondisi saat ini di sini.

Opsional bila relevan: **Files Read** (file penting di luar file yang diubah) dan **Token Efficiency** (eksplorasi di luar scope atau skill yang dipakai).
