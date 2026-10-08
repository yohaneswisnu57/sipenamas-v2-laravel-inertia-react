# Referensi Alur Penelitian — SIPENAMAS Legacy

Dokumen acuan alur bisnis **Penelitian internal** (`penelitian.JENIS_PA = "PENELITIAN"`) di aplikasi legacy.
Baca dokumen ini sebelum menambah, mengubah, atau menghapus fitur penelitian di V2.

- Sumber legacy: `/home/wisnu/sipenamas_legacy_code/appz/ONAIR/{pen,dkn,adm,rev,akr,rkt}/myphp/*.php`
  (ExtJS frontend + endpoint PHP per file, dipilih lewat `?task=XXX`).
- Semua path legacy di bawah relatif ke `appz/ONAIR/`.
- DB lokal V2 (`dbsipenamas`) berisi data legacy asli — cek kolom dengan
  `php artisan tinker --execute 'Schema::getColumnListing("penelitian")'`.
- Abdimas internal memakai tabel & alur yang **sama** (`JENIS_PA = "ABDIMAS"`, skim `ISABDIMAS = 1`,
  file `*abdimas*.php`); perbedaannya dicatat di bagian akhir.

Status kolom "V2" di setiap tabel: **ada** / **sebagian** / **belum** — perbarui saat fitur V2 berubah.

---

## 0. Ringkasan alur

```
[PEN] Isi usulan + tim ──► [Anggota] Setuju keanggotaan ──► [PEN] Upload proposal + lembar pengesahan
      (cekal, kuota, anggaran)                                     (ISDOKUMENPROPOSALFINAL = 1)
                                                                              │
                                                                              ▼
[ADM] Plotting reviewer ◄── [DKN] Setujui usulan (TTD lembar pengesahan) ◄────┘
      │
      ▼
[REV] Kesediaan ─► Penilaian (borang) ─► Revisi ⇄ [PEN] Tanggapan & upload revisi ─► [REV revisi] Verifikasi
      │                                   (+ reviewer pembanding / reviewer ketiga bila perlu)
      ▼
[ADM] Final approval (LOLOS / TIDAK LOLOS, NOMINALDANA_FINAL) ─► Surat Tugas (ST/STPP) & Surat Pencairan Dana (SPD)
      │
      ▼
[DKN] Tunjuk reviewer monev ─► [Reviewer monev] Isi soal monev + kesimpulan (ISFINAL)
      │
      ▼
[PEN] Laporan akhir: dokumen hasil, mahasiswa, realisasi luaran, kuesioner, dana penyertaan, lembar pengesahan laporan
      │
      ▼
[DKN] Verifikasi laporan akhir (ISDEKANAPPROVELAPORANAKHIR) ─► [ADM] Status ketuntasan (TUNTAS / TUNTAS BERSYARAT / BELUM / BATAL)
      │
      └─► Belum tuntas ⇒ cekal ketua & anggota pada periode berikutnya (kembali ke tahap 1)
```

---

## 1. Kolom status utama tabel `penelitian`

| Kolom | Nilai | Diisi oleh |
|---|---|---|
| `PERIODEKEGIATAN_TAHUN` | tahun periode aktif (mis. `2026`) — **kunci filter periode di semua query legacy** | PEN saat buat usulan |
| `KDPERIODE`, `TAHUNUSULAN` | tidak dipakai legacy (NULL di hampir semua baris lama) | V2 |
| `ISPENGAJUANFINAL` | 0 = draft, 1 = diajukan | PEN |
| `ISDOKUMENPROPOSALFINAL` | 1 = proposal final (PDF + lembar pengesahan) sudah diset final | PEN |
| `LBRPENGESAHANPROPOSAL_NAMAFILE/_QRCODE/_ISFINAL` | lembar pengesahan proposal (.docx) | PEN (generate/final), DKN (stempel TTD) |
| `APPROVALPERMOHONAN_ISAPPROVEBYDEKAN`, `APPROVALPERMOHONAN_TIMESTAMP` | persetujuan dekan | DKN |
| `_MSG_PENOLAKANDEKAN` | alasan penolakan (V2) | DKN |
| `STATUSPENUNJUKANREVIEWER` | status plotting reviewer (mis. `FINAL`) | ADM |
| `STATUSPENILAIANREVIEWER` | rekap penilaian reviewer | ADM |
| `STATUSFINALAPPROVAL` | `LOLOS` / `TIDAK LOLOS` / `-` | ADM |
| `NOMINALDANA_FINAL`, `CATATANADMIN` | dana disetujui | ADM |
| `CETAKSURATTUGAS_*`, `CETAKSURATDANA_*` | NAMAFILE, NOMORSURAT, TANGGALSURAT, QRCODE, STATUS (`FINAL`) | ADM |
| `MONEVHASILBY` | KODEPERSON reviewer monev | DKN |
| `FILE_DOKUMENHASILPENELITIAN` | laporan akhir | PEN |
| `LBRPENGESAHANLAPHASIL_*` | lembar pengesahan laporan akhir | PEN, DKN |
| `ISDEKANAPPROVELAPORANAKHIR`, `TSDEKANAPPROVELAPORANAKHIR` | verifikasi laporan akhir | DKN |
| `DOKUMENKONTRAK_NAMAFILE` | dokumen kontrak | ADM/PEN (`importFiledk`) |
| `STATUSKETUNTASANPENELITIAN` | `TUNTAS` / `TUNTAS BERSYARAT` / `BELUM TUNTAS` / `BATAL` / `-` | ADM |

Tabel turunan: `penelitian_tim` (ketua & anggota dosen, `ISAPPROVED`), `penelitian_mhs`,
`penelitian_rencanatarget` (target & realisasi luaran), `penelitian_reviewer`,
`penelitian_penilaianproposal(_detail/_revisi)`, `penelitian_monevhasil`, `pengisiankuesionerpeneliti(_detail)`,
`penelitian_janggaran_*` (RAB).

Setting global: tabel `settingan` baris `THISISIT = 'arief@nuriman.id'` →
`KUOTA_PENELITIAN_KETUA` (3), `KUOTA_PENELITIAN_ANGGOTA` (5), `KUOTA_PENGABDIAN_KETUA` (2), `KUOTA_PENGABDIAN_ANGGOTA` (5)
(nilai di DB lokal per 2026-09-30).

---

## 2. Tahap Pengajuan (peneliti)

File: `pen/myphp/permohonanpenelitian.php` (+ `penelitian_tim0` sebagai tabel staging tim per operator).

### 2.1 Isi usulan — `ADD` / `EDT` / `DEL`
- Field: `KDPRODI`, `KDSKIMPENELITIAN`, `JUDULPENELITIAN`, `BIDANGPENELITIAN`, `SPONSOR`, `KDSUMBERDANA`,
  `NOMINALDANA`, `KOMPOSISIDANA_*`, `ISPENGAJUANFINAL` (checkbox "ajukan").
