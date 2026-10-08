# Backlog Fitur Legacy yang Belum Diport ke V2

Catatan alur untuk fitur penelitian/abdimas yang masih ada di aplikasi legacy tetapi belum
diimplementasikan di V2. Dokumen ini pelengkap [penelitian.md](penelitian.md) §9 ("Status implementasi V2"),
dipakai sebagai pengingat saat fitur-fitur ini mulai dikerjakan.

- Sumber legacy: `/home/wisnu/sipenamas_legacy_code/appz/` (semua path di bawah relatif ke sana).
- Notifikasi WhatsApp (`TPL01`, `TPL02`, `TPL03`, `TPL07`, `TPL31`, `TPL32`, `TPL33`) **tidak akan diport apa adanya**.
  Rencana: diganti notifikasi email di V2. Daftar titik kirim ada di bagian 6.
- Status per 2026-10-01.

---

## 1. Reviewer Pembanding

**Legacy:** `ONAIR/adm/myphp/penilaianreviewerpembanding.php`, pemilih orang `ONAIR/adm/myphp/cmbreviewerpembanding.php`.

Reviewer pembanding adalah reviewer tambahan yang ditunjuk admin ketika hasil dua reviewer utama
dianggap perlu pembanding. Secara teknis dia hanyalah baris lain di tabel `penelitian_reviewer`
dengan flag `ISREVIEWERPEMBANDING = 1`.

| Task | Perilaku |
|---|---|
| `LST` | Baris `penelitian_reviewer` dengan `IDPARENT = idp`, `ISREVIEWERPEMBANDING = 1`, dan `ISREVIEWERREVISI = 0`. Kolom tampil: nama, `TOTALSKOR` (nilai utama), `REVISI_TOTALSKOR` (nilai revisi), `HASILPENILAIAN`. |
| `ADD` | Buat baris baru: `IDPARENT`, `NIK`, `ISREVIEWERPEMBANDING = 1`. Tidak ada `PERAN`/`URUTAN`. |
| `EDT` | Hanya ganti `NIK`. |
| `DEL` | Hapus baris. |

Kandidat (`cmbreviewerpembanding.php`): tabel `person` yang `ISREVIEWERPENELITIAN = 1`
(atau `ISREVIEWERABDIMAS = 1` bila usulan abdimas). Label dropdown menampilkan beban review berjalan:
jumlah penugasan `ISAPPROVED = 1` pada penelitian dengan `STATUSPENUNJUKANREVIEWER = 'FINAL'` di periode aktif.

Catatan: legacy tidak memberi validasi apa pun (tidak cek duplikat, tidak cek maksimum, tidak cek
apakah orang itu anggota tim). Kalau diport ke V2, validasi perlu ditambah sendiri.

**Yang perlu dibangun di V2:** endpoint admin untuk daftar/tambah/ganti/hapus reviewer pembanding,
plus pemakaian flag `ISREVIEWERPEMBANDING` di agregasi penilaian (saat ini `PenilaianAggregationService`
belum membedakannya).

---

## 2. Borang Penilaian Poster & Presentasi

**Legacy:** `ONAIR/adm/myphp/soalpenilaianposter.php`, `soalpenilaianposterdetail.php`,
`soalpenilaianpresentasi.php`, `soalpenilaianpresentasidetail.php`, pemilih `cmbsoalpenilaianposter.php`.

Temuan penting: di legacy **hanya master soalnya** yang ada. Admin bisa membuat paket borang poster
dan presentasi (header + detail kriteria dengan `KRITERIAPENILAIAN` dan `BOBOTPERSEN`, pola sama persis
dengan borang proposal), tetapi **tidak ada halaman reviewer untuk mengisi borang tersebut**.
Tabel `penelitian_penilaianposter(_detail)` dan `penelitian_penilaianpresentasi(_detail)` tidak
disentuh satu pun endpoint PHP.

Pola master soal:
- Tabel staging `soalpenilaianposter_detail0` (per operator) dipakai saat edit, lalu disalin ke tabel final.
- Task tambahan: `CLEARTMP`, `FILLTMP` (isi staging dari data tersimpan), `DUPLIKASI` (salin paket soal).

