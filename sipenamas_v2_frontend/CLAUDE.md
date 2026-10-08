# Frontend SIPENAMAS V2

Stack: React 18 (JavaScript, bukan TypeScript), Vite 6, Tailwind CSS 4, React Router 7, Zustand 5, lucide-react, Vitest + Testing Library.

## Konvensi

- Cek komponen/util yang sudah ada di `src/` sebelum membuat yang baru; ikuti struktur dan penamaan file di sekitarnya.
- Gabungkan class Tailwind dengan `twMerge(clsx(...))`; jangan menulis CSS terpisah bila utility cukup.
- Ikon hanya dari `lucide-react`. State global pakai Zustand; state lokal pakai `useState`.
- Jangan menambah dependency tanpa persetujuan.
- Jangan menebak endpoint atau bentuk response API; cek routes/resource di `../sipenamas_v2_backend` bila perlu.
- Jangan edit `dist/` (hasil build).

## Desain Sistem (acuan = kode yang ada)

Gaya visual: palet Adminto di atas Tailwind 4. Sumber kebenaran: token `@theme` di `src/index.css` dan komponen di `src/components/`.

- Pakai komponen yang ada sebelum menulis markup sendiri:
  - `components/ui/`: `Button` (variant `primary`, `secondary`, `soft-*`, `danger`, `success`, `ghost`, `link`), `Card`/`CardHeader`/`CardContent`/`CardFooter`, `Badge`, `Modal`, `Pagination`, `Switch`, `TableSkeleton`/`TableEmptyState`/`TableFilterBar`.
  - `components/common/`: `StatusBadge` (label status usulan), `StatCard`, `StepIndicator`.
- Warna: primary `#188ae2`, indigo `#5b69bc`, success `#10c469`, danger `#ff5b5b`, warning `#f9c851`, info `#35b8e0`, teks gelap `#313a46`, canvas `#f6f7fb`, border `#e7e9eb`. Netral pakai skala `slate-*`. Untuk kode baru, utamakan token `adm-*` (mis. `bg-adm-primary`, `border-adm-border`) daripada hex arbitrer.
- Card: `bg-white border border-[#e7e9eb] rounded-xl` + shadow halus. Jangan pakai shadow tebal (`shadow-xl`) kecuali dropdown/modal.
- Z-index: konten `z-10`/`z-20`, navbar `z-30`, overlay sidebar `z-35`, sidebar `z-40`, dropdown/modal `z-50`.
- Font: `Public Sans` (body), `Outfit` (heading), `JetBrains Mono` (angka/kode).
- Mobile: input/select/textarea baru minimal 16px di layar kecil (`text-base sm:text-sm`) agar iOS tidak auto-zoom; pakai `min-w-0` pada flex parent bila anak memakai `truncate`.
- Tidak berlaku untuk proyek ini: `.agents/rules/spesifikasi_desain_sistem_filament_v4_lengkap_definitif.md` (Filament/zinc/amber) dan blueprint `react-bootstrap`/`@iconify` di skill `adminto-react-ui`. Dari skill itu, hanya token warna yang relevan.

## Test

- Jalankan test spesifik: `npx vitest run tests/<file>`. Sebelum push: `npx vitest run tests/features` lalu `npx vite build`.
- Tidak ada ESLint/script `lint`; verifikasi = test + build.
- Perubahan logika/perilaku perlu test; perubahan tampilan/style saja tidak.
- Gunakan Testing Library (query berdasarkan role/teks), bukan detail implementasi.