- `PERIODEKEGIATAN_TAHUN` = periode aktif (`periode.ISAKTIF = 1`).
- `PERMOHONANDIBUAT_KDPERSON` = login, `PERMOHONANDIBUAT_TIMESTAMP` = now.
- Tim disalin dari `penelitian_tim0`. Ketua dibuat oleh `SETUPTIM`: `PERAN = KETUA`, `ISAPPROVED = 1`.
  Anggota masuk dengan `ISAPPROVED = 0`.
- Edit menghapus lalu menulis ulang seluruh `penelitian_tim` (status approval anggota ikut tersalin dari staging).
- Jika `ISPENGAJUANFINAL = 1` → antrean WA `TPL01` (minta persetujuan anggota).
- Delete: hapus `penelitian` + `penelitian_tim` (tanpa guard status di server).
- `GETSUMBERDANA`: sumber dana default = `skimpenelitian.DEFKDSUMBERDANA`.
- Aturan UI (`pen/app.js`, controller `Permohonanpenelitian`):
  - Edit & Del hanya untuk `PERAN = KETUA` (`CEKPERANNYA`).
  - Form terkunci (tidak bisa disimpan) begitu `ISPENGAJUANFINAL = 1` — edit hanya selama draft.
  - Del ditolak bila `APPROVALPERMOHONAN_ISAPPROVEBYDEKAN = 1`.
  - Komposisi dana (persen): bahan & peralatan ≤ 70, perjalanan ≤ 40, laporan ≤ 5, total keempatnya harus 100.
  - Wajib isi: fokus penelitian, judul, skim, sumber dana, nominal dana.

### 2.2 Validasi (dipanggil UI sebelum simpan)
| Task | Aturan |
|---|---|
| `CEKCEKALKETUA` / `CEKCEKALANGGOTA` | Ditolak bila orang tsb ada di `penelitian_tim` (peran apa pun) pada penelitian **tahun sebelumnya** (`PERIODEKEGIATAN_TAHUN < tahun aktif`), `STATUSFINALAPPROVAL = LOLOS`, dan `STATUSKETUNTASANPENELITIAN` bukan `TUNTAS`/`TUNTAS BERSYARAT`/`BATAL`, `JENIS_PA` sama. Pesan menampilkan peran, judul, tahun. |
| `CEKDUPLIKASIANGGOTA` | NIK anggota tidak boleh dobel di tim. |
| `CEKKUOTAKETUA` | Jumlah usulan tahun ini (jenis sama, `STATUSFINALAPPROVAL <> 'TIDAK LOLOS'`, kecuali usulan yang sedang diedit) di mana login = `KETUA` & `ISAPPROVED = 1` harus `< KUOTA_*_KETUA`. |
| `CEKKUOTAANGGOTA` (form) | Maksud: tiap anggota belum melebihi `KUOTA_*_ANGGOTA`. **Bug legacy**: query memakai NIK login, bukan NIK anggota. Kuota anggota yang efektif ada di tahap 2.3. |
| `VALIDASIJUMLAHANGGOTA` | Jumlah anggota (`PERAN = ANGGOTA`) harus di antara `skimpenelitian.MINANGGOTA` dan `MAXANGGOTA`. |
| `CEKMAXANGGARAN` / `CEKMAXANGGARANSKIM` | Jika `sumberdana.ISDANALPPM = 1` (sumber dana default skim): batas = `skimpenelitian.ANGGARANPERPENELITIAN`. Selain itu: batas = `prodi_anggaran.ANGGARANPERPENELITIAN` (prodi + periode). Lolos bila `NOMINALDANA <= batas` **atau** `ISOPENBUDGET = 1` (pada cabang dana LPPM, variabel open budget legacy selalu 0). |

### 2.3 Persetujuan anggota — `pen/myphp/statuskesediaantim.php`
- List: baris `penelitian_tim` milik login dengan `ISAPPROVED <> 1` pada usulan `ISPENGAJUANFINAL = 1`.
- `CEKKUOTAANGGOTA`: (jumlah jadi KETUA + jumlah jadi ANGGOTA yang sudah approve, tahun ini, jenis sama) `< KUOTA_*_ANGGOTA`.
- `UPDATESTATUS`: `ISAPPROVED = 1`, `TSAPPROVED = now`. Jika semua anggota sudah setuju → WA `TPL02` + `TPL03`.
- Tidak ada aksi "tolak" di legacy.

### 2.4 Rencana target luaran — `pen/myphp/rencanatargetpenelitian.php`
- `generateData`: salin master `tabelrencanatarget` (per `KDSKIM`) ke `penelitian_rencanatarget`
  (KATEGORI, SUBKATEGORI, ISWAJIB, INDIKATORNYA, URUTAN). Item wajib otomatis `ISCHKTARGET = 1`.
- `editData`: peneliti mencentang target opsional (`ISCHKTARGET`).

### 2.5 Dokumen proposal & lembar pengesahan
- `pen/myphp/dokumenproposalpenelitian.php`
  - `cekBolehuploadprop`: boleh upload hanya jika `ISPENGAJUANFINAL = 1` **dan** semua `penelitian_tim.ISAPPROVED = 1`.
  - `importFeldokumenproposal`: simpan `FILE_DOKUMENPROPOSAL_SOURCE`, `_INIT`, `_FINAL` di `res/proposal/`.
  - `importFeltargetcapaian`: `FILE_TARGETCAPAIAN`.
  - `updateFinal`: set `ISDOKUMENPROPOSALFINAL`, lalu gabung lembar pengesahan ke PDF proposal (disisipkan setelah halaman cover).
- Urutan di UI: tombol "Lembar Pengesahan" (ketua) → isi DANA PENYERTAAN (`CEKDANAPENYERTAAN` wajib terisi) →
  GENERATE DOKUMEN → SET FINAL → baru upload proposal (`cekBolehuploadprop` juga mensyaratkan
  `LBRPENGESAHANPROPOSAL_ISFINAL = 1`) → SET DOKUMEN FINAL. UNDUH lembar hanya setelah disetujui Dekan.
- `permohonanpenelitian.php`:
  - `GENFELLEMBARPENGESAHAN`: dari template `TPL_HALAMAN_PENGESAHAN*.docx` → `LBRPENGESAHANPROPOSAL_NAMAFILE`, `_QRCODE`.
  - `SETFINALFELLEMBARPENGESAHAN`: tanda tangan ketua ke docx, `_ISFINAL = 1`.
  - `CEKPENGESAHANDISETUJUIDEKAN`, `DONLOTFELLEMBARPENGESAHAN`, `SISIPKANFILE` (merge PDF).
  - `UPDATEDANAPENYERTAAN`: `LBRPENGESAHANPROPOSAL_DANAMITRA` / `_DANAINKIND`.

