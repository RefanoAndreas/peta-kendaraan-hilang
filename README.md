# Peta Kendaraan Hilang

Situs peta interaktif laporan kehilangan kendaraan (motor/mobil) di Jawa Timur.
Data dikutip dari pemberitaan media (Suara Surabaya FM 100) dan laporan warga,
dengan tautan sumber di setiap laporan.

**Live:** https://hilang.refanoandreas.com

---

## Fitur

- **Peta vektor gelap penuh** (MapLibre GL + style kustom, sumber tile OpenFreeMap/OpenStreetMap — gratis, tanpa API key)
- **Marker heksagon** berdenyut: merah = belum lapor polisi, biru = sudah lapor, teal = ditemukan
- **Label nomor polisi** muncul di bawah marker saat zoom ≥ 13.2
- **Panel laporan** dengan pencarian, filter jenis/wilayah/status
- **Timeline**: daftar kronologis dikelompokkan per tanggal
- **Kartu detail** + halaman laporan bernuansa gelap (termasuk peringatan anti-penipuan)
- **Arsip** semua laporan dengan pencarian & paginasi
- **API REST** `wp-json/kh/v1/peta` untuk data peta
- **Admin importer**: tempel URL artikel berita → parser ekstrak plat, model, tahun, warna, lokasi, polsek → draft + koordinat otomatis (Nominatim)

## Struktur

```
kendaraan-hilang-core/
├── kendaraan-hilang-core.php   # bootstrap, konstanta versi, aktivasi
├── inc/
│   ├── cpt.php                 # CPT `kendaraan`, taxonomy, seed, migrasi data
│   ├── fields.php              # meta box (plat, model, warna, lat/lng, sumber) + peta picker admin
│   ├── rest.php                # endpoint REST peta & ringkasan
│   ├── ui.php                  # template full-screen, enqueue aset, aset halaman gelap
│   ├── map.php                 # shortcode lama (peta Leaflet) + routing template
│   └── importer.php            # import artikel berita (parser + geocoding)
├── templates/
│   ├── front-peta.php          # halaman depan: panel + peta
│   ├── single-kendaraan.php    # detail laporan (gelap)
│   └── archive-kendaraan.php   # arsip laporan (gelap)
└── assets/
    ├── map-style.js            # style peta vektor "Ingress dark"
    ├── ui.js / ui.css          # logika & tampilan halaman depan
    ├── site.css                # tampilan halaman detail & arsip
    └── map.js / map.css        # peta Leaflet lama (shortcode)
```

## Model data

Custom post type `kendaraan` + taxonomy:

| Taxonomy | Nilai awal |
|---|---|
| `kendaraan_jenis` | Motor, Mobil, Truk, Lain |
| `kendaraan_wilayah` | Surabaya, Sidoarjo, Gresik, Jombang, Pasuruan, Probolinggo, Lainnya |
| `kendaraan_status` | Hilang, Ditemukan |

Meta (prefix `kh_`): `plat`, `plat_masked`, `merk`, `model`, `tahun`, `warna`, `ciri`,
`tanggal`, `jam`, `lokasi`, `lat`, `lng`, `polsek`, `sumber_nama`, `sumber_url`.

> Nomor polisi **ditampilkan lengkap** di situs publik. Ini keputusan redaksi:
> mengikuti praktik media, supaya warga bisa mengenali kendaraan dan melapor.
> Data lengkap tetap hanya untuk pihak kepolisian.

## Importer

`Kendaraan Hilang → Import dari URL` di wp-admin.

- Satu artikel bisa menghasilkan beberapa draft (satu per nomor polisi)
- Plat duplikat otomatis dilewati
- Koordinat dicari lewat Nominatim (per item, dengan cache & fallback query)
- Semua hasil masuk sebagai **draft** untuk diperiksa manusia sebelum publish

## Deploy

Plugin ini diletakkan di `wp-content/plugins/kendaraan-hilang-core/`.
Pada hosting tanpa SSH, file dapat diunggah lewat file manager / API hosting;
setiap perubahan versi wajib menaikkan `KH_CORE_VERSION` agar cache aset ikut berubah.

## Kredit & lisensi

- Peta: © kontributor OpenStreetMap, tile vektor oleh [OpenFreeMap](https://openfreemap.org)
- Renderer: [MapLibre GL JS](https://maplibre.org)
- Sumber berita contoh: [Suara Surabaya](https://www.suarasurabaya.net)
- Kode plugin: © Refano Andreas — lisensi GPL-2.0-or-later (mengikuti WordPress)
