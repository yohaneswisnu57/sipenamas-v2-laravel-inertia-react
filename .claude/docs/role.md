# Panduan Role & Role-Based Access Control (RBAC) SIPENAMAS

Dokumen ini membedah secara mendalam seluruh peran pengguna (*roles*), arsitektur kendali akses (*RBAC*), mekanisme pembatasan lingkup data (*data scoping*), dan kontrol otorisasi granular di setiap modul pada sistem **SIPENAMAS** (Universitas Katolik Widya Mandala Surabaya).

---

## 1. Arsitektur Multi-Tier RBAC SIPENAMAS

Sistem keamanan dan otorisasi SIPENAMAS dibangun di atas **5 Tingkatan Kendali Akses (5-Tier Access Control)**:

```
┌─────────────────────────────────────────────────────────────┐
│ Tier 1: System Level (Superuser & Mode Dewa)                │
│         -> Bypass semua aturan & akses seluruh data         │
├─────────────────────────────────────────────────────────────┤
│ Tier 2: Module Level Access (Routing Sesi)                  │
│         -> GROUPAKSES_ADM, _PEN, _REV, _DKN, _RKT, _AKR     │
├─────────────────────────────────────────────────────────────┤
│ Tier 3: Institutional Data Scoping (Hirarki Organisasi)     │
│         -> Univ (Rektor) > Fak (Dekan) > Prodi > Dosen      │
├─────────────────────────────────────────────────────────────┤
│ Tier 4: Fine-Grained UI Component Access                    │
│         -> z_modulobyek + groupaksesdetail (Menu, Tab, Btn) │
├─────────────────────────────────────────────────────────────┤
│ Tier 5: Proposal Ownership Level (Kepemilikan Berkas)       │
│         -> Ketua Pengusul vs Anggota Tim vs Mahasiswa       │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Katalog Peran (Roles) & Flag Penanda di Basis Data

Otorisasi pengguna disimpan di tabel **`person`** dengan kombinasi nilai *boolean flag* (0/1) dan string kode grup:

| No | Nama Role | Kode Modul | Flag / Kolom Penanda di Database | Deskripsi Wewenang Utama |
| :---: | :--- | :---: | :--- | :--- |
| **1** | **Super Administrator** | `adm` | `ISSUPERUSER = 1` | Memiliki kontrol penuh atas seluruh sistem, manajemen pengguna, reset password, dan bypass semua validasi. |
| **2** | **Administrator LPPM** | `adm` | `GROUPAKSES_ADM != ''` | Mengelola periode, gelombang, skema riset, plotting reviewer, rekap penilaian, final approval LPPM, penerbitan SK/Surat Tugas, pencairan dana, dan ekspor SINTA. |
| **3** | **Operator Riset / Abdimas**| `adm` | `ISOPERATORPENELITIAN = 1`<br>`ISOPERATORABDIMAS = 1` | Staf teknis LPPM yang memproses verifikasi kelengkapan berkas fisik, pencetakan sertifikat, dan administrasi harian usulan. |
| **4** | **Dekan Fakultas** | `dkn` | `ISDEKAN = 1`<br>`GROUPAKSES_DKN != ''` | Memvalidasi, menyetujui, atau menolak usulan dosen di fakultasnya; mengontrol pagu dana fakultas (`anggaranfakultas`); menandatangani lembar pengesahan secara digital. |
| **5** | **Reviewer (Internal/Eksternal)**| `rev`| `ISREVIEWERPENELITIAN = 1`<br>`ISREVIEWERABDIMAS = 1`<br>`GROUPAKSES_REV != ''` | Memberikan konfirmasi kesediaan review, menilai proposal substantif via rubrik skor, memberikan catatan revisi, dan mengevaluasi monev lapangan. |
| **6** | **Dosen Peneliti / Pelaksana**| `pen` | `ISPENELITI = 1`<br>`GROUPAKSES_PEN != ''` | Mengajukan proposal penelitian & abdimas, memilih tim dosen/mahasiswa, mengunggah laporan kemajuan/akhir, serta mengajukan subsidi APC dan insentif jurnal. |
| **7** | **Pimpinan Universitas (Rektorat)**| `rkt`| `ISREKTOR = 1`<br>`GROUPAKSES_RKT != ''` | Memantau dasbor eksekutif (grafik sebaran riset, keterlibatan mahasiswa/tendik), menyetujui riset strategis universitas, dan memantau integrasi MBKM. |
| **8** | **Tim Akreditasi & Borang**| `akr` | `GROUPAKSES_AKR != ''` | Penarikan dan ekstraksi portofolio riset/abdimas dosen dan luaran HKI/jurnal untuk penyusunan instrumen akreditasi (LKPS/LED prodi). |
| **9** | **Staf Keuangan / BAU** | `adm`/API | `ISADMBAU = 1` | Verifikasi rekening bank, kuitansi SPJ, dan approval administrasi pembayaran subsidi APC serta insentif publikasi. |
| **10**| **Gugus Jaminan Mutu (GJM)** | `adm`/`dkn` | `ISGJM = 1`<br>`___GROUPAKSES_GJM != ''`| Pengawasan mutu internal terhadap kepatuhan proses seleksi, review, dan pelaporan luaran sesuai Renstra LPPM. |

---

## 3. Pembedahan RBAC di Setiap Modul Proyek

### 3.1. Modul Gerbang Masuk & Sesi (`login/`)

* **File Kunci**: `appz/ONAIR/login/myphp/dologin.php`
* **Mekanisme Kerja**:
  1. Input `username` dan `paswet` diterima dari form login.
  2. Pengecekan tipe pengguna:
     * **User Eksternal (`person.ISEXTERNAL = 1`)**: Password dicocokkan langsung ke database lokal (`person.PASWET`) menggunakan fungsi dekripsi `encode3t()`.
     * **Civitas UKWMS (`ISEXTERNAL = 0`)**: Autentikasi dikirim via cURL ke API Pegawai UKWMS (`https://api.ukwms.ac.id:8100/v1/auth/login`).
  3. Setelah kredensial valid, dilakukan iterasi terhadap tabel `z_modulmodul`. Jika user memiliki `GROUPAKSES_<KODE>` atau berstatus `ISSUPERUSER = 1`, sistem membuat variabel sesi:
     ```php
     $_SESSION['LPPM_LOGINCENTER_ISADM'] = 1;
     $_SESSION['LPPM_LOGINCENTER_ISPEN'] = 1;
     $_SESSION['LPPM_LOGINCENTER_ISREV'] = 1;
     $_SESSION['LPPM_LOGINCENTER_ISDKN'] = 1;
     $_SESSION['LPPM_LOGINCENTER_ISRKT'] = 1;
     $_SESSION['LPPM_LOGINCENTER_ISAKR'] = 1;
     ```
  4. Pengguna diarahkan ke modul aktif pertama (`LPPM_HALAMANAKTIF`).