**Syarat masuk antrean dekan:** `ISPENGAJUANFINAL = 1` **dan** `ISDOKUMENPROPOSALFINAL = 1` **dan** periode aktif.

V2: lihat §9.

---

## 3. Tahap Dekan (persetujuan usulan) — `dkn/myphp/approvalpermohonan.php`
- List: penelitian di fakultas `fakultas.KDDEKAN = login`, `ISPENGAJUANFINAL = 1`, `ISDOKUMENPROPOSALFINAL = 1`,
  `PERIODEKEGIATAN_TAHUN` = tahun aktif; kolom sisa anggota yang belum setuju (`cekKesediaananggota`).
- `setApprovaldekan(true)`: `APPROVALPERMOHONAN_ISAPPROVEBYDEKAN = 1`, timestamp (sekali), stempel gambar TTD/checkmark ke
  docx lembar pengesahan (`word/media/image1..3.jpg`), buat ulang PDF.
- `setApprovaldekan(false)`: reset approval + `LBRPENGESAHANPROPOSAL_NAMAFILE/_QRCODE = NULL`, `_ISFINAL = 0`.
- Tidak ada kolom alasan penolakan di legacy (V2 menambah `_MSG_PENOLAKANDEKAN` + `v2_dekan_keputusan`).

## 4. Tahap Plotting & Review
| Langkah | File legacy | Kolom |
|---|---|---|
| Admin pilih reviewer (max reviewer dicek) | `adm/setreviewer` (`statusPenunjukan`, `cekMaxreviewer`) | `penelitian_reviewer`, `STATUSPENUNJUKANREVIEWER` |
| Reviewer kesediaan | `rev/statuskesediaan` | `penelitian_reviewer.ISAPPROVED`, `TSAPPROVED` |
| Penilaian borang proposal | `rev/penilaianproposal` (`updateHasilpenilaian`, `updateStatusnya`, `revisiJudul`) | `penelitian_penilaianproposal(_detail)`: HASILPENILAIAN, KOMENTAR, REKOMENDASIBIAYA, STATUSPENILAIAN; `JUDULPENELITIAN_YGLAMA` |
| Komentar revisi | `rev/penilaianproposalrevisi` | `penelitian_penilaianproposal_revisi` |
| Tanggapan peneliti + upload revisi | `pen/hasilreviewpenelitian` (`CEKSTATUSREVIEW`, `CEKPERANNYA` ketua, `UPDATERESPON`, `IMPORTFELDOKUMENPROPOSAL`, `UPDATEFINAL`), `rev/penilaianproposalrevisirespon` | `KOMENRESPON` per baris komentar, `FILE_DOKUMENPROPOSAL_REV` & `_FINAL`, `TS_UPLOADPROPOSALREVISI`, `ISDOKUMENPROPOSALREVISIFINAL` (setelah final: tanggapan & upload terkunci) |
| Verifikasi hasil revisi | `rev/penilaianproposalhasilrevisi` | REVISI_HASILPENILAIAN, REVISI_KOMENTAR, STATUSPENILAIANREVISI |
| Reviewer revisi / pembanding / ketiga | `adm/penilaianreviewer` (`setStatusreviewerrevisi`, `getStatuspembanding`, `cekAdakahreviewerketiga`) | `ISREVIEWERREVISI`, `ISREVIEWERPEMBANDING`, `ISBUTUHREVIEWERKETIGA` |
| Rekap status penilaian | `adm/penilaianreviewer` (`updateStatuspenilaian`) | `STATUSPENILAIAN`, `STATUSPENILAIANREVIEWER` |
| Borang poster & presentasi | `soalpenilaianposter/presentasi` | `penelitian_penilaianposter/presentasi(_detail)` |

**Aturan penilaian (legacy `rev/penilaianproposal.php` `updateStatusnya`, `penilaianproposaldetail.php`):**
- Borang dipilih dari `skimpenelitian.KDSOALPENILAIANPROPOSAL` → `soalpenilaianproposal` → `_detail` (NOMOR, KRITERIAPENILAIAN (HTML), BOBOTPERSEN; total bobot 100).
- Skor per kriteria: 1, 2, 3, 5, 6, 7 (tanpa 4). Nilai = skor × bobot; disimpan di `penelitian_penilaianproposal(_detail)` (IDREVIEWER = `penelitian_reviewer.id`). Total maks 700 → `penelitian_reviewer.TOTALSKOR`.
- Rekomendasi reviewer (`HASILPENILAIAN`): total < 400 → `TOLAK`; selain itu `PERBAIKAN` bila ada komentar revisi, else `LOLOS`.
- Rekap usulan (`STATUSPENILAIANREVIEWER`): ada TOLAK → `TOLAK`; ada PERBAIKAN → `PERBAIKAN`; selain itu `LANJUT`. Setelah verifikasi revisi: `LANJUT (SUDAH PERBAIKAN)` / `TOLAK (BELUM PERBAIKAN)`.
- `ISBUTUHREVIEWERKETIGA = 1` bila ≥ 2 reviewer sudah menilai dan selisih skor tertinggi–terendah ≥ 200.
- Tidak ada penolakan otomatis di final approval — admin yang memutuskan.
- Plotting (`setreviewer.php editData`) mempertahankan baris reviewer yang tetap dipilih (tidak hapus-buat ulang); WA `TPL07` saat FINAL.

## 5. Final Approval & Dokumen — `adm/myphp/finalapproval.php`
- `editData`: `STATUSFINALAPPROVAL`, `NOMINALDANA_FINAL`, `CATATANADMIN`.
- Proses massal (dipilih banyak baris): `ceklistKelolosan` (semua harus LOLOS), `cekDuplikasinomor`,
  `genfileSurattugas` / `genfileSurattugaspp` (ST/STPP) → `CETAKSURATTUGAS_*`,
  `genfileSuratdana` (SPD) → `CETAKSURATDANA_*`, `setDokfinal` → `*_STATUS = 'FINAL'` + QR.
- Peneliti mengunduh surat tugas dari menu pen.
- Legacy **tidak** punya termin pencairan (V2 menambah `v2_pencairan_termin`).

## 6. Monev
- Penunjukan: `dkn/myphp/monevpenelitianpenunjukan.php` `updatePenunjukan` → `MONEVHASILBY`, buat baris
  `penelitian_monevhasil`, WA `TPL32`. Hanya penelitian `STATUSFINALAPPROVAL = LOLOS` **dan**
  `STATUSKETUNTASANPENELITIAN` TUNTAS / TUNTAS BERSYARAT di fakultas dekan (monev dilakukan setelah tuntas).
  Kandidat (`cmbreviewermonevpenelitian.php`): `person.ISGJM = 1`, sefakultas dengan prodi penelitian,
  bukan anggota `penelitian_tim`. Penunjukan boleh diganti/dikosongkan kapan saja.