**Yang perlu dibangun di V2:** kalau fitur ini dilanjutkan, V2 harus mendesain sendiri sisi
pengisiannya karena tidak ada acuan legacy. Minimal: CRUD master soal + penugasan penilai + borang isian.
Konfirmasikan dulu ke LPPM apakah proses poster/presentasi memang dipakai.

---

## 3. Rekap Monev (admin, dekan, akreditasi, rektorat)

**Legacy:** `{adm,dkn,akr,rkt}/myphp/rekapmonevpenelitian.php` dan `rekapmonevabdimas.php`.

Keluarannya file Excel, bukan halaman grid. Alurnya:

1. Muat template `res/RekapMonevPENELITIAN.xlsx` dengan PhpSpreadsheet.
2. Tulis judul di `A1` ("REKAP MONEV PENELITIAN (tahun)") dan kalimat pengantar di `A2`.
3. Untuk tiap soal 1..10 dan tiap pilihan jawaban, hitung jumlah responden:

   ```sql
   SELECT COUNT(*) FROM penelitian_monevhasil A
   JOIN penelitian B ON (B.id = A.IDPARENT) AND (B.JENIS_PA = "PENELITIAN")
     AND (B.PERIODEKEGIATAN_TAHUN = :tahun)
   JOIN prodi C ON (C.KODEPRODI = B.KDPRODI)
   JOIN fakultas D ON (D.KODEFAKULTAS = C.KDFAKULTAS)
   WHERE (A.JAWAB01 = "A")
   ```

4. Tulis angka ke sel tetap: soal 1 → `D6..D8`, soal 2 → `D10..D12`, soal 3 → `D14..D16`,
   soal 4 → `D18..D22`, soal 5 → `D24..D28`, soal 6 → `D30..D32`, soal 7 → `D34..D36`,
   soal 8 → `D38..D42`, soal 9 → `D44..D48`, soal 10 → `D50..D54`.
5. Simpan ke `_tmp_/{operator}_{uniqid}.xlsx`, balas `{"success":"true","urlnya":"..."}`.

Filter: `kdperiode` (kosong/`*`/`ALL` berarti semua tahun) dan `kdfakultas` (`ALL` berarti semua).

Hal yang perlu diperhatikan:
- Soal 1,2,3,6,7 hanya punya pilihan A–C; soal 4,5,8,9,10 punya A–E.
- Jumlah soal di rekap adalah 10, padahal pengisian monev (`penelitian_monevhasil`) hanya memakai
  `JAWAB01..JAWAB08`. Jadi baris soal 9 dan 10 di Excel selalu nol. Jangan ikut-ikutan bug ini
  tanpa mengecek master `soalmonevpenelitian` lebih dulu.
- Legacy tidak memfilter `ISFINAL = 1`, jadi monev yang masih draft ikut terhitung.

**Yang perlu dibangun di V2:** satu endpoint rekap (hitungan di SQL atau koleksi) yang dipakai
bersama oleh peran adm/dkn/akr/rkt, plus ekspor Excel. Pertimbangkan menampilkan grid dulu, ekspor menyusul.

---

## 4. Monev Abdimas — SUDAH DIPORT (2026-10-01)

**Legacy:** `dkn/myphp/monevabdimaspenunjukan.php`, `adm/myphp/cmbreviewermonevabdimas.php`,
`pen/myphp/monevabdimashasil.php`, `pen/myphp/monevabdimashasilreview.php`.

Alurnya identik dengan monev penelitian yang sudah ada di V2, dengan perbedaan:

| Aspek | Penelitian | Abdimas |
|---|---|---|
| Filter daftar | `JENIS_PA = "PENELITIAN"` | `JENIS_PA = "ABDIMAS"` |
| Master soal | `soalmonevpenelitian` | `soalmonevabdimas` |
| Tabel jawaban | `penelitian_monevhasil` | **sama**, `penelitian_monevhasil` |
| Kolom penunjukan | `MONEVHASILBY` | **sama** |

Jadi hanya master soal dan filter jenis yang berbeda; penyimpanan jawaban memakai tabel yang sama
(`JAWAB01..JAWAB08`, `KESIMPULAN`, `ISFINAL`).

Kandidat reviewer monev abdimas (`cmbreviewermonevabdimas.php`): `person` sefakultas dengan prodi usulan
dan bukan anggota `penelitian_tim` usulan tersebut.

