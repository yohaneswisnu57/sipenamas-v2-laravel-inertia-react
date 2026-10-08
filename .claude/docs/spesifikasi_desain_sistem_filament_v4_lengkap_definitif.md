> **TIDAK BERLAKU untuk SIPENAMAS V2 (diarsipkan 2026-10-05).** Frontend memakai React + Tailwind 4 dengan palet Adminto, bukan Filament/zinc/amber. Acuan desain: bagian "Desain Sistem" di `sipenamas_v2_frontend/CLAUDE.md`.

# RINGKASAN DESAIN SISTEM: FILAMENT v4 (SIPENAMAS EDITION)

Dokumen ini merupakan acuan tunggal (*single source of truth*) antarmuka dan gaya visual untuk proyek SIPENAMAS. AI Agent wajib merujuk dokumen ini setiap kali menangani tugas UI/Frontend. Penyimpangan dari kelas-kelas di sini dianggap sebagai **bug sintaksis**.

---

## 1. Aturan Mutlak Desain & Mobile-First (Anti-Slop Invariants)

| Elemen Kritis | Standar Resmi Filament v4 | Larangan Keras (UI Slop) |
| :--- | :--- | :--- |
| **Dark Canvas** | Canvas: `bg-zinc-950`, Panel/Card: `bg-zinc-900` | Dilarang menggunakan hitam pekat `bg-black`. |
| **Primary Accent** | Palet Amber (`amber-500` / `amber-600`) | Hindari warna Blue/Indigo bawaan Tailwind. |
| **Borders vs Shadow** | Presisi 1px: `border-zinc-200` (light) / `border-zinc-800` (dark) + `shadow-xs` | Dilarang drop-shadow tebal (`shadow-lg/xl`). |
| **Z-Index Scale** | Dropdown (`z-30`), Sidebar (`z-40`), Modal (`z-50`), CmdK (`z-60`), Toast (`z-[70]`) | Dilarang Z-Index acak/sama yang menumpuk. |
| **Mobile iOS Bug** | WAJIB `text-sm` (min 16px) pada `<input>` & `<select>` | Dilarang `text-xs` pada input form (memicu auto-zoom). |
| **Tap Highlight** | Induk terluar wajib: `[-webkit-tap-highlight-color:transparent]` | Hindari kilat biru jelek saat tombol ditekan di HP. |
| **Flex Truncation** | Wajib `min-w-0` pada pembungkus Flex jika elemen anak menggunakan `truncate`. | Teks panjang tidak boleh menembus batas kontainer flex. |
| **Safe Area Inset** | Wajib `pb-safe` pada modal/toast/footer yang menempel di bawah layar. | Tidak boleh tertutup garis home/poni HP (Notch). |

---

## 2. Kamus Kelas Fondasi (Exact Tailwind Classes)

### 2.1 Surface & Kontainer
- **Canvas Dasar:** `bg-zinc-100 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100`
- **Panel / Card:** `bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs`
- **Toolbar / Topbar:** `h-16 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 sm:px-6`
- **Divider / Separator:** `border-t border-zinc-200 dark:border-zinc-800`

### 2.2 Tombol & Interaksi
- **Primary:** `bg-amber-500 hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-500 text-white font-semibold rounded-lg px-3.5 py-2 text-sm sm:text-xs shadow-xs focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition disabled:opacity-70 disabled:cursor-not-allowed`
- **Secondary:** `border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 font-medium rounded-lg px-3.5 py-2 text-sm sm:text-xs shadow-xs transition disabled:opacity-70`
- **Destructive:** `bg-rose-600 hover:bg-rose-500 text-white font-semibold rounded-lg px-3.5 py-2 text-sm sm:text-xs shadow-xs focus:outline-none focus:ring-2 focus:ring-rose-500/20 transition`
- **Micro Action/Icon:** `rounded-md p-2 sm:p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:hover:bg-zinc-800 dark:hover:text-white transition` (P-2 di mobile untuk jempol).
- **Text Link:** `text-amber-600 hover:underline dark:text-amber-400 cursor-pointer transition`