---

### 3.2. Modul Administrator LPPM (`adm/`)

* **Penjaga Akses**: `adm/myphp/ceksession.php` (memastikan `$_SESSION['LPPM_ADMIN_MYUSERNAME']` aktif).
* **Lingkup Data**: Akses **Global Universitas** (seluruh fakultas, prodi, dan dosen).
* **Fitur & RBAC Internal**:
  * **Manajemen Pengguna & Otorisasi (`sethakakses.php`)**:
    * Mengaktifkan/menonaktifkan peran: `ISPENELITI`, `ISDEKAN`, `ISREKTOR`, `ISREVIEWERPENELITIAN`, `ISREVIEWERABDIMAS`.
    * Memetakan kelompok hak akses ke kolom `GROUPAKSES_ADM`, `GROUPAKSES_PEN`, dll.
    * Fitur darurat: `DORESETPWD` dan `DORESETPIMPINAN`.
  * **Konfigurasi Grup Akses Granular (`groupakses.php` & `groupaksesdetail.php`)**:
    * Mengontrol visibilitas setiap tombol, tab, dan menu di UI ExtJS untuk kelompok tertentu.
  * **Tata Kelola Seleksi & Pendanaan**:
    * Menugaskan Reviewer 1, 2, dan Reviewer Pembanding (`setreviewer.php`).
    * Menentukan status kelulusan akhir (`finalapproval.php`): status `'LOLOS'` / `'TIDAK LOLOS'` dan pagu dana definitif.
    * Memvalidasi pencairan termin 1 (70%) dan termin 2 (30%) beserta kelengkapan SPJ (`penggunaananggaranpenelitian.php`).
  * **Ekspor Nasional & Notifikasi**:
    * Men-generate laporan sinkronisasi format SINTA (`finalapproval_sinta_xls.php`).
    * Mengirim broadcast pengumuman via WhatsApp Gateway (`waqrcode.php`, `watest.php`).