Bug legacy yang harus diperbaiki saat port: filter penunjukan abdimas menulis
`STATUSKETUNTASANPENELITIAN = "TUNTAS BESYARAT"` (kurang huruf `R`), sehingga abdimas berstatus
"TUNTAS BERSYARAT" tidak pernah muncul di antrean penunjukan. Versi penelitian mengejanya benar.

**Status V2 (2026-10-01): selesai.** Modul monev yang ada menerima parameter `jenis`
(`?jenis=PENELITIAN|ABDIMAS`, default `PENELITIAN`) lewat enum `App\Enums\JenisPa`, pada
`GET /dkn/monev`, `GET /dkn/monev/{id}/kandidat`, `POST /dkn/monev/{id}/penunjukan`,
`GET /pen/monev-hasil`, `GET /pen/monev-hasil/{id}`, `PUT .../jawaban`, `POST .../kesimpulan`.
Jenis di luar kedua nilai ditolak 422. Permission tetap `view/approve penelitian` dan
`submit monev penelitian` (legacy juga memakai menu peran yang sama).
Bug legacy `TUNTAS BESYARAT` tidak diport — ejaan benar dipakai untuk kedua jenis.
Kelengkapan jawaban sebelum `ISFINAL` dihitung dari baris master soal jenis tersebut, bukan
dipatok 8, sehingga perbedaan legacy (penelitian `JAWAB01..08`, abdimas `JAWAB01..07`) ikut benar.
Uji: `tests/Feature/Dekan/MonevPenunjukanTest.php`, `tests/Feature/Peneliti/MonevHasilTest.php`.

---

## 5. Pengajuan Insentif Jurnal dari Target Luaran

**Legacy:** `pen/myphp/insentivejurnal2.php` (form dari target luaran), `insentivejurnalpenulis2.php`
(daftar penulis), `insentivejurnallampiran2.php` (lampiran). Varian `*.php` tanpa angka 2 adalah
menu insentif mandiri (bukan dari penelitian).

Kaitannya dengan alur penelitian: pada tahap CAPAIAN DAN LUARAN, target dengan `ISADAINSENTIF = 1`
baru boleh dicentang REALISASI kalau sudah ada pengajuan insentif final
(`insentif.IDPENELITIANREFF` = id penelitian dan `ISPENGAJUANFINAL = 1`).

### 5.1 Pembuatan draft — task `GETDATAINSENTIVE`
Dipanggil saat peneliti membuka form dari baris target luaran (`idnyarencanatarget`):

1. Ambil `penelitian_rencanatarget` → dapat `IDPARENT` (id penelitian) dan `FILE_DOC` (file luaran yang sudah diunggah).
2. Kalau belum ada baris `insentif` untuk penelitian tersebut, buat satu dengan
   `KDPERSONPENGAJU` = login, `IDPENELITIANREFF`, `IDRENCANATARGETREFF`, `JENISREFF = 'INTERNAL'`,
   `TANGGALPENGAJUAN` = hari ini, `KDPRODI` dari penelitian, dan kolom sumber publikasi terisi otomatis:
   judul berformat `"{JUDULPENELITIAN} ({TAHUN}/INTERNAL/{id})"`, sumber dana, dan tahun.
   Untuk `JENIS_PA = PENELITIAN` isi ke `SUMBERPUBLIKASIPENELITIAN_*`, untuk abdimas ke `SUMBERPUBLIKASIABDIMAS_*`.
3. File luaran disalin otomatis dari `res/penelitian/` ke `res/insentive/` sebagai lampiran wajib
   (`insentif_lampiran` dengan `ISWAJIB = 1`), dinamai `LAMPIRANINSENTIVE_{id}.{ext}`.

Format judul sumber itu bukan kosmetik: saat simpan, legacy mem-parsing kembali teks dalam kurung
untuk mendapat `JENISREFF` dan `IDPENELITIANREFF`. Kalau V2 membuat ulang fitur ini, simpan id
sebagai kolom, jangan ikut mem-parsing string.

