# SIPENAMAS — Claude Code

@AGENTS.md

## Khusus Claude Code

- Konfigurasi proyek ada di `.claude/settings.json` (root, backend, frontend; isi permission + hook harus sama). Hook:
  - `SessionStart`: menampilkan branch, `git status`, 5 commit terakhir, dan task terbuka dari `docs/plan/` terbaru.
  - `PostToolUse` (Edit/Write): menjalankan Pint pada file PHP backend yang baru diedit.
- Skill proyek ada di `.claude/skills/` (Boost: `laravel-best-practices`, `testing-best-practices`, `laravel-permission-development`, `tailwindcss-development`, `infer-conventions`). Baca `testing-best-practices` sebelum menulis test backend.
- Skill `inertia-laravel-development` dan `inertia-react-development` hanya untuk tugas migrasi Inertia yang sudah disetujui (`docs/plan/kajian-migrasi-inertia.md`). Inertia belum terpasang; kode sekarang masih SPA + REST.
- Repo ini memakai sparse-checkout. Folder `.claude/` dan `.agents/` harus ada di daftar `git sparse-checkout list`; bila hilang, jalankan `git sparse-checkout add .claude .agents`.
- Simpan fakta lintas sesi yang tidak tercatat di repo ke auto-memory, bukan ke file ini.