---

### 3.3. Modul Dosen Peneliti (`pen/`)

* **Penjaga Akses**: `pen/myphp/ceksession.php` (memastikan `$_SESSION['LPPM_PENELITI_MYUSERNAME']` aktif).
* **Lingkup Data**: **Data Pribadi & Usulan Tim Sendiri**.
* **Aturan Pembatasan Data (Data Scoping)**:
  * Pada query `permohonanpenelitian.php`:
    ```sql
    WHERE (A.PERMOHONANDIBUAT_KDPERSON = '$kdoprtr')
       OR (A.id IN (SELECT IDPARENT FROM penelitian_tim WHERE NIKNIDN = '$kdoprtr'))
    ```
    *Dosen hanya dapat melihat proposal yang mereka buat sendiri (Ketua) atau di mana mereka diundang sebagai Anggota.*
* **Hierarki Peran dalam Tim Proposal**:
  * **Ketua Peneliti (`PERAN = 'KETUA'`)**:
    * Hak penuh menambah/menghapus anggota dosen (`permohonanpenelitiantim.php`).
    * Hak mendaftarkan mahasiswa pembantu riset (`cmbmahasiswa.php`).
    * Hak mengunggah draf proposal, dokumen revisi, laporan kemajuan, dan laporan akhir.
    * Hak mencetak lembar pengesahan ber-QR code.
  * **Anggota Tim (`PERAN = 'ANGGOTA'`)**:
    * Hanya memiliki hak konfirmasi kesediaan (`statuskesediaan.php`).
    * Akses baca (*read-only*) terhadap draf proposal dan hasil review.
    * Tidak dapat mengubah anggaran atau menghapus proposal.
  * **Mahasiswa (`penelitian_mhs`)**:
    * Tidak memiliki akun login ke modul `pen/`. Data mahasiswa dikelola langsung oleh Ketua Peneliti untuk pencatatan poin portofolio dan rekognisi MBKM.

---

### 3.4. Modul Dekan Fakultas (`dkn/`)

* **Penjaga Akses**: `dkn/myphp/ceksession.php` (memastikan `$_SESSION['LPPM_DEKAN_MYUSERNAME']` aktif).
* **Lingkup Data**: **Tingkat Fakultas Tertentu (Faculty Scoped)**.
* **Mekanisme Pembatasan Data**:
  * Pada `approvalpermohonan.php`:
    ```sql
    LEFT JOIN prodi B ON (B.KODEPRODI = A.KDPRODI)
    LEFT JOIN fakultas D ON (D.KODEFAKULTAS = B.KDFAKULTAS)
    WHERE (D.KDDEKAN = '$kdoprtr')
    ```
    *Sistem mendeteksi NIK Dekan yang login dan membatasi data hanya untuk proposal yang berasal dari program studi di bawah fakultas tersebut.*
* **Wewenang & Tindakan**:
  * **Approval Proposal (`approvalpermohonan.php`)**: Menyetujui (`APPROVALPERMOHONAN_ISAPPROVEBYDEKAN = 1`) atau menolak usulan.
  * **Pengawasan Plafon Anggaran (`anggaranfakultas.php`, `anggaranprodi.php`)**: Memastikan total biaya yang diajukan tidak melampaui kuota anggaran penelitian fakultas.
  * **Approval Laporan (`approvallaporan.php`)**: Memverifikasi laporan capaian sebelum dosen diizinkan mencairkan dana tahap berikutnya.
  * **Pemberian Sanksi & Monitoring (`penelitianbelumtuntas.php`)**: Memantau daftar dosen fakultas yang belum menyerahkan luaran wajib.

---

### 3.5. Modul Reviewer (`rev/`)