### 5.2 Isian form
Data artikel: `JUDULARTIKEL`, `INFOJURNAL_NAMAJURNAL`, `INFOJURNAL_TERINDEKDALAM`,
`INFOJURNAL_FAKTORDAMPAK`, `VOLUME`, `NOMOR`, `BULAN`, `TAHUN`, `NOMORHALAMANAWAL`,
`NOMORHALAMANAKHIR`, `KDJENISPUBLIKASI`, `URL`, `DOI`, `ISSN`, `EDITORINCHIEF`, `PUBLISHER`.
Ditambah daftar penulis (`insentif_penulis`, staging `insentif_penulis0`) dan lampiran
(`insentif_lampiran`, staging `insentif_lampiran0`).

### 5.3 Validasi sebelum simpan — task `CEKSYARATSIMPAN`
- **Keterisian**: semua field di atas wajib terisi, ditambah minimal satu penulis dan tidak boleh ada
  lampiran tanpa file. Pesan: `Isian data tidak boleh kosong: {daftar field}.`
- **Kemiripan judul** (`CEKSIMILAR`): bandingkan judul artikel dengan seluruh `insentif.JUDULARTIKEL`
  yang ada memakai perbandingan per-kata. Ambang dari `settingan.PERSENSIMILAR`. Hasil
  `ADASIMILAR` menampilkan daftar judul mirip dari tabel `tmpceksimilar`.
- **ISSN** (`CEKISSN`): validasi check digit ISSN standar — bobot 8,7,6,5,4,3,2 untuk tujuh digit
  pertama, digit cek = `11 - (total % 11)`, dengan 11→`0` dan 10→`X`.
- **DOI** (`CEKDOI`) dan **URL** (`CEKURL`): dicek keberadaannya. Catatan: pemanggilan `subcekUrl`
  sudah dikomentari di legacy, jadi URL praktis tidak divalidasi.
- **Duplikasi DOI** (`subcekPernah`): DOI yang sama tidak boleh diajukan dua kali.
- **First author**: tepat satu penulis dengan `ISFIRSTAUTHOR = 1`.
- **Corresponding author**: satu atau dua penulis dengan `ISCORRESPONDINGAUTHOR = 1`.

### 5.4 Simpan
`SIMPANDRAFT` menyimpan dengan `ISPENGAJUANFINAL = 0`, `SIMPANFINAL` dengan `= 1`.
Simpan akan menghapus lalu menulis ulang seluruh `insentif_penulis` dan `insentif_lampiran`
dari tabel staging. `CEKSTATUSFINAL` dipakai UI untuk mengunci form setelah final.
Persetujuan dekan atas insentif disimpan di `insentif.APPROVALPERMOHONAN_ISAPPROVEBYDEKAN`.

**Yang perlu dibangun di V2:** modul insentif tersendiri (tabel `insentif`, `insentif_penulis`,
`insentif_lampiran` sudah ada di DB legacy), dan gerbang REALISASI pada target ber-insentif di
`LaporanAkhirGate`. Lingkupnya besar — pertimbangkan dikerjakan terpisah dari alur penelitian.

---

## 6. Halaman Verifikasi QR Dokumen — SUDAH DIPORT (2026-10-07)

**V2:** `GET /api/v1/dox/{JENIS}/{KODE}` (publik, throttle 30/menit, `DokumenPublikController`) mencari kolom `*_QRCODE` seperti tabel di bawah lalu menampilkan dokumen sebagai PDF (konversi LibreOffice); kode salah → 404 `KODE SALAH ATAU DOKUMEN TIDAK DITEMUKAN.` Frontend `/dox/:jenis/:kode` langsung mengalihkan ke PDF itu (keputusan user: PDF saja, seperti legacy). QR surat ST/STPP/SPD berisi `{DOX_URL}/{JENIS}/{KODE}`, default `https://sipenamasdev.ukwms.ac.id/dox` (keputusan user: domain V2). Jenis `STX`/`STPPX`/`SPDX` (`penelitianexternal`) belum diport. Surat yang sudah di-set final sebelum perubahan ini masih memuat QR ke domain legacy.

**Legacy:** `appz/dox/index.php`. URL publik tanpa login:
`https://lppm.ukwms.ac.id/appz/dox/{JENIS}/{KODE}`.

`{KODE}` dicocokkan ke kolom QR code di tabel dokumen. Kalau cocok, nama file diambil dan isi
`res/proposal/{NAMAFILE}` ditampilkan di dalam frame. Kalau tidak, muncul
`KODE SALAH ATAU DOKUMEN TIDAK DITEMUKAN.`

