---
name: adminto-react-ui
description: Always use when creating, modifying, or reviewing React UI components, pages, forms, tables, and widgets to strictly match the Adminto React Admin Dashboard design system and conventions.
---

# Adminto React UI Design System Skill

Skill ini adalah panduan standar dan komprehensif bagi AI untuk menghasilkan komponen dan halaman **React (JSX/TSX)** yang 100% konsisten dengan desain sistem **Adminto Admin Dashboard**.

Skill ini bersifat **mandiri (self-contained)** dan tidak memerlukan folder `reference design` eksternal.

> **Catatan SIPENAMAS V2:** frontend memakai Tailwind 4 + `lucide-react`, bukan `react-bootstrap`, `@iconify/react`, atau `react-hook-form`. Dari skill ini hanya token warna yang berlaku. Acuan utama: bagian "Desain Sistem" di `sipenamas_v2_frontend/CLAUDE.md` dan komponen di `sipenamas_v2_frontend/src/components/`.

---

## 1. Kapan Skill Ini Digunakan
- Membuat atau memodifikasi komponen antarmuka React (Cards, Tables, Modals, Forms, Stats Widgets, Charts, Badges, Breadcrumbs).
- Membangun halaman dashboard, halaman autentikasi, manajemen data (CRUD), atau profil pengguna dengan tema Adminto.
- Mengatur tata letak (Vertical/Horizontal layout, Topbar, LeftSidebar, Footer).
- Melakukan audit konsistensi visual pada komponen React yang ada.

---

## 2. Invarian & Prinsip Utama Desain Adminto

1. **Struktur Card & Header**:
   - Selalu gunakan `Card` dari `react-bootstrap`.
   - Header card wajib memiliki kelas `border-0 border-bottom border-dashed` dengan judul `<h4 className="header-title">{title}</h4>`.
   - Aksi opsional di pojok kanan atas header menggunakan Dropdown `card-drop` dengan icon `ri:more-2-fill`.
2. **Divider & Border Dashed**:
   - Pemisah antar seksi atau metrik metrik widget selalu menggunakan garis putus-putus presisi: `border-dashed` (misal: `border-top border-dashed`, `border-bottom border-dashed`).
3. **Warna & Palet Subtle**:
   - Gunakan palet utama Adminto: Primary (`#188ae2`), Secondary/Purple (`#5b69bc`), Success (`#10c469`), Info (`#35b8e0`), Warning (`#f9c851`), Danger (`#ff5b5b`), Dark (`#313a46`).
   - Gunakan badge dan tombol berlatar lembut: `bg-primary-subtle text-primary`, `bg-success-subtle text-success`, `btn-soft-primary`, `btn-soft-danger`.
4. **Tipografi & Ikon**:
   - Font family: `"Outfit", sans-serif`.
   - Page title: `fs-18 fw-semibold`, Header title: `fs-15 text-uppercase fw-semibold`, Muted text: `text-muted fs-13`.
   - Ikon: Gunakan `@iconify/react` (format Remix Icon `ri:*` atau Tabler `tabler:*`).
5. **Form & Data Table**:
   - Integrasikan `react-hook-form` untuk validasi form.
   - Gunakan struktur form terdedikasi (`TextFormInput`, `SelectFormInput`, `DropzoneFormInput`, `CustomFlatpickr`).
   - Tabel dilengkapi pencarian, pagination ringkas, dan baris hoverable.

---

## 3. Sub-Dokumentasi & Referensi Kode Lengkap

Untuk rincian kode dan blueprint siap pakai, rujuk berkas-berkas referensi internal berikut:

- [references/design-tokens.md](references/design-tokens.md) — Kamus warna, CSS variables, utility classes, tipografi, dan shadow.
- [references/component-blueprints.md](references/component-blueprints.md) — Blueprint komponen React siap pakai (Cards, Statistic Widgets, Data Tables, Modals, Breadcrumbs).
- [references/forms-and-inputs.md](references/forms-and-inputs.md) — Blueprint input form, select, datepicker, file upload dropzone dengan React Hook Form.
- [references/layouts-and-navigation.md](references/layouts-and-navigation.md) — Blueprint tata letak halaman (VerticalLayout, TopBar, LeftSidebar, Footer).
- [references/charts-and-visualizations.md](references/charts-and-visualizations.md) — Blueprint ApexCharts (Radial Progress, Area/Revenue Chart, Donut Chart).
- [references/extended-ui-and-auth.md](references/extended-ui-and-auth.md) — Blueprint UI lanjutan (Tabs, Ribbons, Avatar Groups, Auth Pages).

---

## 4. Alur Kerja Implementasi Komponen Baru

1. **Identifikasi Kebutuhan**: Tentukan apakah komponen berupa Widget/Card, Form Input, Data Table, Chart, atau Layout Halaman.
2. **Gunakan Blueprint Adminto**: Terapkan struktur JSX/TSX sesuai dengan template di folder `references/`.
3. **Terapkan Token Styling**: Pastikan kelas bootstrap dan token warna sesuai palet Adminto (hindari warna arbitrer).
4. **Verifikasi Kebersihan Kode**: Pastikan types (jika TSX) didefinisikan dengan jelas dan komponen siap pakai.