- Pengisian: `pen/myphp/monevhasilpenelitian.php` (list `MONEVHASILBY = login`) + `monevpenelitianhasilreview.php`
  → `JAWAB01..08` (soal `soalmonevpenelitian`, pilihan A–E), `updateFinalkesimpulan` → `KESIMPULAN`, `ISFINAL`
  (ditolak final bila ada jawaban kosong).
- Rekap: `adm|dkn|akr|rkt/myphp/monevpenelitianhasil*.php`, `rekapmonevpenelitian.php`.

## 7. Laporan Akhir & Ketuntasan

### 7.1 Sisi peneliti — panel "hasilpenelitian" (`pen/myphp/hasilpenelitian.php`)
Daftar: penelitian di mana login ada di `penelitian_tim`, `PERIODEKEGIATAN_TAHUN` = tahun periode terpilih,
`STATUSFINALAPPROVAL = LOLOS` **atau** `STATUSKETUNTASANPENELITIAN = TUNTAS`. Tiga tombol berurutan:

| Tombol | Syarat (UI) | Isi | File / kolom |
|---|---|---|---|
| KUESIONER | — | Kuesioner kepuasan, satu pengisian per orang per periode (`JENIS_PA = PENELITIAN`). Jawaban A–D → skor 1–4. Lengkap bila semua soal `SKOR > 0`. | `kuesionerpenelitiandetail.php` (`CEKIDOT`, `LST`, `UPDATEROW`, `CEKSTATUSOK`); `soalkuesionerpeneliti(_detail)`, `pengisiankuesionerpeneliti(_detail)` |
| KELENGKAPAN LAPORAN | kuesioner lengkap **dan** `PERAN = KETUA` | DANA PENYERTAAN (mitra, inkind) · MAHASISWA (add/edit/del NIM) · GENERATE DOKUMEN · SET FINAL · UNDUH DOKUMEN | `updateDanapenyertaan` → `LBRPENGESAHANLAPHASIL_DANAMITRA/_DANAINKIND`; `penelitian_mhs`; `genFellembarpengesahan` (template `TPL_HALAMAN_PENGESAHAN_LAPAKHIR_PENELITIAN.docx` → `res/proposal/lbrpengesahan_lapakhir_{id}.docx`, `_NAMAFILE`, `_QRCODE`, tiga gambar TTD dikosongkan); `setfinalFellembarpengesahan` (gambar ke-2 = checkmark ketua, `_ISFINAL = 1`) |
| CAPAIAN DAN LUARAN | `PERAN = KETUA` **dan** `LBRPENGESAHANLAPHASIL_ISFINAL = 1` | Per target `ISCHKTARGET = 1`: upload dokumen, REALISASI, keterangan hasil | `rencanatargethasilpenelitian.php`: `IMPORTFILELUARAN` → `res/penelitian/luaran_{idtarget}.{ext}`, `FILE_DOC`, `FILE_EXT`; `UPDATE` → `ISCHKREALISASI`, `KETHASIL`, `STATUSTAYANG`; `CLEARFILELUARAN` |

Aturan tambahan:
- Setelah SET FINAL: GENERATE, SET FINAL, dan grid mahasiswa terkunci; DANA PENYERTAAN tetap bisa diubah.
- UNDUH lembar pengesahan hanya bila `ISDEKANAPPROVELAPORANAKHIR = 1` ("BELUM DISETUJUI DEKAN").
- REALISASI butuh `FILE_DOC` terisi ("SILAHKAN UPLOAD DOKUMEN TERLEBIH DULU").
- Target `ISADAINSENTIF = 1`: label centang menjadi PUBLISHED, muncul `STATUSTAYANG` (BELUM SUBMIT / SUBMITTED /
  ACCEPTED (LOA)) selama belum published, dan REALISASI butuh pengajuan insentif final
  (`insentif.IDPENELITIANREFF` = penelitian, `ISPENGAJUANFINAL = 1`; form "Kelengkapan Dokumen" = `insentivejurnal2.php`).
- Laporan penelitian sendiri diunggah sebagai target wajib "Unggah laporan penelitian" (master `tabelrencanatarget`).
- Tombol DOKUMEN KONTRAK, DANA INKIND, dan upload `FILE_DOKUMENHASILPENELITIAN` ada di kode tetapi **disembunyikan** di UI.
- Bug legacy: `UPDATE` membaca parameter `insentif`, UI mengirim `isinsentif` → `ISMENGAJUKANINSENTIF` selalu 0.
  `ISDONE` kuesioner tidak pernah terisi (variabel gelombang kosong); gerbang memakai hitungan skor, bukan kolom itu.

### 7.2 Dekan & admin
| Langkah | File legacy | Kolom |
|---|---|---|
| Verifikasi dekan | `dkn/approvallaporan`: list = fakultas dekan, `ISPENGAJUANFINAL`, `ISDOKUMENPROPOSALFINAL`, `LOLOS`, tahun periode. Jendela PERSETUJUAN hanya terbuka bila `LBRPENGESAHANLAPHASIL_ISFINAL = 1`; centang setuju terkunci setelah disetujui. `setApprovaldekan(true)`: timestamp sekali + gambar TTD 1 & 2 jadi checkmark. `(false)` hanya mode bypass: reset file pengesahan | `ISDEKANAPPROVELAPORANAKHIR`, `TSDEKANAPPROVELAPORANAKHIR` |
| Status ketuntasan | `adm/hasilpenelitian`: list `JENIS_PA = PENELITIAN`, tahun periode, bukan TIDAK LOLOS, dengan persen realisasi target. `updateStatusketuntasan` tanpa syarat; pilihan `-`/`TUNTAS`/`BELUM TUNTAS`/`TUNTAS BERSYARAT`/`BATAL`; WA `TPL31` bila tuntas | `STATUSKETUNTASANPENELITIAN` |
| Daftar belum tuntas | `*/penelitianbelumtuntas.php` | — |

## 8. Perbedaan Abdimas
- `JENIS_PA = "ABDIMAS"`, skim `ISABDIMAS = 1`, fokus dari `fokusabdimas`.
- Field tambahan: `ABDIMAS_TEMPATLOKASI`, `LBRPENGESAHANPROPOSAL_DANAMITRA`.
- Anggaran prodi memakai `prodi_anggaran.ABDIMAS_ANGGARANPERPENELITIAN` / `ABDIMAS_ISOPENBUDGET`.
- Kuota memakai `KUOTA_PENGABDIAN_*`; cekal hanya melihat riwayat Abdimas.
- Template pengesahan `TPL_HALAMAN_PENGESAHAN_ABDIMAS*`.