| `{JENIS}` | Tabel | Kolom QR |
|---|---|---|
| `ST`, `STPP` | `penelitian` | `CETAKSURATTUGAS_QRCODE` |
| `SPD` | `penelitian` | `CETAKSURATDANA_QRCODE` |
| `STX`, `STPPX` | `penelitianexternal` | `CETAKSURATTUGAS_QRCODE` |
| `SPDX` | `penelitianexternal` | `CETAKSURATDANA_QRCODE` |
| `LPPP` (lembar pengesahan proposal penelitian) | `penelitian` | `LBRPENGESAHANPROPOSAL_QRCODE` |
| `LPPA` (lembar pengesahan proposal abdimas) | `penelitian` | `LBRPENGESAHANPROPOSAL_QRCODE` |
| `LPLAP` (lembar pengesahan laporan akhir penelitian) | `penelitian` | `LBRPENGESAHANLAPHASIL_QRCODE` |
| `LPLAA` (lembar pengesahan laporan akhir abdimas) | `penelitian` | `LBRPENGESAHANLAPHASIL_QRCODE` |

Catatan: pasangan penelitian/abdimas (`LPPP`/`LPPA`, `LPLAP`/`LPLAA`) sebenarnya menunjuk kolom yang sama —
pembedaannya hanya kosmetik.

**Yang perlu dibangun di V2:** route publik (tanpa autentikasi) yang menerima jenis + kode QR,
mencari dokumen, dan menyajikan filenya inline. Perlu diputuskan: domain mana yang dicetak ke QR,
dan apakah kode lama harus tetap bisa dibuka (kompatibilitas dokumen yang sudah tercetak).

---

## 7. Notifikasi (rencana: email, bukan WhatsApp)

Legacy mengantre pesan WhatsApp lewat `req_push.php` pada titik-titik berikut. V2 akan
menggantinya dengan email, jadi yang penting dipertahankan adalah **kapan** notifikasi dikirim
dan **siapa** penerimanya, bukan mekanismenya.

| Template | Dikirim saat | Penerima |
|---|---|---|
| `TPL01` | Usulan diajukan (`ISPENGAJUANFINAL = 1`) | Anggota tim — minta persetujuan keanggotaan |
| `TPL02`, `TPL03` | Semua anggota sudah menyetujui | Ketua (dan pihak terkait) |
| `TPL07` | Plotting reviewer diset `FINAL` | Reviewer yang ditunjuk |
| `TPL31` | Status ketuntasan diset `TUNTAS` | Peneliti |
| `TPL32` | Reviewer monev ditunjuk dekan | Reviewer monev |
| `TPL33` | Terkait pengisian monev | Peneliti/reviewer monev |

Saat mengerjakan notifikasi email, buat satu lapisan notifikasi tunggal dan kirim lewat queue
Laravel, agar titik pemicu di atas tidak tersebar di controller.

---

## 8. Reviewer menyimpan penilaian sebagai DRAFT — SUDAH DIPORT (2026-10-01)

**Legacy:** `ONAIR/rev/myphp/penilaianproposal.php` task `UPDATESTATUSNYA`.

Di legacy, pada bagian SUMMARY penilaian proposal reviewer memilih Status Penilaian **DRAFT** atau
**FINAL** dari sebuah dropdown, lalu klik SIMPAN. Nilai itu disimpan apa adanya ke
`penelitian_reviewer.STATUSPENILAIAN` (`$r->STATUSPENILAIAN = $par->STATUSPENILAIAN`). DRAFT berarti
simpan sementara — reviewer masih bisa membuka dan mengubah penilaian lagi; FINAL berarti penilaian
dikunci dan ikut direkap. Perilaku ini dikonfirmasi oleh "Panduan Penggunaan Sipenamas (Reviewer)"
langkah 6, yang menyebut DRAFT untuk menyimpan sementara dan FINAL untuk mengunci.

**Keadaan V2 sekarang:** `Reviewer/PenugasanController@submitPenilaian` selalu memaksa
`STATUSPENILAIAN = 'FINAL'` dan `abort_if(... === 'FINAL')`, sehingga sekali dikirim penilaian langsung
terkunci. `SubmitPenilaianRequest` juga belum punya field status DRAFT/FINAL. Tidak ada jalur simpan
sementara.

