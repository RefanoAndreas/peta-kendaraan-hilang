<?php
/**
 * Panduan publik: 5 halaman pilar di `/panduan/…`.
 *
 * Kenapa ada di plugin, bukan di wp-admin: isi panduan ikut versi kode (bisa
 * di-review di git, sama di semua environment) dan blok datanya diambil **live**
 * dari database — jumlah laporan per kecamatan/wilayah/bulan berubah sendiri,
 * jadi halaman tidak pernah basi.
 *
 * Fakta prosedural (syarat lapor polisi, STTLP/SKTLK, blokir STNK) mengacu pada
 * halaman resmi Polri (pusiknas.polri.go.id, polri.go.id) dan Samsat. Jangan
 * menambah angka biaya/tenggat yang tidak ada di sumber resmi.
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daftar halaman pilar. Urutan = urutan di halaman indeks.
 *
 * @return array
 */
function kh_panduan_slugs() {
	return array(
		'cara-melaporkan-motor-hilang',
		'cek-plat-nomor-motor-hilang',
		'motor-hilang-surabaya',
		'kawasan-rawan-curanmor-surabaya',
		'motor-hilang-ditemukan-lagi',
	);
}

/**
 * Isi satu halaman pilar: judul, SEO, tanggal tinjau, blok konten.
 *
 * Blok: array( 't' => h2|h3|p|ul|ol|table|data|faq|links, ... ).
 *
 * @param string $slug Slug halaman (anak dari /panduan/).
 * @return array|null
 */
function kh_panduan_item( $slug ) {
	$d = kh_panduan_data();
	return isset( $d[ $slug ] ) ? $d[ $slug ] : null;
}

/**
 * Semua data panduan.
 *
 * @return array
 */