* **Penjaga Akses**: `rev/myphp/ceksession.php` (memastikan `$_SESSION['LPPM_REVIEWER_MYUSERNAME']` aktif).
* **Lingkup Data**: **Proposal yang Ditugaskan Saja (Assigned Proposals Only)**.
* **Mekanisme Pembatasan Data**:
  * Pada `penilaianproposal.php`:
    ```sql
    FROM penelitian_reviewer A
    LEFT JOIN penelitian B ON (B.id = A.IDPARENT)
    WHERE (A.NIK = '$kdoprtr')
    ```
    *Reviewer sama sekali tidak dapat melihat usulan dosen lain kecuali yang telah di-assign oleh Admin LPPM pada tabel `penelitian_reviewer`.*
* **Prinsip Independensi Review**:
  * **Blind / Confidential Review**: Reviewer menilai berdasarkan nomor identitas proposal dan substansi tanpa intervensi langsung dari pengusul.
  * **Reviewer 1 vs Reviewer 2**: Penilaian dilakukan secara independen. Jika selisih nilai antar-reviewer melebihi ambang batas, Admin LPPM mengaktifkan `ISREVIEWERPEMBANDING = 1` untuk reviewer ke-3.
* **Wewenang & Tindakan**:
  * Konfirmasi kesediaan menilai (`statuskesediaan.php`).
  * Input skor per butir rubrik kriteria (`penilaianproposaldetail.php`).
  * Input rekomendasi kelayakan dana (`REKOMENDASIBIAYA`) dan komentar perbaikan.
  * Memeriksa dokumen perbaikan pada form revisi (`penilaianproposalhasilrevisi.php`).
  * Evaluasi kemajuan lapangan pada tahap Monev (`kuesionerdetail_a.php`, `kuesionerdetail_b.php`).

---

### 3.6. Modul Pimpinan Universitas / Rektorat (`rkt/`)

* **Penjaga Akses**: `rkt/myphp/ceksession.php` (memastikan `$_SESSION['LPPM_REKTOR_MYUSERNAME']` aktif).
* **Lingkup Data**: **Agregat Tingkat Universitas (University-Wide)**.
* **Wewenang & Tindakan**:
  * **Dashboard Eksekutif**: Akses grafik visualisasi tren riset dosen (`dosen_barchart.php`), grafik keterlibatan mahasiswa (`mahasiswa_barchart.php`), dan tenaga kependidikan (`tendik_barchart.php`).
  * **Approval Riset Strategis (`approvalpermohonan.php`)**: Memberikan persetujuan akhir pada skema penelitian unggulan universitas dengan pendanaan khusus.
  * **Evaluasi MBKM Riset (`mbkm.php`, `mbkmbyprodi.php`)**: Memantau partisipasi mahasiswa dalam riset dosen untuk pemenuhan Indikator Kinerja Utama (IKU 2).

---

### 3.7. Modul Tim Akreditasi & SPMI (`akr/`)

* **Penjaga Akses**: `akr/myphp/ceksession.php`.
* **Lingkup Data**: **Data Mining & Pelaporan Seluruh Prodi (Read-Only / Mining)**.
* **Wewenang & Tindakan**:
  * Tidak memiliki hak manipulasi data (tidak dapat menambah, mengubah proposal, atau menyetujui anggaran).
  * Menarik data portofolio riset & pengabdian terfilter berdasarkan Program Studi dan Tahun Akademik (`daftarpenelitian.php`, `daftarabdimas.php`).
  * Mengekstraksi data luaran publikasi jurnal terakreditasi, perolehan HKI, paten, dan buku ajar.
  * Ekspor tabel Excel terformat untuk lampiran borang LKPS (Laporan Kinerja Program Studi) dan LED (Laporan Evaluasi Diri).

---

## 4. Sistem Hak Akses Granular Komponen UI (`z_modulobyek`)

Selain pembatasan berbasis role di backend, SIPENAMAS menerapkan proteksi antarmuka (UI Level RBAC) menggunakan tabel `z_modulobyek`, `groupakses`, dan `groupaksesdetail`:

1. **Pendaftaran Komponen Antarmuka (`z_modulobyek`)**:
   Setiap menu bar, tab, grid, dan tombol aksi didaftarkan dalam sistem:
   * `MODULNYA`: Kode modul target (`ADM`, `PEN`, `REV`, `DKN`, `RKT`, `AKR`).
   * `OBYEKNYA`: ID unik komponen ExtJS (misal: `btn_tambah_proposal`, `tab_anggaran`, `btn_approve_dekan`).
   * `TXTNYA`: Label antarmuka.
   * `TIPENYA`: Tipe kontrol (`MENU`, `BUTTON`, `TABPANEL`, `GRID`).