**Yang perlu dibangun di V2:** tambahkan parameter status (DRAFT/FINAL) pada request dan controller.
Saat DRAFT, simpan skor/komentar/rekomendasi tetapi jangan kunci dan jangan panggil
`PenilaianAggregationService::recompute()`; rekap tetap hanya memperhitungkan baris `FINAL`
(konsisten dengan `PenilaianAggregationService` yang sudah memfilter `STATUSPENILAIAN = 'FINAL'`).

**Status V2 (2026-10-01): selesai.** `SubmitPenilaianRequest` menerima `status` opsional (`DRAFT`/`FINAL`,
default `FINAL`). `PenugasanController@submitPenilaian` menyimpan `STATUSPENILAIAN = status`; `recompute()`
hanya dipanggil saat `FINAL`, sehingga DRAFT tidak dikunci (guard `abort_if STATUSPENILAIAN === 'FINAL'`
tetap berlaku, DRAFT masih bisa dikirim ulang) dan tidak ikut rekap. Uji:
`tests/Feature/Reviewer/PenilaianBorangTest.php::test_draft_penilaian_is_not_locked_and_not_aggregated`.

---

## 9. Reviewer mengubah judul proposal (REVISI JUDUL) — SUDAH DIPORT (2026-10-01)

**Legacy:** `ONAIR/rev/myphp/penilaianproposal.php` task `REVISIJUDUL`, tombol "REVISI JUDUL" di UI reviewer.

Reviewer dapat mengganti judul proposal. Legacy menyimpan judul baru ke `penelitian.JUDULPENELITIAN`
dan memindahkan judul sebelumnya ke `penelitian.JUDULPENELITIAN_YGLAMA` sebagai cadangan (hanya diisi
bila belum ada isi sebelumnya). Tidak ada validasi lain. Tombol ini muncul di "Panduan Penggunaan
Sipenamas (Reviewer)" langkah 3 dan 6 (label "REVISI JUDUL").

**Keadaan V2 sekarang:** tidak ada endpoint, service, maupun kolom `JUDULPENELITIAN_YGLAMA` yang dipakai.
Pencarian `revisijudul`/`judulrevisi` di `app/`, `routes/`, dan `database/migrations/` tidak menemukan apa pun.

**Yang perlu dibangun di V2:** endpoint reviewer untuk mengganti judul penelitian milik penugasannya,
menyimpan judul lama ke kolom cadangan sebelum menimpa. Pastikan kolom `JUDULPENELITIAN_YGLAMA` tersedia
di skema V2 sebelum mengimplementasikan.

**Status V2 (2026-10-01): selesai.** `POST /rev/penugasan/{id}/revisi-judul` (`judulBaru`, permission
`submit penilaian penugasan`), guard `ownPenugasan()` memastikan reviewer memang ditugaskan ke usulan itu.
`JUDULPENELITIAN_YGLAMA` diisi dengan judul lama hanya bila cadangan masih kosong (revisi kedua tidak
menimpa cadangan asli). Kolom `JUDULPENELITIAN_YGLAMA` sudah ada di DB produksi; ditambahkan ke skema uji
(`database/migrations/testing/2026_09_30_000002_add_review_legacy_columns.php`). Uji:
`tests/Feature/Reviewer/PenilaianBorangTest.php::test_reviewer_revises_proposal_title_and_old_title_is_backed_up`.

---

## Urutan pengerjaan yang disarankan

1. ~~**Monev abdimas**~~ — selesai 2026-10-01.
2. **Notifikasi email** — berdampak langsung ke pengguna, tabel tidak berubah.
3. ~~**Halaman verifikasi QR**~~ — selesai 2026-10-07.
4. **Rekap monev** — butuh keputusan soal format (grid dulu atau langsung Excel).
5. **Reviewer pembanding** — kecil, tetapi perlu kejelasan aturan bisnis karena legacy tanpa validasi.
6. ~~**Simpan DRAFT penilaian reviewer**~~ — selesai 2026-10-01.
7. ~~**Revisi judul oleh reviewer**~~ — selesai 2026-10-01.
8. **Insentif jurnal** — paling besar, sebaiknya jadi modul tersendiri.
9. **Borang poster/presentasi** — tunda sampai ada konfirmasi bahwa proses ini benar-benar dipakai.