### 2.3 Form & Fields Dasar
- **Input / Select Standar:** `block w-full rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-950 dark:text-white shadow-xs focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:placeholder-zinc-500 disabled:opacity-50`
- **Input Error State:** `block w-full rounded-lg border border-rose-300 dark:border-rose-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-950 dark:text-white shadow-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20`
- **Input With Icon Wrapper:** `relative flex items-center` (Letakkan SVG absolut di `left-3`, beri padding `pl-9` pada input).
- **Label Input:** `block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5`
- **Helper & Validation Text:** `text-[11px] mt-1 text-zinc-500 dark:text-zinc-400` (Ganti text-rose-600 untuk error).
- **Toggle Switch iOS:** `relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-amber-500/20 bg-zinc-200 dark:bg-zinc-700` (Pakai `bg-amber-500` saat On). Lingkaran dalam: `pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-xs ring-0 transition duration-200 ease-in-out`

### 2.4 Status Badges & Avatars
- **Success / Active:** `inline-flex items-center gap-1.5 rounded-md bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/20 dark:ring-emerald-500/30 px-2 py-0.5 text-xs font-medium`
- **Warning / Pending:** `inline-flex items-center gap-1.5 rounded-md bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-1 ring-inset ring-amber-600/20 dark:ring-amber-500/30 px-2 py-0.5 text-xs font-medium`
- **Danger / Inactive:** `inline-flex items-center gap-1.5 rounded-md bg-rose-500/10 text-rose-700 dark:text-rose-400 ring-1 ring-inset ring-rose-600/20 dark:ring-rose-500/30 px-2 py-0.5 text-xs font-medium`
- **Avatar Tunggal:** `flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300`
- **Avatar Group Container:** `flex -space-x-2 overflow-hidden` (Anak wajib memiliki `ring-2 ring-white dark:ring-zinc-900`).

---

## 3. Komponen Struktur & Layout (App Shell)

### 3.1 Mobile Off-Canvas Sidebar
- **Mobile Backdrop:** `fixed inset-0 z-40 bg-zinc-950/60 backdrop-blur-xs lg:hidden animate-in fade-in duration-200`
- **Sidebar Container:** `fixed inset-y-0 left-0 z-40 w-64 transform flex-col border-r border-zinc-200 bg-white transition-transform duration-300 lg:relative lg:translate-x-0 dark:border-zinc-800 dark:bg-zinc-900`
- **Breadcrumbs:** `flex items-center gap-2 text-xs font-medium text-zinc-500 dark:text-zinc-400` (Pemisah gunakan ikon ChevronRight h-3.5 w-3.5).

### 3.2 Form Section & Tabs
- **Bar Tab Kontainer:** `flex overflow-x-auto whitespace-nowrap border-b border-zinc-200 dark:border-zinc-800 px-4 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden`
- **Tab Item Aktif:** `border-b-2 border-amber-500 py-3 px-3 text-xs font-semibold text-amber-600 dark:text-amber-400`
- **Section Collapsible Header:** Form/Menu dengan panah chevron yang memutar (`-rotate-90`) saat di-collapse.

---

## 4. Komponen Data Table Lengkap (Filament v4 Signature)

### 4.1 Tabel Responsif & Densitas
- **Table Wrapper:** `w-full max-w-full overflow-x-auto`
- **Sel Data (Anti-Wrap):** `px-4 whitespace-nowrap font-medium text-zinc-900 dark:text-white`
- **Dual-Density Engine (WAJIB):** Gunakan variable react `isCompact`. Sel padding = `isCompact ? 'py-2 text-xs' : 'py-3.5 text-sm'`.
- **Sortable Header & Row Hover:** Header kolom `hover:text-zinc-950`, baris body tabel wajib `hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40`.

### 4.2 Toolbar, Pencarian, & Filter
- **Toolbar Wrapper:** `p-4 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between` (Wajib ditumpuk di mobile).
- **Active Filter Bar / Bulk Actions:** Muncul di toolbar saat dicentang: `inline-flex items-center gap-2 rounded-lg bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-500/20 dark:text-amber-400`
- **Table Loading Overlay:** `absolute inset-0 z-10 flex items-center justify-center bg-white/50 backdrop-blur-sm dark:bg-zinc-900/50` dengan spinner SVG.
- **Empty State:** `flex flex-col items-center justify-center p-12 text-center` (Ikon abu-abu besar, judul `text-sm font-semibold`, teks `text-xs text-zinc-500`).

