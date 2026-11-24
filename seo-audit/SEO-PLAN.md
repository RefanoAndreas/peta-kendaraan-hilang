# RENCANA SEO — Peta Kendaraan Hilang (hilang.refanoandreas.com)

Audit: 24 November 2026 · kondisi situs: plugin v0.43.5 · 432 halaman laporan di sitemap ·
SEO on-page sudah terpasang (title/desc/canonical/JSON-LD), tapi **arsitektur tautan internal bolong**.

---

## 1. Jawaban singkat: #1 itu realistis untuk keyword mana?

| Kelompok keyword | Contoh | Peluang #1 | Lawan |
|---|---|---|---|
| **Nopol spesifik** | `L 3051 BBE`, `W 2155 NAG hilang` | **Sangat tinggi** — kamu satu-satunya yang punya halaman stabil per plat | Tidak ada (artikel SS menggabung banyak plat dalam 1 halaman) |
| **Wilayah + jenis** | `motor hilang sidoarjo`, `kendaraan hilang gresik` | **Tinggi**, 1–3 bulan | Artikel berita SS (per hari, cepat basi) |
| **Kecamatan** | `motor hilang rungkut`, `motor hilang kenjeran` | **Sangat tinggi**, nyaris tanpa lawan | Tidak ada yang punya halaman per kecamatan |
| **Utilital lokal** | `cek plat motor hilang surabaya`, `cara lapor motor hilang ke polisi`, `STTLP motor hilang` | **Tinggi**, 2–4 bulan | Artikel generik Kapanlagi/Tempo/Moladin (tanpa data & tanpa alat) |
| **Head term kota** | `motor hilang surabaya` | **Sedang**, 4–6 bulan + butuh backlink | Suara Surabaya (domain berotoritas, 10 hasil pertama milik mereka) |
| **Nasional** | `motor hilang`, `cek plat nomor`, `peta kendaraan hilang` | **Rendah / jangan dikejar** | Iklan GPS tracker, Samsat, media nasional |

Kesimpulan: **menang di ekor panjang (nopol + kecamatan + utilital), lalu naik ke head term kota.**
Head term tidak akan menang tanpa backlink — itu bagian Fase 3, bukan on-page.

---

## 2. Temuan audit (terukur, bukan dugaan)