2. **Konfigurasi Grup Akses (`groupaksesdetail`)**:
   Admin LPPM menentukan status izin per komponen:
   * `STATUSNYA = 1`: Komponen aktif dan dapat diklik oleh pengguna.
   * `STATUSNYA = 0`: Komponen disembunyikan (*hidden*) atau dinonaktifkan (*disabled*).
3. **Penerapan pada Pengguna**:
   Kolom `GROUPAKSES_<MODUL>` di tabel `person` mengaitkan profil dosen ke grup izin tertentu. Saat aplikasi ExtJS memuat (`app.js`), antarmuka hanya akan merender komponen yang berstatus aktif untuk grup tersebut.

---

## 5. Matriks Lengkap Hak Akses Fitur (Feature Access Matrix)

| Modul / Fitur Fungsional | Super Admin | Admin LPPM | Dekan Fak. | Reviewer | Dosen Peneliti | Rektorat | Tim Akred. |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Buka/Tutup Periode & Gelombang** | CRUD | CRUD | R | - | R | R | R |
| **Manajemen Hak Akses & Password** | CRUD | CRUD | - | - | - | - | - |
| **Input Proposal Baru (Riset/Abdimas)**| CRUD | R | - | - | **CRUD (Own)**| - | - |
| **Tambah Tim Dosen & Mahasiswa** | CRUD | U | - | - | **CRUD (Own)**| - | - |
| **Konfirmasi Kesediaan Anggota Tim** | - | - | - | - | **U (Assigned)**| - | - |
| **Approval Usulan Tingkat Fakultas** | CRUD | R | **U (Faculty)**| - | - | R | - |
| **Kontrol Pagu Anggaran Fakultas** | CRUD | R | **U (Faculty)**| - | - | R | - |
| **Plotting Reviewer 1, 2, Pembanding**| CRUD | **CRUD** | - | - | - | - | - |
| **Konfirmasi Kesediaan Reviewer** | - | - | - | **U (Assigned)**| - | - | - |
| **Input Skor Rubrik & Catatan Review**| - | R | - | **U (Assigned)**| - | - | - |
| **Upload Dokumen Revisi Proposal** | - | - | - | - | **U (Own)** | - | - |
| **Validasi Naskah Revisi Reviewer** | - | R | - | **U (Assigned)**| - | - | - |
| **Final Approval LPPM & Status SK** | CRUD | **CRUD** | R | - | R | **A (Strat.)**| - |
| **Validasi Pencairan Termin 1 & 2** | CRUD | **CRUD** | - | - | R | R | - |
| **Input Laporan Monev Kemajuan** | - | - | - | - | **U (Own)** | - | - |
| **Penilaian Kuesioner Monev** | - | R | R | **U (Assigned)**| - | - | - |
| **Upload Laporan Akhir & Luaran** | - | - | - | - | **U (Own)** | - | - |
| **Pengajuan Subsidi APC & Insentif** | CRUD | R | R | - | **CRUD (Own)**| - | - |
| **Approval Pencairan Subsidi/Insentif**| CRUD | **CRUD** | - | - | - | - | - |
| **Pendaftaran HKI & Paten** | CRUD | **CRUD** | - | - | **CRUD (Own)**| - | - |
| **Ekspor Data Format SINTA Kemdikbud**| R / Exp | **R / Exp** | - | - | - | - | - |
| **Dasbor Eksekutif & Chart Visual** | R | R | R | - | - | **R / View** | - |
| **Ekstraksi Data Borang LKPS / LED** | R / Exp | R / Exp | R / Exp | - | - | R / Exp | **R / Exp** |

*Keterangan Operasi: **CRUD** = Create, Read, Update, Delete; **U** = Update / Memberikan Status; **R** = Read Only; **Exp** = Export Data; **Own** = Hanya Usulan Sendiri; **Faculty** = Hanya Dosen Fakultas Sendiri; **Assigned** = Hanya Usulan yang Ditugaskan.*