### 4.3 Dropdown, Aksi Baris, & Pagination
- **Dropdown/Popover (Z-30):** `absolute right-0 mt-2 w-48 rounded-xl border border-zinc-200 bg-white p-1 shadow-xl z-30 dark:border-zinc-800 dark:bg-zinc-900 animate-in fade-in zoom-in-95`
- **Pagination Footer:** `flex flex-col sm:flex-row gap-3 sm:items-center justify-between border-t border-zinc-200 px-4 py-3 text-xs text-zinc-500`

---

## 5. Form & Fields Kompleks (Filament Advanced Forms)

- **Dropzone Area (File Upload):** `flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/40 p-6 text-center transition hover:border-amber-500 hover:bg-amber-500/5 cursor-pointer`
- **Radio Cards:** `relative flex cursor-pointer rounded-xl border p-4 shadow-xs transition` (Aktif: `border-amber-500 bg-amber-500/5 ring-1 ring-inset ring-amber-500`).
- **Tags Input Pembungkus:** `flex flex-wrap items-center gap-1.5 rounded-lg border border-zinc-300 bg-white p-2 shadow-xs focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20`
- **Dynamic Repeater Item:** `flex items-center gap-2 rounded-lg border border-zinc-200 bg-white p-2.5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900`

---

## 6. Overlays, Dialogs & Transisi (Z-Index Scale)

- **Backdrop Overlay Global:** `fixed inset-0 bg-zinc-950/60 backdrop-blur-xs transition-opacity`
- **Modal Standar (Z-50):** `fixed inset-0 z-50 flex items-center justify-center p-4` (Dalamnya: `w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-2xl animate-in zoom-in-95`).
- **Modal Footer Layout:** `mt-6 flex items-center justify-end gap-2 border-t border-zinc-200 pt-4 pb-safe`
- **Slide-Over Sheet (Z-50):** `fixed inset-y-0 right-0 z-50 flex h-full w-full max-w-md flex-col border-l border-zinc-200 bg-white shadow-2xl animate-in slide-in-from-right`
- **Command Palette / ⌘K (Z-60):** `fixed inset-0 z-60 flex items-start justify-center p-4 pt-16 sm:pt-24`
- **Floating Toast (Z-[70]):** `fixed bottom-4 right-4 z-[70] flex items-center gap-3 rounded-xl p-4 shadow-xl animate-in slide-in-from-bottom-3 pb-safe`
- **Tooltip:** `absolute z-50 rounded bg-zinc-900 px-2 py-1 text-[10px] font-medium text-white shadow-sm`

---

## 7. Penyajian Data & Konten

- **Prose / Rich Text (WYSIWYG Markdown):** `prose prose-sm prose-zinc dark:prose-invert max-w-none text-xs` (Wajib membungkus teks artikel atau biografi).
- **Infolist Grid (dl/dt/dd):** `<dl>` gunakan `grid grid-cols-1 sm:grid-cols-2 gap-4`. `<dt>` gunakan `text-[11px] font-medium text-zinc-500`. `<dd>` gunakan `text-sm font-semibold text-zinc-900`.
- **KPI Widget Card:** `rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs relative overflow-hidden`
- **Miniature Sparklines:** Letakkan SVG chart transparan (`opacity-20`) di `absolute bottom-0 left-0 right-0 h-10` di dalam KPI Card.

---

## 8. Checklist Verifikasi Mandiri AI (Hard Enforcement)

Sebelum memfinalisasi kode UI/Frontend, AI Agent wajib mengaudit komponen secara internal dengan pertanyaan berikut:
- [ ] **Mobile Touch & Input:** Apakah seluruh input `<input>`/`<select>` bersaiz `text-sm` untuk mencegah *auto-zoom* iOS? Apakah form menumpuk ke bawah (flex-col) di mobile?
- [ ] **Anti-Slop Color:** Apakah warna dasar menggunakan `bg-zinc-950` / `bg-zinc-900` dan BUKAN `#000000`? Apakah bayangannya ringan (`shadow-xs`) dengan border 1px?
- [ ] **Focus & States:** Apakah semua tombol memiliki `focus:ring-2 focus:ring-amber-500/20` dan menonaktifkan klik ganda dengan `disabled:opacity-70`?
- [ ] **Z-Index Collision:** Apakah hirarki skala Z-index (`30` untuk dropdown, `50` untuk Modal) dipatuhi tanpa konflik?