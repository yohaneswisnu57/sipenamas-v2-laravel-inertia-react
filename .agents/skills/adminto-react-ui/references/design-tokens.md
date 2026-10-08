# Adminto Design Tokens & Styling Guide

Dokumen ini berisi spesifikasi teknis nilai token warna, tipografi, utility classes, dan CSS variables yang digunakan oleh tema Adminto.

---

## 1. Color Palette (Palet Warna Utama)

| Token Name | HEX Code | Penggunaan Utama |
| :--- | :--- | :--- |
| **Primary** | `#188ae2` | Tombol utama, active link, indikator progres, link highlight |
| **Secondary / Purple** | `#5b69bc` | Aksen sekunder, chart segment, badge sekunder |
| **Success** | `#10c469` | Status sukses, metrik positif / kenaikan profit, badge approved |
| **Info** | `#35b8e0` | Status informasi, tooltip highlight, badge info |
| **Warning** | `#f9c851` | Peringatan, status pending, rating star |
| **Danger** | `#ff5b5b` | Kesalahan/error, status dibatalkan/ditolak, metrik penurunan |
| **Pink** | `#ff8acc` | Aksen grafis khusus, kategori spesifik |
| **Teal** | `#02bc9c` | Aksen grafis khusus, metrik alternatif |
| **Dark** | `#313a46` | Header teks, dark mode background elements |
| **Light** | `#eef2f7` | Background elemen subtle, border ringan |

---

## 2. Grayscale & Backgrounds

* **White**: `#ffffff` (Latar Card & Konten di Light Mode)
* **Body Bg (Light)**: `#f6f7fb`
* **Border & Dividers**: `#eef2f7` (Solid) / `#e7e9eb` (Dashed)
* **Text Muted**: `#8a969c`
* **Text Body**: `#6c757d`
* **Text Heading**: `#313a46`

---

## 3. Utility Classes Khas Adminto

### Subtle Badges & Soft Buttons
```html
<!-- Badges -->
<span class="badge bg-primary-subtle text-primary">Active</span>
<span class="badge bg-success-subtle text-success">Completed</span>
<span class="badge bg-danger-subtle text-danger">Pending</span>
<span class="badge bg-warning-subtle text-warning">In Progress</span>
<span class="badge bg-info-subtle text-info">New</span>
<span class="badge bg-dark-subtle text-dark">Archived</span>

<!-- Rounded Badges -->
<span class="badge rounded-pill bg-success-subtle text-success">Verified</span>

<!-- Soft Buttons -->
<button class="btn btn-soft-primary">View Detail</button>
<button class="btn btn-soft-success">Approve</button>
<button class="btn btn-soft-danger">Delete</button>
```

### Dashed Borders & Dividers
```html
<!-- Dashed Card Header / Divider -->
<div class="border-bottom border-dashed"></div>
<div class="border-top border-dashed"></div>
<div class="border-start border-end border-dashed"></div>
```

---

## 4. Typography Scale

* **Font Family**: `"Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`
* **Page Title**: `<h4 className="page-title fs-18 fw-semibold">{title}</h4>`
* **Header Title**: `<h4 className="header-title fs-15 text-uppercase fw-semibold">{title}</h4>`
* **Sub-title / Description**: `<p className="text-muted fs-13 mb-3">{desc}</p>`
* **Card Widget Number**: `<h3>` atau `<h4>` dengan `fw-semibold text-dark`

---

## 5. Shadow & Card Radius

* **Card Radius**: `border-radius: 0.25rem` (Bootstrap default `$border-radius: 0.25rem`)
* **Card Shadow**: `box-shadow: 0 0 35px 0 rgba(154, 161, 171, 0.15)`
* **Modal Radius**: `0.375rem`
