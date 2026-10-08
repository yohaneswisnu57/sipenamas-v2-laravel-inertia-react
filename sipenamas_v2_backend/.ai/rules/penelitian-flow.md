# Penelitian Flow Rules

Read this before touching any penelitian/abdimas controller, model, or migration.

## Full flow summary

```
[PEN] Isi usulan + tim
  → [Anggota] Setuju keanggotaan
  → [PEN] Upload proposal + lembar pengesahan (ISDOKUMENPROPOSALFINAL=1)
  → [DKN] Setujui usulan (TTD lembar pengesahan)
  → [ADM] Plotting reviewer
  → [REV] Kesediaan → Penilaian (borang) → Revisi ⇄ [PEN] Tanggapan & upload revisi → [REV] Verifikasi
  → [ADM] Final approval (LOLOS/TIDAK LOLOS, NOMINALDANA_FINAL) → Surat Tugas/STPP/SPD
  → [DKN] Tunjuk reviewer monev → [REV monev] Isi soal monev + kesimpulan
  → [PEN] Laporan akhir (dokumen, mahasiswa, luaran, kuesioner, dana penyertaan, lembar pengesahan)
  → [DKN] Verifikasi laporan akhir → [ADM] Status ketuntasan (TUNTAS/TUNTAS BERSYARAT/BELUM/BATAL)
```

## Key status columns on `penelitian`

| Column | Values | Set by |
|--------|--------|--------|
| `ISPENGAJUANFINAL` | 0=draft, 1=diajukan | PEN |
| `ISDOKUMENPROPOSALFINAL` | 1=proposal final | PEN |
| `APPROVALPERMOHONAN_ISAPPROVEBYDEKAN` | 1=approved, 0=rejected | DKN |
| `STATUSPENUNJUKANREVIEWER` | `FINAL` when done | ADM |
| `STATUSPENILAIANREVIEWER` | rekap penilaian | ADM |
| `STATUSFINALAPPROVAL` | `LOLOS`/`TIDAK LOLOS`/`-` | ADM |
| `ISDEKANAPPROVELAPORANAKHIR` | 1=approved | DKN |
| `STATUSKETUNTASAN` | `TUNTAS`/`TUNTAS BERSYARAT`/`BELUM`/`BATAL` | ADM |

## Business rules (non-obvious)

- **Cekal**: ketua dan anggota yang belum tuntas di-cekal dari mengajukan usulan periode berikutnya.
- **Reviewer ke-3**: diperlukan bila dua reviewer pertama berbeda signifikan nilainya.
- **Verifikator revisi**: reviewer yang sudah selesai verifikasi tidak bisa ditunjuk ulang (`5293b43`).
- **Dekan decision locked**: setelah approve/reject, tidak bisa diubah (`8422613`).
- **Reviewer decision locked**: setelah penilaian final, tidak bisa diubah (`8422613`).
- **Surat**: ST, STPP, SPD di-generate saat final approval LOLOS (`e63d17c`).
- **Abdimas**: pakai tabel + alur yang **sama** dengan `JENIS_PA = 'ABDIMAS'`.

## Legacy columns that replaced V2 tables

The V2 additive tables were dropped (`2026_09_30_123838_drop_unused_v2_tables.php`). Use these legacy columns instead:

| Dropped table | Legacy replacement |
|---------------|--------------------|
| `v2_dekan_keputusan` | `penelitian.APPROVALPERMOHONAN_*`, `_MSG_PENOLAKANDEKAN` |
| `v2_reviewer_kesediaan` | `penelitian_reviewer.ISAPPROVED`, `TSAPPROVED`, `KOMENTAR` |
| `v2_proposal_revisi` | `penelitian_reviewer.ISREVIEWERREVISI`, `REVISI_*`; `penelitian.ISDOKUMENPROPOSALREVISIFINAL`, `TINDAKLANJUT` |
| `v2_proposal_meta` | none (fitur "lanjutan" dihapus) |
| `v2_pencairan_termin` | Surat Pencairan Dana legacy (`CETAKSURATDANA_*`) |

Only remaining V2 table: `v2_penelitian_mitra`.

## Controllers map

| Role | Controller | Responsibility |
|------|-----------|---------------|
| Peneliti | `PenelitianController` | CRUD usulan, dokumen proposal |
| Peneliti | `PengesahanProposalController` | Lembar pengesahan + dana penyertaan |
| Peneliti | `RevisiProposalController` | Thread revisi |
| Peneliti | `LaporanAkhirController` | Laporan akhir + mahasiswa + dana |
| Peneliti | `CapaianLuaranController` | Capaian rencana target |
| Peneliti | `MonevHasilController` | Isi soal monev (sebagai peneliti) |
| Dekan | `ApprovalController` | Approve/reject proposal |
| Dekan | `MonevController` | Tunjuk reviewer monev |
| Dekan | `LaporanAkhirController` | Verifikasi laporan akhir |
| Admin | `PlottingController` | Assign reviewer + revisi verifikator |
| Admin | `FinalApprovalController` | Final approval LOLOS/TIDAK LOLOS |
| Admin | `SuratKeputusanController` | Generate & finalize surat |
| Admin | `KetuntasanController` | Set status ketuntasan |
| Reviewer | `PenugasanController` | Kesediaan, borang, verifikasi revisi |
