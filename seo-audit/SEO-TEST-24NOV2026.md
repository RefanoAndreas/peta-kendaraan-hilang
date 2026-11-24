# HASIL TES SEO & PERFORMA — hilang.refanoandreas.com (24 Nov 2026, plugin v0.47.0)

## 1. Status Lighthouse / PageSpeed

| Jalur | Hasil |
|---|---|
| PageSpeed Insights API (tanpa key) | **HTTP 429** — kuota harian project Google habis |
| PageSpeed Insights web (pagespeed.web.dev) | *"Unable to resolve https://hilang.refanoandreas.com/"* — crawler PSI ditolak CDN Hostinger |
| Lighthouse CLI lokal (lighthouse 13.6.0, Chrome 140) | **403** pada dokumen utama — baik dengan UA default, UA desktop penuh, maupun `emulatedUserAgent` diubah |
| Chrome headless dengan `--dump-dom` (UA default & desktop) | **200 OK** → situsnya sehat; yang ditolak adalah pola request Lighthouse |

Kesimpulan: **skor Lighthouse resmi belum bisa diambil** lewat CDN apa adanya. Yang bisa diukur: TTFB, bobot halaman, waktu render, jumlah request, dan seluruh item audit SEO (di bawah). Kalau angka Lighthouse resmi dibutuhkan, sementara matikan CDN (`cdn: false` lewat API Hostinger) → jalankan Lighthouse → nyalakan lagi.

## 2. Metrik terukur

| Metrik | Beranda | Detail laporan |
|---|---|---|
| TTFB (curl, 3 percobaan) | 353 · 305 · 270 ms | 284 · 260 · 428 ms |
| DOM ready (Chrome, tanpa throttle) | 1.136 ms | — |
| Load event (tanpa throttle) | 1.155 ms | — |
| TTFB di bawah throttle Lighthouse-mobile (4× CPU, 1,6 Mbps, RTT 150 ms) | 988 ms | — |
| DOM ready (throttled) | 2.447 ms | — |
| Load event (throttled) | 2.910 ms | — |
| Request | 13–14 | 14 |
| Bobot HTML | 62,6 KB | 71,7 KB |
| Node DOM | ±4.600 | — |

Catatan: peta memuat tile setelah load, jadi angka di atas belum termasuk raster tile.

## 3. Audit SEO on-page

| Halaman | Title (char) | Desc (char) | H1 | Kata | Schema |
|---|---|---|---|---|---|
| Beranda | 55 | 139 | 1 | 1.257 | WebSite |
| /wilayah/ | 63 | **135** (dari 177) | 1 | 478 | WebSite + **CollectionPage + ItemList + Breadcrumb** |
| /kecamatan/sukolilo/ | **54** (dari 81) | 174 | 1 | 545 | WebSite + CollectionPage + Breadcrumb |
| Detail laporan | 54 | 138 | 1 | 605 | WebSite + WebPage + Vehicle + Breadcrumb |
| /panduan/ (5 halaman) | 52–64 | 130–146 | 1 | 474–776 | WebSite + Article + Breadcrumb + **FAQPage** |

Temuan yang diperbaiki pada sesi ini:
1. Judul halaman kecamatan 81 → **54** karakter (suffix nama situs tidak lagi ditambahkan untuk taksonomi kecamatan).
2. Deskripsi `/wilayah/` 177 → **135** karakter.
3. Judul beranda 34 → **55** karakter (ditambah kata kunci "Motor & Mobil Hilang Jatim").
4. `/wilayah/` tadinya hanya punya schema `WebSite` → ditambah `CollectionPage` + `ItemList` + `BreadcrumbList`.
5. Tidak ada gambar tanpa `alt` (situs belum memakai `<img>` sama sekali).

## 4. Sitemap

`wp-sitemap.xml`: halaman 14 · laporan 432 · wilayah 10 · kecamatan 31 = **487 URL**.

## 5. Yang masih terbuka

- Skor Lighthouse resmi (LCP/CLS/TBT) belum terukur — CDN menolak Lighthouse/PSI.
- Halaman panduan `motor-hilang-surabaya` (474 kata) dan `kawasan-rawan-curanmor-surabaya` (486 kata) masih di bawah target 800 kata.
- Perlu keputusan: apakah bot protection CDN perlu dilonggarkan untuk agen pengukuran (bukan untuk Googlebot — Googlebot sudah lolos TEST LIVE URL).

## 6. UPDATE: Lighthouse BERHASIL dijalankan (lewat origin, CDN di-bypass)

Cara: Chrome headless dijalankan dengan `--host-resolver-rules="MAP hilang.refanoandreas.com <IP-origin-server>"`
(origin LiteSpeed, `X-Powered-By: PHP/8.2.36`), lalu Lighthouse menempel lewat `--port=9333`.
**DNS/CDN tidak diubah sama sekali** — lebih aman daripada mematikan CDN.

| Halaman | Perf | A11y | Best-prac | SEO | LCP | TBT | CLS | SI |
|---|---|---|---|---|---|---|---|---|
| Beranda (mobile) | **49** | 100 | 100 | **100** | 5,1 s | 2.770 ms | 0 | 4,7 s |
| Detail laporan (mobile) | **74** | 96 → lihat di bawah | 100 | **100** | 1,3 s | 1.450 ms | 0 | 1,6 s |

Catatan: skor diukur pada origin (tanpa CDN), jadi di produksi bisa sedikit berbeda.

### Penyebab skor beranda rendah (dari Laporan Lighthouse)
- `mainthread-work-breakdown`: Other 4.053 ms · **Script Evaluation 2.757 ms** · Style & Layout 1.487 ms.
- `long-tasks`: 20 tugas panjang; terbesar dari **maplibre-gl.js (609 ms)**, inline (448/447 ms), ui.js (228/191 ms).
- `unused-javascript`: ~97 KiB maplibre belum terpakai saat awal.
- Script pihak ketiga yang ikut termuat: **`cdn-reach.hostinger.com/js/embed.js`** — tidak ada di kode plugin,
  disuntik oleh Hostinger. Kandidat dihapus kalau tidak dipakai.

### Yang sudah dicoba dan TIDAK menolong
- `.item { content-visibility: auto; contain-intrinsic-size: auto 96px }` → skor 49 → 47 (noise). **Dibatalkan.**

### Perbaikan yang diperkirakan berdampak besar (butuh keputusan user — mengubah perilaku peta)
1. **Marker DOM → layer GeoJSON `symbol`** di MapLibre. Sekarang tiap laporan = 1 elemen DOM
   (ratusan node) + listener sendiri; layer vektor menghapus ribuan operasi layout/paint.
2. **Tunda init peta** sampai `load`/`requestIdleCallback` pertama (LCP 5,1 s kemungkinan besar kanvas peta).
3. **Batasi marker awal** (mis. 100 terdekat + sisanya saat idle) atau default clustering lebih agresif.
4. Hapus `cdn-reach.hostinger.com/js/embed.js` kalau tidak dipakai.
5. Minify ui.css/ui.js (hemat ~5 KiB) — dampak kecil.

### Aksesibilitas
- Detail 96: satu kegagalan `color-contrast` pada `<span style="opacity:.75">` (catatan nomor polisi).
  **Sudah diperbaiki** → diganti kelas `.kh-note-ink` (warna `#9fb8c2`, tanpa opacity).
- Beranda 100 · SEO 100 di kedua halaman.
