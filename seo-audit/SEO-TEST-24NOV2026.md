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