function kh_panduan_data() {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}

	$data = array(

		'cara-melaporkan-motor-hilang' => array(
			'judul'     => 'Cara Melaporkan Motor Hilang ke Polisi',
			'seo_title' => 'Cara Melaporkan Motor Hilang ke Polisi: Syarat & Urutan',
			'seo_desc'  => 'Urutan melapor kehilangan motor: dokumen yang dibawa, membuat STTLP/SKTLK di Polsek, blokir STNK di Samsat, sampai klaim asuransi.',
			'tinjau'    => '24 November 2026',
			'blok'      => array(
				array( 't' => 'p', 'x' => 'Kehilangan motor bukan cuma soal kendaraan yang hilang — tanpa laporan polisi, STNK tetap tercatat atas namamu dan pajak kendaraan tetap berjalan. Urutan di bawah ini yang paling praktis: lapor ke polisi dulu, baru urus Samsat dan asuransi.' ),

				array( 't' => 'h2', 'x' => 'Ringkasnya: 3 urutan yang benar' ),
				array( 't' => 'ol', 'x' => array(
					'**Lapor ke Polsek/Polres** di wilayah tempat kejadian (TKP), bawa bukti kepemilikan → dapat **STTLP** (surat tanda terima laporan polisi).',
					'**Blokir STNK di Samsat** dengan membawa STTLP, supaya kendaraan tidak bisa dipakai/dijual dan pajak progresif tidak menumpuk.',
					'**Klaim asuransi** (kalau ada) — hampir semua polis mensyaratkan laporan polisi + STTLP.',
				) ),

				array( 't' => 'h2', 'x' => '1. Lapor ke Polsek atau Polres terdekat' ),
				array( 't' => 'p', 'x' => 'Datang ke kantor polisi yang membawahi lokasi kejadian — Polsek kalau kejadiannya di satu kecamatan, Polres/Polrestabes kalau perlu. Bawa dokumen asli dan salinannya:' ),
				array( 't' => 'ul', 'x' => array(
					'**KTP** pemilik kendaraan (dan KTP pelapor kalau berbeda orang).',
					'**STNK** asli — kalau STNK ikut hilang, bawa **BPKB**.',
					'**BPKB** asli (untuk membuktikan kepemilikan; kalau masih di leasing, bawa fotokopi + surat keterangan leasing).',
					'**Faktur pembelian / kwitansi** kalau ada — membantu mempercepat verifikasi.',
					'**Kronologi tertulis**: tanggal, jam, lokasi, ciri kendaraan, dan nomor polisi.',
					'Foto kendaraan, STNK, dan BPKB kalau punya — simpan di ponsel sebelum berangkat.',
				) ),
				array( 't' => 'p', 'x' => 'Di beberapa daerah, polisi meminta **surat pengantar RT/RW** atau surat keterangan kehilangan dari kelurahan. Ini bukan syarat nasional, jadi tanyakan dulu ke Polsek setempat lewat telepon supaya tidak bolak-balik.' ),

				array( 't' => 'h2', 'x' => '2. Terima STTLP (atau SKTLK)' ),
				array( 't' => 'p', 'x' => 'Setelah laporan diterima, kamu akan mendapat **STTLP** — di sebagian wilayah disebut **SKTLK** (Surat Keterangan Tanda Lapor Kehilangan). Dokumen ini yang diminta Samsat, asuransi, dan instansi lain. Periksa di tempat: nomor polisi, merek/model, tanggal, jam, dan lokasi harus sama dengan kronologimu. Kalau ada yang salah, minta diperbaiki saat itu juga.' ),
				array( 't' => 'p', 'x' => 'Soal biaya: STTLP umumnya tidak dipungut biaya. Kalau ada pungutan, minta **kuitansi resmi** — dan jangan pernah memberi uang di luar loket.' ),

				array( 't' => 'h2', 'x' => '3. Blokir STNK di Samsat' ),
				array( 't' => 'p', 'x' => 'Pemblokiran mencegah kendaraan dipakai atau dijual atas namamu, sekaligus menghentikan penumpukan pajak. Ada dua jalur: **datang langsung ke Samsat** dengan STTLP + KTP + fotokopi STNK/BPKB, atau **online lewat aplikasi SIGNAL** (Samsat Digital Nasional) kalau kendaraanmu sudah terdaftar di sana. Prosedur dan syarat tambahan bisa berbeda per Samsat, jadi cek dulu ke kantor Samsat wilayahmu.' ),

				array( 't' => 'h2', 'x' => '4. Kalau punya asuransi' ),
				array( 't' => 'p', 'x' => 'Hubungi asuransi secepatnya dan tanyakan tenggat laporan (banyak polis meminta laporan dalam hitungan hari). Siapkan fotokopi polis, STTLP, STNK/BPKB, dan KTP. Untuk kendaraan yang masih kredit, bank/leasing juga perlu diberi tahu karena BPKB ada di mereka.' ),

				array( 't' => 'h2', 'x' => 'Yang sering terlewat' ),
				array( 't' => 'ul', 'x' => array(
					'**Jangan mengurus sendiri dengan menebus uang.** Kalau ada yang mengaku menemukan kendaraanmu lalu minta biaya di muka, itu modus penipuan. Semua serah terima lewat kantor polisi.',
					'**Catat nomor STTLP** dan simpan salinan digitalnya (foto/PDF) — sering diminta lagi saat klaim atau saat kendaraan ditemukan.',
					'**Periksa status pajak** kendaraan secara berkala; pemblokiran kadang butuh waktu untuk tercatat.',
					'**Laporkan juga ke komunitas** — banyak kendaraan ditemukan karena warga mengenali nomor polisinya, bukan karena pelacakan.',
				) ),

				array( 't' => 'data', 'x' => 'checklist' ),

				array( 't' => 'h2', 'x' => 'Pertanyaan yang sering muncul' ),
				array( 't' => 'faq', 'x' => array(
					array( 'Berapa lama batas waktu melapor?', 'Tidak ada batas baku, tetapi semakin cepat semakin baik: rekaman CCTV masih tersimpan, dan blokir STNK bisa segera diproses. Kalau kejadiannya baru, lapor di hari yang sama.' ),
					array( 'Boleh lapor di luar kota kejadian?', 'Umumnya laporan dibuat di wilayah hukum tempat kejadian (Polsek/Polres setempat). Kalau kamu sudah di kota lain, kamu bisa meminta bantuan polisi setempat untuk berkoordinasi, tetapi laporan tetap diproses di wilayah TKP.' ),
					array( 'STNK ikut hilang, bagaimana?', 'Bawa BPKB sebagai bukti kepemilikan. Untuk penggantian STNK, Polri mensyaratkan laporan polisi kehilangan STNK — jadi laporan kehilangan tetap jadi langkah pertama.' ),
					array( 'Apakah laporan polisi bisa dicabut kalau motor ketemu?', 'Bisa. Datang lagi ke kantor polisi yang menerima laporan dengan membawa STTLP dan bukti kendaraan sudah ditemukan, lalu minta pencabutan/pembaruan status laporan.' ),
				) ),

				array( 't' => 'h2', 'x' => 'Baca juga' ),
				array( 't' => 'links', 'x' => array(
					array( 'Cek plat nomor motor hilang', '/panduan/cek-plat-nomor-motor-hilang/' ),
					array( 'Motor ditemukan lagi: yang harus dilakukan', '/panduan/motor-hilang-ditemukan-lagi/' ),
					array( 'Data kendaraan hilang per kecamatan', '/wilayah/surabaya/' ),
				) ),
			),
		),

		'cek-plat-nomor-motor-hilang' => array(
			'judul'     => 'Cek Plat Nomor Motor Hilang',
			'seo_title' => 'Cek Plat Nomor Motor Hilang: Yang Resmi & Yang Bukan',
			'seo_desc'  => 'Cara memeriksa nomor polisi kendaraan yang diduga hilang: arsip laporan warga, cek status pajak lewat SIGNAL/e-Samsat, dan batas data tidak resmi.',
			'tinjau'    => '24 November 2026',
			'blok'      => array(
				array( 't' => 'p', 'x' => 'Ada banyak situs yang menjanjikan "cek plat nomor hilang". Kenyataannya tidak ada layanan resmi yang memberi tahu apakah sebuah nomor polisi sedang dilaporkan hilang — data laporan hilang bukan data publik kepolisian. Yang bisa kamu lakukan ada tiga, dan penting tahu bedanya.' ),

				array( 't' => 'h2', 'x' => '1. Cek di arsip laporan warga (seperti situs ini)' ),
				array( 't' => 'p', 'x' => 'Situs ini mengumpulkan laporan kehilangan dari pemberitaan dan laporan warga, lalu menampilkannya **lengkap dengan nomor polisi**. Kalau kamu melihat motor dengan plat tertentu dan ingin tahu apakah sudah pernah dilaporkan hilang, ini tempat paling cepat. Caranya: tempel nomor polisi di kotak pencarian peta — format apa pun diterima ("L 3051 BBE", "l3051bbe", "3051").' ),
				array( 't' => 'p', 'x' => 'Batasnya harus jelas: **situs ini bukan kanal resmi**. Tidak semua kehilangan dilaporkan ke kami, dan tidak semua laporan kami terverifikasi polisi. Hasil kosong bukan berarti kendaraan itu legal — hanya berarti belum ada di arsip kami.' ),

				array( 't' => 'h2', 'x' => '2. Cek status kendaraan yang resmi: SIGNAL / e-Samsat' ),
				array( 't' => 'p', 'x' => 'Aplikasi **SIGNAL** (Samsat Digital Nasional) dan layanan **e-Samsat** daerah menampilkan data resmi kendaraan: status pajak, masa berlaku STNK, dan identitas pemilik terdaftar (sebagian disembunyikan). Ini berguna sebelum membeli motor bekas: kalau kendaraan sudah diblokir karena laporan kehilangan, statusnya akan terlihat di sana.' ),
				array( 't' => 'p', 'x' => 'Yang tidak bisa dilakukan SIGNAL: memberi tahu "apakah motor ini dicuri". Pemblokiran hanya tercatat kalau pemilik melapor dan Samsat sudah memprosesnya.' ),

				array( 't' => 'h2', 'x' => '3. Minta bantuan polisi untuk pengecekan' ),
				array( 't' => 'p', 'x' => 'Kalau kamu punya dugaan kuat (misalnya menemukan motor terparkir lama dengan plat yang cocok dengan laporan), jangan mendekat sendiri. Catat lokasi, foto dari jarak aman, lalu laporkan ke Polsek setempat atau hubungi 110. Polisi yang berwenang mencocokkan nomor rangka dan nomor mesin — dua nomor itu yang menentukan identitas kendaraan, bukan platnya.' ),

				array( 't' => 'h2', 'x' => 'Kenapa plat saja tidak cukup' ),
				array( 't' => 'ul', 'x' => array(
					'**Plat bisa dipalsukan atau diganti** dalam hitungan menit; nomor rangka dan nomor mesin tidak.',
					'**Satu plat bisa muncul di beberapa laporan** — misalnya karena salah baca saat mencatat, atau karena plat dipakai ulang setelah pemilik lama memblokir STNK.',
					'**Data laporan bisa keliru**: lokasi dan ciri kendaraan berasal dari berita atau laporan warga, dan bisa dikoreksi kemudian.',
				) ),

				array( 't' => 'h2', 'x' => 'Kalau kamu menemukan kendaraan yang cocok' ),
				array( 't' => 'ol', 'x' => array(
					'Jangan mengadang atau mengejar pengendara. Risiko keselamatan lebih besar daripada manfaatnya.',
					'Catat: plat, lokasi persis, waktu, ciri tambahan (kerusakan, stiker, barang bawaan).',
					'Buka halaman laporan di situs ini, tekan **"Saya menemukan kendaraan ini"**, isi kontak. Admin menghubungi pelapor lebih dulu sebelum meneruskan.',
					'Untuk tindakan resmi, hubungi Polsek setempat atau 110.',
					'**Jangan pernah mentransfer uang** kepada siapa pun yang mengaku menemukan kendaraan. Serah terima hanya di kantor polisi.',
				) ),

				array( 't' => 'data', 'x' => 'cek-plat' ),

				array( 't' => 'h2', 'x' => 'Pertanyaan yang sering muncul' ),
				array( 't' => 'faq', 'x' => array(
					array( 'Apakah ada situs resmi untuk cek plat motor hilang?', 'Tidak ada layanan publik dari kepolisian untuk itu. Yang resmi adalah SIGNAL/e-Samsat (data kendaraan & status blokir) dan laporan langsung ke Polsek/Polres.' ),
					array( 'Kalau plat tidak ditemukan di situs ini, berarti motor aman?', 'Belum tentu. Arsip kami hanya memuat laporan yang masuk lewat pemberitaan dan laporan warga. Kendaraan bisa saja sudah dilaporkan ke polisi tetapi tidak pernah masuk berita.' ),
					array( 'Bisakah saya cek nomor rangka lewat situs ini?', 'Tidak. Kami hanya menampilkan data yang dipublikasikan: plat, merek/model, warna, waktu, dan lokasi kejadian. Nomor rangka dan nomor mesin hanya bisa dicek petugas.' ),
					array( 'Data plat di situs ini dari mana?', 'Dari pemberitaan (antara lain Suara Surabaya) dan laporan warga yang dikirim lewat formulir. Setiap laporan mencantumkan tautan sumbernya di halaman detail.' ),
				) ),

				array( 't' => 'h2', 'x' => 'Baca juga' ),
				array( 't' => 'links', 'x' => array(
					array( 'Cara melaporkan motor hilang ke polisi', '/panduan/cara-melaporkan-motor-hilang/' ),
					array( 'Motor hilang di Surabaya: data terbaru', '/panduan/motor-hilang-surabaya/' ),
					array( 'Buka peta kendaraan hilang', '/' ),
				) ),
			),
		),

		'motor-hilang-surabaya' => array(
			'judul'     => 'Motor Hilang di Surabaya',
			'seo_title' => 'Motor Hilang Surabaya: Data Terbaru per Wilayah & Kecamatan',
			'seo_desc'  => 'Rekap laporan motor dan mobil hilang di Surabaya: jumlah per kecamatan, model yang paling sering hilang, jam rawan, dan langkah melaporkannya.',
			'tinjau'    => '24 November 2026',
			'blok'      => array(
				array( 't' => 'p', 'x' => 'Halaman ini merangkum laporan kehilangan kendaraan di Surabaya yang masuk ke arsip kami dari pemberitaan dan laporan warga. Angkanya diperbarui otomatis — bukan data statistik resmi kepolisian, tapi cukup untuk melihat pola: kawasan mana yang paling sering muncul, motor tipe apa yang paling sering hilang, dan pada jam berapa kejadiannya.' ),

				array( 't' => 'data', 'x' => 'ringkas-surabaya' ),

				array( 't' => 'h2', 'x' => 'Sebaran per kecamatan' ),
				array( 't' => 'p', 'x' => 'Kecamatan ditaksir dari **koordinat lokasi kejadian**, bukan dari teks alamat — laporan warga hampir selalu menyebut nama jalan atau patokan, bukan nama kecamatan. Klik kecamatan untuk melihat daftar laporannya.' ),
				array( 't' => 'data', 'x' => 'tabel-kecamatan' ),

				array( 't' => 'h2', 'x' => 'Model yang paling sering hilang' ),
				array( 't' => 'p', 'x' => 'Motor matic mendominasi karena jumlahnya memang paling banyak di jalan. Tabel di bawah menghitung merek + model yang muncul di laporan Surabaya.' ),
				array( 't' => 'data', 'x' => 'tabel-model' ),

				array( 't' => 'h2', 'x' => 'Jam kejadian' ),
				array( 't' => 'p', 'x' => 'Sebagian laporan mencantumkan jam kejadian. Pola yang muncul konsisten dengan pengalaman warga: malam sampai dini hari mendominasi, saat kendaraan diparkir di luar rumah atau di gang.' ),
				array( 't' => 'data', 'x' => 'tabel-jam' ),

				array( 't' => 'h2', 'x' => 'Kalau motor kamu hilang di Surabaya' ),
				array( 't' => 'ol', 'x' => array(
					'Lapor ke Polsek wilayah kejadian, bawa KTP, STNK, dan BPKB — dapat **STTLP**.',
					'Blokir STNK di Samsat Surabaya atau lewat aplikasi SIGNAL.',
					'Kirim laporan ke situs ini lewat **formulir lapor** supaya warga lain bisa ikut memantau.',
					'Bagikan halaman laporanmu ke grup WA/IG kawasan — kebanyakan temuan berawal dari warga yang mengenali plat.',
				) ),
				array( 't' => 'p', 'x' => 'Detail langkah dan dokumennya ada di panduan [Cara melaporkan motor hilang ke polisi](/panduan/cara-melaporkan-motor-hilang/).' ),

				array( 't' => 'h2', 'x' => 'Pertanyaan yang sering muncul' ),
				array( 't' => 'faq', 'x' => array(
					array( 'Apakah angka di halaman ini data resmi polisi?', 'Bukan. Angkanya berasal dari pemberitaan dan laporan warga yang masuk ke arsip kami. Untuk data resmi, minta ke Polrestabes Surabaya.' ),
					array( 'Kenapa kecamatan tertentu angkanya kecil?', 'Karena laporan yang masuk tidak merata: berita lebih sering mengangkat kejadian di kawasan tertentu. Angka kecil berarti "sedikit laporan masuk", bukan "aman".' ),
					array( 'Apakah semua laporan sudah diverifikasi?', 'Tidak. Setiap laporan punya status: belum diverifikasi, terverifikasi media, atau sudah lapor polisi. Status ini terlihat di kartu laporan dan di peta.' ),
				) ),

				array( 't' => 'h2', 'x' => 'Baca juga' ),
				array( 't' => 'links', 'x' => array(
					array( 'Kawasan rawan curanmor Surabaya', '/panduan/kawasan-rawan-curanmor-surabaya/' ),
					array( 'Cek plat nomor motor hilang', '/panduan/cek-plat-nomor-motor-hilang/' ),
					array( 'Semua wilayah di Jawa Timur', '/wilayah/' ),
				) ),
			),
		),

		'kawasan-rawan-curanmor-surabaya' => array(
			'judul'     => 'Kawasan Rawan Curanmor Surabaya',
			'seo_title' => 'Kawasan Rawan Curanmor Surabaya Menurut Data Laporan',
			'seo_desc'  => 'Kecamatan dengan laporan kehilangan motor terbanyak di Surabaya, jam rawan, dan kebiasaan parkir yang paling sering dimanfaatkan pelaku.',
			'tinjau'    => '24 November 2026',
			'blok'      => array(
				array( 't' => 'p', 'x' => 'Peringatan di depan: tabel di bawah bukan peta kriminalitas resmi. Angkanya adalah **jumlah laporan yang masuk ke arsip kami** — jadi kecamatan dengan angka tinggi berarti "banyak laporan masuk", bukan otomatis "paling tidak aman". Sebaliknya, angka rendah bisa berarti kejadiannya jarang diberitakan.' ),
				array( 't' => 'p', 'x' => 'Yang membuat data ini berguna: semuanya dari kejadian nyata dengan lokasi, waktu, dan tipe kendaraan. Untuk memilih rute pulang malam atau memutuskan di mana memarkir motor, pola seperti ini lebih membantu daripada statistik tahunan.' ),

				array( 't' => 'h2', 'x' => 'Kecamatan dengan laporan terbanyak' ),
				array( 't' => 'data', 'x' => 'tabel-kecamatan' ),

				array( 't' => 'h2', 'x' => 'Jam rawan' ),
				array( 't' => 'p', 'x' => 'Mayoritas kejadian terjadi saat kendaraan diparkir dan pemiliknya tidur atau sedang bekerja. Perhatikan juga waktu subuh: banyak laporan menyebut motor hilang antara pukul 03.00 dan 05.00.' ),
				array( 't' => 'data', 'x' => 'tabel-jam' ),

				array( 't' => 'h2', 'x' => 'Pola lokasi yang sering muncul di laporan' ),
				array( 't' => 'ul', 'x' => array(
					'**Parkir di depan rumah tanpa kunci tambahan** — paling sering, terutama di gang sempit.',
					'**Kos dan rumah kontrakan** tanpa pagar atau tanpa penjaga.',
					'**Warung/toko yang tutup** dengan motor ditinggal semalam.',
					'**Minimarket dan pertokoan** tempat motor diparkir di badan jalan.',
					'**Kawasan industri dan pergudangan** saat shift malam.',
					'**Tempat parkir liar** tanpa karcis — tidak ada yang mencatat keluar-masuk.',
				) ),

				array( 't' => 'h2', 'x' => 'Kebiasaan yang benar-benar mengurangi risiko' ),
				array( 't' => 'ol', 'x' => array(
					'Kunci ganda: kunci cakram + rantai ke tiang atau tiang pagar. Pelaku lebih memilih target yang butuh waktu lama.',
					'Jangan parkir di titik buta CCTV; kalau terpaksa, pilih tempat yang terlihat dari pos jaga atau warung yang masih buka.',
					'Aktifkan alarm dan simpan STNK di dompet — STNK di bagasi memudahkan pelaku menjual kendaraan.',
					'Catat nomor rangka dan nomor mesin, foto semua sisi motor. Ini yang dipakai polisi saat pencocokan.',
					'Kalau memarkir lama di tempat umum, titip ke juru parkir resmi dan simpan karcisnya.',
				) ),

				array( 't' => 'h2', 'x' => 'Pertanyaan yang sering muncul' ),
				array( 't' => 'faq', 'x' => array(
					array( 'Apakah data ini bisa dipakai untuk klaim asuransi?', 'Tidak. Asuransi meminta laporan polisi (STTLP), bukan rekap komunitas. Halaman ini hanya membantu melihat pola.' ),
					array( 'Kenapa kecamatan X tidak muncul?', 'Hanya kecamatan yang punya laporan dengan koordinat cukup dekat yang ditampilkan. Kalau titik kejadiannya lebih dari 3,5 km dari pusat kecamatan, laporannya tidak dipetakan ke kecamatan mana pun.' ),
					array( 'Bagaimana cara ikut menyumbang data?', 'Kirim laporan lewat formulir lapor (gratis, cukup masuk dengan akun Google). Sertakan lokasi di peta dan, kalau ada, tautan berita atau nomor STTLP.' ),
				) ),

				array( 't' => 'h2', 'x' => 'Baca juga' ),
				array( 't' => 'links', 'x' => array(
					array( 'Motor hilang Surabaya: data lengkap', '/panduan/motor-hilang-surabaya/' ),
					array( 'Cara melaporkan motor hilang ke polisi', '/panduan/cara-melaporkan-motor-hilang/' ),
					array( 'Peta kendaraan hilang', '/' ),
				) ),
			),
		),

		'motor-hilang-ditemukan-lagi' => array(
			'judul'     => 'Motor Hilang Ditemukan Lagi',
			'seo_title' => 'Motor Hilang Ditemukan Lagi: Langkah Resmi & Anti-Penipuan',
			'seo_desc'  => 'Kendaraan ditemukan? Ini urutan resminya: lapor polisi, cek dokumen, cabut laporan/blokir STNK, dan cara menghindari modus penipuan tebus motor.',
			'tinjau'    => '24 November 2026',
			'blok'      => array(
				array( 't' => 'p', 'x' => 'Kabar kendaraan ditemukan selalu campur aduk: lega, tapi juga rawan dimanfaatkan orang lain. Urutan yang benar di bawah ini menjaga kamu tetap aman secara hukum dan tidak kehilangan uang.' ),

				array( 't' => 'h2', 'x' => '1. Jangan langsung mengambil sendiri' ),
				array( 't' => 'p', 'x' => 'Kalau kendaraan ditemukan dalam kondisi utuh di tempat umum, cara paling aman: foto, catat lokasi, lalu hubungi polisi. Kalau kendaraan ditemukan sudah dirusak atau dipreteli, jangan menyentuhnya sama sekali — itu barang bukti.' ),

				array( 't' => 'h2', 'x' => '2. Lapor ke kantor polisi yang menerima laporan awal' ),
				array( 't' => 'p', 'x' => 'Bawa STTLP (atau nomor laporannya), KTP, dan STNK/BPKB. Polisi akan mencocokkan nomor rangka dan nomor mesin, lalu membuat catatan bahwa kendaraan sudah ditemukan. Tanpa catatan ini, status kendaraan di Samsat bisa tetap terblokir.' ),

				array( 't' => 'h2', 'x' => '3. Buka blokir STNK di Samsat' ),
				array( 't' => 'p', 'x' => 'Kalau dulu kamu memblokir STNK, sekarang urusannya membuka blokir. Syaratnya biasanya: STTLP, surat keterangan dari kepolisian bahwa kendaraan sudah ditemukan, KTP, dan STNK/BPKB. Datang ke Samsat wilayah kendaraan terdaftar atau lewat aplikasi SIGNAL.' ),

				array( 't' => 'h2', 'x' => '4. Kalau ada asuransi' ),
				array( 't' => 'p', 'x' => 'Segera beri tahu asuransi. Kalau klaim sudah dibayar, asuransi biasanya punya hak atas kendaraan yang ditemukan (subrogasi) — jangan menjual atau memindahtangankan kendaraan sebelum urusan ini selesai.' ),

				array( 't' => 'h2', 'x' => 'Modus penipuan yang paling sering terjadi' ),
				array( 't' => 'ul', 'x' => array(
					'**"Motor kamu ada di saya, transfer dulu biaya titip/transport."** Setelah transfer, nomor diblokir. Jangan pernah membayar sebelum melihat kendaraan dan dokumennya di kantor polisi.',
					'**"Saya temukan STNK/BPKB-mu."** Pelaku memakai data dari media sosial untuk meminta uang. Verifikasi lewat polisi, bukan lewat chat.',
					'**Surat jalan atau kuasa palsu** saat pengambilan kendaraan. Pengambilan hanya di kantor polisi dengan identitas yang diverifikasi petugas.',
					'**Kendaraan "ditemukan" tapi nomor rangka diubah.** Karena itu pencocokan nomor rangka/mesin wajib dilakukan polisi, bukan hanya melihat plat.',
				) ),

				array( 't' => 'h2', 'x' => 'Jangan lupa perbarui laporan di situs ini' ),
				array( 't' => 'p', 'x' => 'Kalau laporanmu ada di arsip kami, kirim pesan lewat **halaman kontak** atau tombol "Laporkan data ini" di halaman laporan supaya statusnya diubah menjadi **Ditemukan**. Ini penting: laporan yang sudah selesai tidak perlu lagi dibagikan, dan warga berhenti memantau kendaraan yang sudah kembali.' ),

				array( 't' => 'data', 'x' => 'ditemukan-terbaru' ),

				array( 't' => 'h2', 'x' => 'Pertanyaan yang sering muncul' ),
				array( 't' => 'faq', 'x' => array(
					array( 'Apakah laporan polisi otomatis hilang kalau motor ditemukan?', 'Tidak otomatis. Kamu (atau polisi) yang memperbarui statusnya. Simpan STTLP dan surat keterangan penemuan.' ),
					array( 'Motor ditemukan dalam kondisi rusak berat, bagaimana?', 'Tetap lapor dan catat kerusakannya. Kalau ada asuransi, kerusakan ini bagian dari klaim; kalau tidak, catatan polisi berguna saat servis atau saat menjual.' ),
					array( 'Bolehkah saya memberi imbalan kepada penemu?', 'Boleh dan sering dilakukan sebagai ucapan terima kasih, tetapi berikan **setelah** kendaraan dan dokumen diverifikasi polisi — dan jangan pernah di muka lewat transfer ke orang yang belum terverifikasi.' ),
				) ),

				array( 't' => 'h2', 'x' => 'Baca juga' ),
				array( 't' => 'links', 'x' => array(
					array( 'Cara melaporkan motor hilang ke polisi', '/panduan/cara-melaporkan-motor-hilang/' ),
					array( 'Cek plat nomor motor hilang', '/panduan/cek-plat-nomor-motor-hilang/' ),
					array( 'Laporan yang sudah ditemukan', '/kendaraan/' ),
				) ),
			),
		),
	);

	return $data;
}
