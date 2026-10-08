# SIPENAMAS V2 — Project Context

## What is this

SIPENAMAS V2 is a rewrite of legacy SIPENAMAS (Sistem Informasi Penelitian dan Pengabdian Masyarakat) for Universitas Katolik Widya Mandala Surabaya (UKWMS).

- **Stack:** Laravel 12, PHP 8.4 (composer constraint `^8.2`), Sanctum auth, Spatie permissions, MySQL (tests: sqlite)
- **Architecture:** REST API only (no Blade views except `welcome`). Frontend lives in the same monorepo: `../sipenamas_v2_frontend/`.
- **Auth:** Token-based via Sanctum. Multi-role per user (`switch-role` endpoint). Roles: `peneliti`, `reviewer`, `admin`, `dekan`, `rektorat`, `akreditasi`.

## Scope of V2 (implemented so far)

Source of truth for feature status: table "Status implementasi V2" in `docs/legacy-flow/penelitian.md` (§9). The summary below can lag behind it.

Only **Penelitian internal** is implemented. Abdimas uses the same tables (`JENIS_PA = "ABDIMAS"`) but no V2 routes exist yet.

### Modules implemented

| Module | Routes prefix | Status |
|--------|--------------|--------|
| Peneliti — usulan, proposal, revisi, laporan akhir | `api/v1/pen/` | Complete |
| Reviewer — kesediaan, borang penilaian, verifikasi revisi | `api/v1/rev/` | Complete |
| Dekan — approval proposal, monev, laporan akhir | `api/v1/dkn/` | Complete |
| Admin — plotting, final approval, surat (ST/STPP/SPD), ketuntasan | `api/v1/adm/` | Complete |
| Rektorat — dashboard strategis | `api/v1/rkt/` | Partial |
| Akreditasi — data mining, rekap luaran | `api/v1/akr/` | Partial |
| Abdimas | — | Not started |

## Database

Uses **legacy tables** directly. The additive tables `v2_dekan_keputusan`, `v2_reviewer_kesediaan`, `v2_proposal_revisi`, `v2_proposal_meta`, `v2_pencairan_termin` were **dropped** in `2026_09_30_123838_drop_unused_v2_tables.php` and replaced by legacy columns. Do not reintroduce them. The only remaining V2 table is `v2_penelitian_mitra`.

Key tables: `penelitian`, `penelitian_tim`, `penelitian_reviewer`, `penelitian_penilaianproposal`, `penelitian_penilaianproposal_detail`, `penelitian_penilaianproposal_revisi`, `penelitian_rencanatarget`, `penelitian_monevhasil`, `penelitian_mhs`, `skimpenelitian`.

## Key conventions

- All controllers under `app/Http/Controllers/Api/V1/{Role}/`
- API versioned: all routes under `/api/v1/`
- JENIS_PA filter: queries always scope `JENIS_PA = 'PENELITIAN'` (or 'ABDIMAS')
- Period filter: `PERIODEKEGIATAN_TAHUN` = active period year
- Enum files in `app/Enums/`
- Legacy support helpers in `app/Support/Legacy/`
- RBAC helpers in `app/Support/Rbac/`

## Legacy reference

Full legacy business rules: `docs/legacy-flow/penelitian.md` (relative to monorepo root `/home/wisnu/SIPENAMAS_V2/`).

Legacy PHP source: `/home/wisnu/sipenamas_legacy_code/appz/ONAIR/{pen,dkn,adm,rev,akr,rkt}/myphp/*.php`

**Always read `docs/legacy-flow/penelitian.md` before changing penelitian/abdimas business logic.**

## Recent work

Use `git log` and the newest `docs/plan/tugas-*.md`. Do not keep a commit list here; it goes stale.

## What is NOT done

- Abdimas (pengabdian) module — no routes, controllers, or business logic
- Pencairan termin UI (removed intentionally)
- Usulan lanjutan (removed intentionally)
- Full rektorat and akreditasi dashboards