## 9. Status implementasi V2 (per 2026-10-01)

Rincian fitur legacy yang **belum** diport (reviewer pembanding, borang poster/presentasi, rekap monev,
insentif jurnal, halaman verifikasi QR, notifikasi): [backlog-v2-belum-diport.md](backlog-v2-belum-diport.md).
| Tahap | V2 |
|---|---|
| 2.1 Isi usulan | ada — `POST /pen/penelitian` (`ajukan = false` → draft), `PUT /pen/penelitian/{id}` (ketua, hanya draft), `DELETE /pen/penelitian/{id}` (ketua, belum disetujui Dekan). Anggota dosen dipilih via pencarian combobox master dosen (`/api/v1/dosen`, padanan legacy `cmbperson.php`). **Beda dari legacy (dipertahankan, keputusan user 2026-09-30)**: V2 memakai rincian RAB (`penelitian_janggaran_*`) + mitra + ringkasan, bukan komposisi dana persen (`KOMPOSISIDANA_*`) |
| 2.2 Validasi cekal/kuota/jumlah anggota/anggaran | ada — `ProposalEligibilityService` |
| `PERIODEKEGIATAN_TAHUN` diisi | ada |
| 2.3 Persetujuan anggota | ada — `GET /pen/kesediaan-tim`, `POST /pen/kesediaan-tim/{timId}/setuju`; UI `KesediaanTimPage` (`/pen/kesediaan-tim`) |
| 2.4 Rencana target | ada — dibuat otomatis saat submit; `GET/PUT /pen/penelitian/{id}/rencana-target` |
| 2.4–2.5 Rencana target & upload proposal (UI) | `KelengkapanPengajuanCard` di halaman detail usulan (status SUBMITTED) |
| 2.5 Upload proposal | ada — `POST /pen/penelitian/{id}/dokumen-proposal` (syarat: diajukan, semua anggota setuju, `LBRPENGESAHANPROPOSAL_ISFINAL = 1`), lalu `POST .../dokumen-proposal/final` (set `ISDOKUMENPROPOSALFINAL`; setelah itu proposal & rencana target terkunci). PDF gabungan tetap dibuat saat unduh, bukan disimpan saat final. Naskah terunggah = draft (boleh diganti) sampai set final; ketua/anggota bisa pratinjau lewat `GET /pen/penelitian/{id}/dokumen-proposal` (PDF inline), tombol "Pratinjau Proposal" di kartu Kelengkapan (2026-10-05) |
| 2.5 Lembar pengesahan | ada (keputusan user 2026-09-30: ikuti legacy) — ketua: `PUT /pen/penelitian/{id}/dana-penyertaan` → `POST .../lembar-pengesahan` (wajib dana penyertaan; simpan `LBRPENGESAHANPROPOSAL_NAMAFILE/_QRCODE`, file `res/proposal/lbrpengesahan_proposal_{id}.docx`) → `POST .../lembar-pengesahan/final` (`_ISFINAL = 1`, tanda tangan ketua muncul). `GET .../pengesahan`: `?preview=1` kapan saja setelah generate, unduh hanya setelah disetujui Dekan. **Beda**: tanda tangan berupa QR (requirement LPPM), bukan gambar checkmark; template V2 belum mencetak nominal dana mitra/in-kind; penolakan Dekan me-reset lembar (sesuai legacy). **Halaman detail usulan (2026-10-02, padanan `onPermohonanpenelitian_btnPrintlembarpengesahanClick`)**: kartu Kelengkapan Pengajuan tampil saat `SUBMITTED` **dan** `DITOLAK_DEKAN` (alasan penolakan ditampilkan) supaya ketua bisa generate & set final ulang; tombol unduh hanya untuk ketua, hanya setelah Dekan setuju, format PDF (lembar pengesahan + proposal gabungan; unduh DOCX dihapus dari UI karena menu DOWNLOAD docx legacy tersembunyi); QR ketua di kartu baru tampil setelah `_ISFINAL = 1`; "Dana Disetujui" menampilkan `-` sampai `NOMINALDANA_FINAL` terisi |
| 3 Dekan | ada — antrean hanya usulan dengan tim lengkap & proposal final; approve mengisi `APPROVALPERMOHONAN_TIMESTAMP` & `_KDDEKAN`; belum: stempel TTD ke docx pengesahan; Dekan melihat naskah proposal (`GET /dkn/proposal/{id}/dokumen-proposal`, PDF inline — padanan iframe `CEKFILEPENGESAHAN` legacy) di jendela telaah, bisa diunduh, dan tombol Setujui/Tolak baru aktif setelah naskah termuat; keputusan terkunci setelah disetujui (usulan keluar dari antrean, approve/reject ulang ditolak 422 — sama dengan legacy yang men-disable tombol; mode bypass legacy tidak diport); setelah disetujui status usulan `DISETUJUI_DEKAN` ("Disetujui Dekan, Menunggu Plotting") sampai plotting reviewer FINAL |
| 4 Plotting | ada — hanya usulan yang sudah disetujui Dekan; baris reviewer yang tetap dipilih dipertahankan; reviewer ke-3 bila ada yang menolak tugas **atau** `ISBUTUHREVIEWERKETIGA`; kesediaan reviewer terkunci setelah dikonfirmasi (`POST /rev/penugasan/{id}/kesediaan` ditolak 422; daftar penugasan memuat `kesediaanSaya`). **Beda dari legacy (keputusan user 2026-10-01)**: legacy `rev/statuskesediaan` tetap bisa diubah lewat *View / Update Status* |
| 4 Penilaian | ada — borang per skim (`GET /rev/penugasan/{id}/borang`, `POST .../penilaian` dengan `skor[]`), aturan legacy <400 TOLAK & rekap `STATUSPENILAIANREVIEWER` (`BorangPenilaianService`, `PenilaianAggregationService`). Aturan V2 lama "rata-rata <500 auto TIDAK LOLOS" dihapus (keputusan user 2026-09-30); penilaian hanya bisa dikirim sekali — setelah `STATUSPENILAIAN = FINAL` kirim ulang ditolak 422 dan halaman borang tampil baca-saja (`penilaianSaya` di `GET /rev/penugasan/{id}`), sama dengan legacy yang men-disable borang & tombol simpan. Reviewer bisa membaca naskah proposal (`GET /rev/penugasan/{id}/dokumen-proposal`, padanan legacy `cekFeldokumenproposal`/`FILE_DOKUMENPROPOSAL_INIT`) langsung di halaman borang sebelum menilai; catatan evaluasi tidak lagi berisi teks bawaan (2026-10-01). Penilaian bisa disimpan sebagai **DRAFT** (`status=DRAFT` pada `POST .../penilaian`, padanan legacy `UPDATESTATUSNYA`): skor/komentar/rekomendasi tersimpan tetapi belum dikunci dan belum ikut rekap (`recompute()` hanya dipanggil saat `FINAL`); default `FINAL`. Draf boleh belum lengkap (skor sebagian, catatan/rekomendasi kosong) seperti legacy yang menyimpan skor per sel; FINAL tetap wajib lengkap. Kirim FINAL wajib `rekomendasiDana` > 0 ("Mohon diisi rekomendasi biayanya.") dan catatan minimal 100 karakter ("Komentar minimal berisi 100 karakter."), padanan `onPenilaianproposal_btnSimpanClick` + `CEKNILAINOL` di `rev/app.js`; skor nol sudah mustahil karena pilihan skor 1–7 (2026-10-07). Draf tidak tampil ke pihak lain (`catatanRevisi`/`rekomendasi*` di resource hanya dari baris FINAL). UI: tombol "Simpan Draf" dan "Kirim FINAL" (2026-10-01). Tombol "Panduan Penilaian" mengunduh `res/BUTIRPENILAIAN.docx` lewat `GET /rev/panduan-penilaian` (padanan legacy `panduanPenilaian`; berkas harus ada di disk `legacy_res`). Reviewer bisa merevisi judul proposal (`POST /rev/penugasan/{id}/revisi-judul` dengan `judulBaru`, padanan legacy `REVISIJUDUL`): judul lama dicadangkan ke `JUDULPENELITIAN_YGLAMA` bila cadangan masih kosong; UI tombol "Revisi Judul" di kartu info proposal (2026-10-01) |
| 4 Revisi | ada (keputusan user 2026-09-30: tanggapan per komentar, tampil sebagai utas) — reviewer mengirim `komentarRevisi[]` saat penilaian → `penelitian_penilaianproposal_revisi.KOMENREVISI` (ada komentar ⇒ `PERBAIKAN`); ketua: `GET /pen/penelitian/{id}/revisi`, `PUT .../revisi/komentar/{komentarId}` (`KOMENRESPON`), `POST .../revisi/dokumen` (unggah naskah sebagai **draft** → `FILE_DOKUMENPROPOSAL_REV` & `_FINAL` + `TS_UPLOADPROPOSALREVISI`; boleh diulang, file lama dihapus; padanan `IMPORTFELDOKUMENPROPOSAL`), `GET .../revisi/dokumen` (pratinjau, padanan `CEKFELDOKUMENPROPOSAL`), lalu `POST .../revisi/final` (wajib sudah ada naskah; `ISDOKUMENPROPOSALREVISIFINAL = 1`, padanan checkbox SET DOKUMEN FINAL / `UPDATEFINAL`; setelah itu tanggapan & naskah terkunci). Draft tidak terlihat verifikator karena antreannya memfilter `ISDOKUMENPROPOSALREVISIFINAL = 1` (2026-10-07). Syarat legacy `CEKSTATUSREVIEW`: semua reviewer FINAL dan `periodegelombang.TGLREVISI_TO` gelombang aktif belum lewat. Tetap dari V2: verifikator revisi harus ditunjuk admin dulu. Reviewer melihat utas lewat `GET /rev/penugasan/{id}/komentar-revisi`. `penelitian.TINDAKLANJUT` tidak dipakai lagi. Verifikator revisi bisa membuka naskah awal (`.../dokumen-proposal`) dan naskah revisi (`GET /rev/penugasan/{id}/dokumen-proposal-revisi`, padanan `cekFeldokumenproposalinit`/`cekFeldokumenproposalrev`) langsung dari antrean verifikasi (2026-10-01). **Beda dari legacy (keputusan user 2026-10-01)**: verifikator yang sudah menyelesaikan verifikasi (DISETUJUI/DITOLAK) tidak boleh ditunjuk lagi di siklus revisi berikutnya untuk usulan yang sama (`RevisiCycleService::tunjukVerifikator` menolak 422 bila `STATUSPENILAIANREVISI = FINAL`; `reviewer1/2/3.sudahVerifikasiRevisi` di resource, opsi di-disable pada dropdown Tunjuk Verifikator Revisi); legacy `setStatusreviewerrevisi` tidak melarang reassign siapa pun. Mengganti pilihan sebelum ada yang menyelesaikan verifikasi (masih DRAFT) tetap bebas |
| 4 Reviewer pembanding | ada (2026-10-01) — admin tambah reviewer pembanding via `POST /adm/plotting/{id}/reviewer` dengan `isPembanding=true`; hanya bisa bila `ISBUTUHREVIEWERKETIGA=1` (konflik TOLAK vs LANJUT, atau selisih skor ≥ 200); baris disimpan `ISREVIEWERPEMBANDING=1`; resource memisahkan `reviewerPembanding` dari `reviewer1/2/3`; aggregation `hitungStatusPenilaian` memprioritaskan hasil pembanding di atas PERBAIKAN. `ISBUTUHREVIEWERKETIGA` di-recompute tiap `PenilaianAggregationService::recompute()` |
| 4 Belum | borang poster/presentasi |
| 5 Final approval | ada — status LOLOS/TIDAK LOLOS; surat massal padanan `GENFILESURAT` (yang aktif di legacy; `genfileSurattugas/pp/dana` sudah tidak dipanggil): `POST /adm/surat/generate` (`ids[]`, `nomor` awal, `tanggal`, `abaikanDuplikasi`) — LOLOS ⇒ ST (nomor N, `surattugas_{id}.docx`) + SPD (N+1, `suratdana_{id}.docx`), selain itu STPP (N, `surattugaspp_{id}.docx`); dana = `NOMINALDANA_FINAL` × 70%; `KODEANGGARAN` dari `skimpenelitian.NOMORKODEANGGARAN` + `tabelkodeanggaran`; jangka waktu dari `periode.TGLPELAKSANAAN*` tahun kegiatan; nomor bentrok ⇒ 409, lanjut bila dikonfirmasi (dialog legacy). Ditolak bila ada surat FINAL di pilihan (maksud `CEKLISTKEFINALAN`; versi legacy tidak efektif karena `jenis` tidak dikirim UI). `POST /adm/surat/final` (QR ke `${MGZ}`, `*_STATUS = FINAL`; ditolak bila semua sudah FINAL). `GET /adm/final-approval/{id}/surat/{ST|STPP|SPD}`; peneliti `GET /pen/penelitian/{id}/surat-tugas` (hanya FINAL). UI: panel Generate/Set Final di `/adm/final-approval` (daftar juga memuat TIDAK LOLOS, MONEV, LAPORAN_AKHIR, TUNTAS) dan tombol "Unduh Surat Tugas" di detail usulan. **Beda**: STPP mengisi `${BEBAN}` (legacy menghitung tetapi tidak mengisinya); `terbilang` tidak lagi dobel "Rupiah". Halaman verifikasi QR: `GET /api/v1/dox/{JENIS}/{KODE}` + frontend `/dox/:jenis/:kode` (2026-10-07, lihat backlog §6). Belum: WA `TPL07`. Termin pencairan V2 **dihapus** (2026-09-30). Keputusan final (`POST /adm/final-approval/{id}`) hanya menulis `STATUSFINALAPPROVAL` + `NOMINALDANA_FINAL` seperti legacy `editData`; nomor SK buatan (`SURATTUGAS_NOMORSK` = `SK/LPPM/...` di backend, nomor acak di UI admin) dan field `nomorSk`/`tglSk` di resource **dihapus** (2026-10-02) — nomor resmi = `CETAKSURATTUGAS_NOMORSURAT` (export SINTA ikut memakainya bila surat FINAL) |
| 6 Monev | ada — penunjukan Dekan (`GET /dkn/monev`, `GET /dkn/monev/{id}/kandidat`, `POST /dkn/monev/{id}/penunjukan`; UI `/dkn/monev`) dan pengisian reviewer monev di menu peneliti (`GET /pen/monev-hasil[/{id}]`, `PUT .../jawaban`, `POST .../kesimpulan`; UI `/pen/monev-hasil`). Endpoint V2 lama `POST /pen/penelitian/{id}/monev` (diisi peneliti) dihapus. Monev **Abdimas** ikut lewat endpoint yang sama dengan `?jenis=ABDIMAS` (default `PENELITIAN`); master soal `soalmonevabdimas`, jawaban tetap di `penelitian_monevhasil`. **Beda dari legacy (2026-10-01)**: (a) antrean abdimas memakai ejaan `TUNTAS BERSYARAT` yang benar — legacy `monevabdimaspenunjukan.php` menulis `TUNTAS BESYARAT` sehingga abdimas bersyarat tidak pernah muncul; (b) kelengkapan jawaban sebelum `ISFINAL` dihitung dari jumlah baris master soal jenis tersebut, bukan dipatok 8 (legacy memeriksa `JAWAB01..08` untuk penelitian tetapi hanya `JAWAB01..07` untuk abdimas). Belum: WA `TPL32`/`TPL33`, rekap monev adm/dkn/akr/rkt |
| 7.1 Laporan akhir (peneliti) | ada — UI `/pen/laporan-akhir`. Kuesioner: `GET /pen/kuesioner-penelitian?kdperiode=`, `PUT /pen/kuesioner-penelitian/{detailId}`. Kelengkapan: `GET /pen/laporan-akhir[/{id}]`, `PUT .../dana-penyertaan`, `POST|PUT|DELETE .../mahasiswa`, `POST .../lembar-pengesahan[/final]`, `GET .../lembar-pengesahan[?preview=1]`. Capaian: `GET .../capaian`, `PUT .../capaian/{targetId}`, `POST|DELETE|GET .../capaian/{targetId}/dokumen`. Syarat UI legacy dijaga di server (`LaporanAkhirGate`). Endpoint V2 lama `POST /pen/penelitian/{id}/laporan-akhir` dihapus (padanan legacy-nya disembunyikan). Belum: form pengajuan insentif dari target ber-insentif (`insentivejurnal2.php`), dokumen kontrak & dana inkind (disembunyikan di legacy) |
| 7.2 Verifikasi dekan | ada — `GET /dkn/laporan-akhir?kdperiode=`, `POST /dkn/laporan-akhir/{id}/approve` (syarat UI legacy: lembar pengesahan laporan final; sekali setuju tidak bisa dibatalkan; tanda tangan Dekan dibubuhkan ke docx), `GET .../lembar-pengesahan` (pratinjau); UI `/dkn/laporan-akhir`. Pembatalan persetujuan (mode bypass legacy) tidak diport |
| 7.2 Status ketuntasan | ada — `GET /adm/ketuntasan?kdperiode=`, `PUT /adm/ketuntasan/{id}` (`-`, `TUNTAS`, `BELUM TUNTAS`, `TUNTAS BERSYARAT`, `BATAL`); UI `/adm/ketuntasan`. Belum: WA `TPL31` |
| Filter tahap (peneliti/admin/Dekan) | ada — `?tahap=` di daftar peneliti; filter di plotting & dasbor admin; Dekan: `GET /dkn/pengajuan?kdperiode=` (usulan final fakultas Dekan via `fakultas.KDDEKAN`) + UI `/dkn/pengajuan`. Dasar legacy `dkn/penelitianbelumtuntas.php`: belum tuntas = `STATUSFINALAPPROVAL = LOLOS` dan `STATUSKETUNTASANPENELITIAN` bukan `TUNTAS`/`TUNTAS BERSYARAT`. Proses = belum LOLOS/TIDAK LOLOS; Berjalan = LOLOS belum tuntas; Tuntas = TUNTAS/TUNTAS BERSYARAT. Dicek ke DB nyata (776 baris PENELITIAN): selisih 2 baris anomali data (LAPORAN_AKHIR tanpa LOLOS) |
| Pagu anggaran Dekan | ada (2026-10-02) — `GET /dkn/anggaran-penelitian?kdperiode=` (padanan `dkn/anggaranpenelitian.php`), read-only seperti legacy (`editData()` legacy dikomentari). Pagu per **prodi** di `prodi_anggaran` (ALOKASIANGGARAN, ANGGARANPERPENELITIAN, JUMLAHPENELITIAN, ISOPENBUDGET, CATATAN); kolom hitung: pengajuan = SUM(NOMINALDANA), disetujui = SUM(NOMINALDANA_FINAL) LOLOS dari skim dengan `sumberdana.ISDANALPPM <> 1`, danaLppm = varian `ISDANALPPM = 1`, sisa = alokasi − disetujui. UI `/dkn/pagu`. **Tidak diport**: `anggaranfakultas.php`/`fakultas_anggaran` — tabel tidak ada di DB produksi dan panelnya tanpa item menu (kode mati) |
| Monitoring belum tuntas (Dekan) | ada (2026-10-02) — `GET /dkn/belum-tuntas?kdperiode=` (padanan `dkn/penelitianbelumtuntas.php`): LOLOS dan `STATUSKETUNTASANPENELITIAN` bukan TUNTAS/TUNTAS BERSYARAT, lingkup `fakultas.KDDEKAN`, `kdperiode=ALL` didukung. UI `/dkn/monitoring` (read-only; tombol "Kirim Peringatan" mock dihapus — pengingat legacy lewat antrian WA) |
| Survei MBKM (Rektorat) | ada (2026-10-02) — `GET /rkt/mbkm/pengisian`, `/rkt/mbkm/rekap-prodi?kampus=SURABAYA|MADIUN`, `/rkt/mbkm/belum-mengisi`, `/rkt/mbkm/data-hasil/{dosen|mahasiswa|tendik}` (padanan `rkt/mbkm.php`, `mbkmbyprodi.php`, `mbkmbelum.php`, `mbkmdatahasil*.php`). Tabel `mbkm_mhs` + `mbkm_datahasil_*`; prodi diturunkan dari NIM lewat `prodi.PREFIXNIK` (awalan terpanjang). Modul legacy = **survei berhadiah**, bukan keterlibatan riset: mock V2 lama (`totalSksTerkonversi`, `dosenPembimbing`) dihapus karena tidak ada di skema legacy. UI `/rkt/mbkm` |
| Daftar usulan per jenis (peneliti) | ada (2026-10-02) — `GET /pen/penelitian?jenis=PENELITIAN|ABDIMAS` (default PENELITIAN; padanan LST `permohonanpenelitian.php`/`permohonanabdimas.php` yang memfilter `JENIS_PA`), `?tahun=` memakai `PERIODEKEGIATAN_TAHUN`. Sebelumnya daftar penelitian ikut memuat baris ABDIMAS. Resource: `tahun` = `TAHUNUSULAN` atau `PERIODEKEGIATAN_TAHUN` (baris legacy), `tempatLokasi` = `ABDIMAS_TEMPATLOKASI`. UI `/pen/abdimas` membaca daftar ini (data dummy dihapus); **pengajuan Abdimas baru belum diport** (tombol dihapus) |
| Dashboard peneliti | ada — banner "Tindakan Diperlukan" hanya untuk usulan REVISI milik ketua (legacy `hasilreviewpenelitian` khusus ketua; MONEV dikeluarkan karena diisi reviewer monev), batas revisi dari `periodegelombang.TGLREVISI_TO` gelombang aktif (`batasRevisi` di `GET /pen/dashboard`); tanggal & tren statis dihapus (2026-10-02) |
| Insentif jurnal & subsidi APC (pilihan index) | sebagian (2026-10-02) — "Terindeks Dalam" dari master `indexjurnal` (`GET /index-jurnal`, urut URUTAN; `?apc=1` = `cmbindexjurnalapc.php`, hanya `BOLEHAPC = 1`), disimpan sebagai `KODEINDEXJURNAL` di `INFOJURNAL_TERINDEKDALAM`. Insentif: `KDJENISPUBLIKASI` dan `ISMENGAJUKANINSENTIF` (= `ADAINSENTIF`) diisi dari index seperti `GETJENISPUBLIKASI`. Nominal reward rekaan (15/10/5/3 jt) dihapus — tabel legacy tidak punya kolom nominal. Sisa alur legacy (penulis, lampiran, verifikasi) tetap di backlog |
| Unduh panduan & template per skim | ada (2026-10-05) — `GET /pen/template`, `GET /pen/template/{tplNN-jenis}/unduh`, padanan legacy `cekFeltplproplap` (`FNAME_TPL<indek><PROPOSAL\|LAPORAN\|PANDUAN>` di `res/tpldoc/`, dibaca lewat disk `legacy_res`). Indek 01–08 = KODESKIM INT01–INT08 (nama dari `skimpenelitian.NAMASKIM`), 08B = LPJ keuangan, 09 = SOP Abdimas, 10 = Rencana Target. Dashboard peneliti mengelompokkan per skim. Proposal & laporan diunduh; panduan hanya dibaca (`Content-Disposition: inline`), tetapi tombolnya disembunyikan dulu di dashboard (keputusan user 2026-10-05) |
| Notifikasi WhatsApp | belum — legacy memakai Wappin (API template cloud `api.chat.wappin.app`), antrian `z_log_wa_msg` + template `settingan_tplnotifikasi` (TPL01..TPL36) dan broadcast `adm/notifpengumuman.php` atas `timeline`/`timeline_detail`. Di produksi TPL01–TPL14 (alur penelitian) semuanya `ISAKTIF = 0`. `adminApi.getWhatsappGatewayStatus`/`sendWhatsappBroadcast` masih mock |
| Uji alur penuh | `tests/Feature/AlurPenelitianLintasPeranTest.php` (semua peran lewat HTTP, pengajuan → TUNTAS → monev) dan `sipenamas_v2_frontend/tests/integration/alurPenelitian.integration.test.js` (modul API frontend terhadap backend sqlite sekali pakai, data dari `E2eAlurPenelitianSeeder`) |