| # | Temuan | Bukti | Dampak |
|---|---|---|---|
| 1 | **Beranda 0 tautan ke halaman laporan** | `grep href` di HTML mentah beranda: 0 × `/kendaraan/` | 432 halaman hanya ditemukan lewat sitemap. Tanpa tautan internal, tidak ada aliran otoritas & tidak ada sinyal anchor text. |
| 2 | **Beranda hanya 725 karakter teks** | strip tag → 725 char; H1 "PETA KENDARAAN HILANG", isi peta dimuat JS | Beranda tidak bisa meranking apa pun selain nama merek. |
| 3 | **`/wilayah/` = HTTP 404** | dicek langsung | Tidak ada hub taksonomi. 10 halaman wilayah hanya bisa ditemukan dari sitemap. |
| 4 | **Halaman detail nyaris tanpa tautan keluar** | detail L 3051 BBE: 1 tautan `/wilayah/`, 5 tautan internal (mayoritas nav legal) | Link graph berbentuk bintang tanpa sisi — 432 halaman terisolasi. |
| 5 | **Halaman detail 319 kata** | diukur | Tipis; tidak ada modul yang menambah teks + tautan kontekstual. |
| 6 | **Taksonomi `jenis` & `status` hanya 1 term** (`motor`, `hilang`) | sitemap taxonomies | Halaman doorway tipis. Jangan diindeks. |
| 7 | **Judul & deskripsi detail tidak memuat nopol** | `title` = "Hilang Honda Beat 2025 di Jalan Sumur Welut — …" | Keyword nopol (peluang #1 terkuat) tidak ada di title. |
| 8 | ~~Deskripsi beranda "432 laporan" basi~~ → **ternyata benar**: angkanya `wp_count_posts()->publish` (live), bukan hardcode. Yang 423 adalah angka HANDOFF 20 Nov. | dicek ulang 24 Nov | Tidak ada masalah; dibatalkan. |
| 9 | **"Pernah dikoreksi" tampil 2× identik** di satu halaman | teks halaman detail | Bug editorial kecil, mengganggu kualitas halaman. |
| 10 | **Mobile LCP 4,1 s** (Lighthouse 11 Nov) | catatan HANDOFF §2 | Core Web Vitals = faktor peringkat. |
| 11 | ~~Tidak ada BreadcrumbList~~ → **sudah ada** di `inc/schema.php` (WebSite + WebPage + Vehicle + BreadcrumbList + CollectionPage). Yang belum: Organization/Person. | dicek 24 Nov | Koreksi: cukup tambah Organization/Person, jangan bongkar yang ada. |

Catatan: skema dasar (WebSite, WebPage, CollectionPage, mainEntity) **sudah bagus** — jangan diutak-atik dulu.
Indeks Google tidak bisa diukur dari luar (captcha). Angka pasti harus dari **Search Console** → Fase 0.

---

## 3. Fase 0 — Ukur dulu (hari ini, 30 menit, tanpa sentuh kode)

1. **Google Search Console** → properti *Domain* `refanoandreas.com` atau *URL prefix* `https://hilang.refanoandreas.com/`.
   Verifikasi: file HTML di `public_html` (bisa diunggah via TUS MCP, tidak perlu akses DNS).
   Setelah verifikasi: submit `https://hilang.refanoandreas.com/wp-sitemap.xml`, lalu cek **Pages → Not indexed** (alasan apa).
2. **Bing Webmaster Tools** → "Import from GSC" (gratis, 2 menit).
3. Pasang **monitoring posisi**: manual mingguan untuk 8 query (daftar di §7) atau pakai GSC → Performance → Queries.

Tanpa langkah ini kita buta: tidak tahu 432 halaman terindeks atau 30.

---

## 4. Fase 1 — Perbaikan teknis ✅ **SELESAI 24 Nov 2026 (plugin v0.44.1)**

Semua di plugin `kendaraan-hilang-core` (bukan plugin pihak ketiga), lalu deploy TUS + naikkan versi + purge cache.

### 1.1 Blok "server-rendered" di bawah peta beranda — ✅ live
`templates/front-peta.php` — tambahkan di bawah kanvas peta (bukan overlay, agar tidak mengganggu ukuran peta yang sudah diukur):

- `<h2>Laporan terbaru</h2>` + `<ul>` 30 tautan laporan terbaru (plat + model + wilayah).
- `<h2>Kendaraan hilang per wilayah</h2>` + 10 tautan `/wilayah/<slug>/` dengan jumlah laporan.
- 300–500 kata teks nyata: apa itu halaman ini, sumber data, cara pakai, cakupan wilayah, cara lapor.
  Target keyword: *peta kendaraan hilang Jawa Timur*, *daftar motor hilang Surabaya Sidoarjo Gresik*.

Hasil: 432 halaman dapat jalur crawl + anchor text berisi plat/model/wilayah.

### 1.2 Hub `/wilayah/` — ✅ live (halaman WP, opsi `kh_wilayah_page`)
Halaman WP sungguhan (bukan rewrite kosong — lihat jebakan 404 di HANDOFF §2a) yang menampilkan semua
taksonomi wilayah + jumlah laporan + 3 laporan terbaru per wilayah. Tautkan dari footer (`kh_legal_nav()`
atau nav terpisah) dan dari blok 1.1.

### 1.3 Modul tautan di halaman detail — ✅ live
`templates/single-kendaraan.php`:
- Breadcrumb: Beranda › Kendaraan Hilang Surabaya › L 3051 BBE (dengan **BreadcrumbList** JSON-LD).
- "Laporan lain di <wilayah>" — 6 tautan laporan terdekat/terbaru di wilayah yang sama.
- "Laporan lain dengan model sama" — 4 tautan (Beat 2025 dsb).
- Tautan ke `/wilayah/<slug>/`.

Hasil: 432 halaman saling terhubung → crawl budget & otoritas mengalir, teks per halaman naik dari 319 jadi ~450 kata.

### 1.4 Judul & deskripsi halaman detail menyertakan nopol — ✅ live
Sekarang: `Hilang Honda Beat 2025 di Jalan Sumur Welut — Peta Kendaraan Hilang`
Usul: `L 3051 BBE Hilang — Honda Beat 2025, Sumur Welut Surabaya`
Deskripsi: sudah memuat plat, pertahankan.

### 1.5 Bersihkan doorway — ✅ live
`noindex` untuk taksonomi `jenis` & `status` (hanya 1 term), keluarkan dari `wp-sitemap`. Wilayah tetap indeks.

### 1.6 Perbaikan kecil — ✅ sebagian (koreksi dobel sudah; Organization/Person belum)
- Sinkronkan angka di meta description beranda (hitung live, jangan hardcode).
- Hilangkan duplikasi "Pernah dikoreksi" (`inc/pedoman.php`).
- Tambah `Organization` + `Person` (pemilik situs) di `inc/schema.php` untuk E-E-A-T.

---

## 5. Fase 2 — Konten yang menangkap intent (2–4 minggu, ini yang menaikkan peringkat)

### 2.1 Halaman kecamatan (31 halaman, prioritas tertinggi)
Data 31 titik pusat kecamatan Surabaya **sudah ada** di `inc/data-kecamatan.php` dan dipakai halaman
analitik. Ubah jadi taksonomi/halaman nyata: `/wilayah/surabaya/rungkut/`.
Isi per halaman: jumlah laporan, peta mini, tabel laporan, jam rawan kecamatan itu, polsek terdekat.
Target: `motor hilang rungkut`, `motor hilang kenjeran`, `motor hilang lakarsantri` — **tanpa kompetitor**.

### 2.2 Pillar "panduan" (5 halaman, satu kali tulis, jangan AI-generate mentah)
| URL | Target keyword | Kenapa menang |
|---|---|---|
| `/panduan/cara-melaporkan-motor-hilang/` | `cara lapor motor hilang ke polisi`, `STTLP` | Kompetitor artikel generik; kamu bisa sertakan alur nyata + link form |
| `/panduan/cek-plat-nomor-motor-hilang/` | `cek plat motor hilang`, `cek nopol hilang` | Volume besar; kamu punya **alat pencarian plat sungguhan** (E-E-A-T + dwell time) |
| `/panduan/motor-hilang-surabaya/` | `motor hilang surabaya` (head term) | Hub kota yang diperbarui otomatis; head term hanya bisa dimenangkan begini |
| `/panduan/kawasan-rawan-curanmor-surabaya/` | `rawan curanmor surabaya`, `daerah rawan maling motor` | Datamu unik (jam 03.00–04.00, 31 kecamatan) → magnet backlink |
| `/panduan/motor-hilang-ditemukan-lagi/` | `motor ditemukan apakah lapor polisi`, `cabut laporan` | Intent pasca-kejadian, nol kompetitor relevan |

Aturan tulis: 800–1200 kata, tiap halaman punya tabel data live dari DB (bukan angka statis),
FAQ 4–6 pertanyaan + **FAQPage schema**, dan CTA ke peta/form lapor.

### 2.3 Halaman statistik publik
`/analitik/` sudah publik dan isinya unik — beri judul & deskripsi SEO ("Statistik Kendaraan Hilang Jawa Timur")
dan tautkan dari beranda/footer. Ini aset backlink paling kuat yang kamu punya.

---

## 6. Fase 3 — Otoritas & backlink (penentu #1 untuk `motor hilang surabaya`)

1. **Data story ke media lokal**: kirim ringkasan 1 halaman ("432 laporan: 68% Surabaya, Beat 46%,
   jam rawan 03.00–04.00, 31 kecamatan") ke Jawa Pos, Detik Jatim, Tribun Jatim, Suara Surabaya.
   Sertakan tautan. Satu backlink dari domain berita = lompatan besar, jauh lebih besar dari 10 artikel baru.
2. **Relasi dua arah dengan Suara Surabaya**: kamu mengutip mereka, tawarkan tautan balik
   ("data lengkap & peta: …"). Mereka sudah menyebut plat; kamu yang mengarsipkan.
3. **Distribusi sosial yang sudah ada** (kartu Story + QR, video promo) → pastikan setiap kartu punya URL
   halaman detail, bukan cuma gambar.
4. **Komunitas**: Kaskus (forum otomotif), grup FB curanmor per wilayah, Reddit r/indonesia.
   Jangan spam: satu posting berguna per kejadian besar.
5. **Konsistensi entitas**: nama situs "Peta Kendaraan Hilang" harus sama di title, schema, media sosial.

---

## 7. Keyword yang dipantau mingguan (GSC → Performance → Queries)

1. `motor hilang surabaya`
2. `motor hilang sidoarjo`
3. `kendaraan hilang jawa timur`
4. `cek plat motor hilang`
5. `motor hilang rungkut`
6. `cara melaporkan motor hilang`
7. `daftar motor hilang surabaya`
8. `L 3051 BBE` (sampel nopol — uji keterindeksan halaman detail)

---

## 8. Yang **tidak** perlu dilakukan (hemat waktu)

- Tidak perlu plugin SEO (Yoast/RankMath) — on-page sudah dikerjakan sendiri dan lebih rapi.
- Tidak perlu ganti domain: subdomain `refanoandreas.com` sudah mengumpulkan otoritas; pindah = risiko + 301.
- Tidak perlu mengejar `motor hilang` / `cek plat nomor` nasional — dikuasai iklan GPS & Samsat.
- Tidak perlu menambah artikel berita per kejadian — sudah tercakup halaman detail otomatis.
- Tidak perlu mengubah skema JSON-LD dasar (WebSite/WebPage/CollectionPage) — sudah benar.

---

## 9. Urutan eksekusi yang disarankan

| Langkah | Pekerjaan | Estimasi | Siapa |
|---|---|---|---|
| 0 | GSC + Bing WMT + submit sitemap | 30 mnt | kamu (login Google) — aku siapkan file verifikasi & unggah |
| 1 | Fase 1.1–1.6 (satu batch deploy) | 3–4 jam | aku |
| 2 | Halaman kecamatan (31) | 1–2 hari | aku (+ keputusan struktur URL) |
| 3 | 5 pillar panduan | 2–3 hari | aku draft, kamu review fakta |
| 4 | Data story ke media | 2–3 jam | kamu (butuh nama/email pengirim) |
| 5 | Ukur ulang 2 & 4 minggu | 30 mnt | aku |

Ekspektasi jujur: Fase 1 + GSC biasanya mulai terlihat dalam **2–3 minggu** (indeks naik drastis).
Peringkat #1 untuk ekor panjang: **1–3 bulan**. Head term kota: **4–6 bulan, dan hanya kalau Fase 3 jalan**.