### Tabel tambahan V2 (audit 2026-09-30)
Prinsip: pakai tabel/kolom legacy; tabel V2 hanya untuk user & RBAC.

| Tabel V2 | Status | Pengganti legacy |
|---|---|---|
| `v2_dekan_keputusan` | dihapus | `penelitian.APPROVALPERMOHONAN_ISAPPROVEBYDEKAN/_TIMESTAMP/_KDDEKAN/_CATATANDEKAN`, `_MSG_PENOLAKANDEKAN` |
| `v2_reviewer_kesediaan` | dihapus | `penelitian_reviewer.ISAPPROVED/TSAPPROVED`; menolak = `ISAPPROVED=0` + `TSAPPROVED` terisi + belum `STATUSPENILAIAN=FINAL`; alasan di `KOMENTAR` |
| `v2_proposal_meta` | dihapus | fitur "usulan lanjutan" dihapus |
| `v2_pencairan_termin` | dihapus | Surat Pencairan Dana legacy (`CETAKSURATDANA_*`) — belum diimplementasi |
| `v2_proposal_revisi` | dihapus — verifikator wajib salah satu reviewer usulan (keputusan user 2026-09-30); status diturunkan `RevisiState`; tanggapan peneliti per komentar di `KOMENRESPON` | `penelitian_reviewer.ISREVIEWERREVISI/REVISI_HASILPENILAIAN (SUDAH/BELUM)/REVISI_KOMENTAR/STATUSPENILAIANREVISI`, `penelitian.FILE_DOKUMENPROPOSAL_REV/TS_UPLOADPROPOSALREVISI/ISDOKUMENPROPOSALREVISIFINAL`, `penelitian_penilaianproposal_revisi.KOMENREVISI/KOMENRESPON` |
| `v2_penelitian_mitra` | dipertahankan sementara (user akan review) | legacy hanya nominal `LBRPENGESAHANPROPOSAL_DANAMITRA` |
| `users`, `personal_access_tokens`, tabel permission, `homebase_prodi_map`, `user_kodeperson_aliases`, `impersonation_logs` | dipertahankan | domain user & RBAC V2 |
