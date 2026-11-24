<?php
/**
 * Halaman legal/informasi: Kebijakan Privasi, Tentang, Kontak, Disclaimer.
 *
 * Dibuat otomatis sekali (bisa diedit admin lewat wp-admin seperti halaman biasa).
 * Template-nya pakai tema gelap yang sama dengan halaman detail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daftar halaman + isi awal.
 */
function kh_legal_pages() {
	$email = 'business.refanoandreas@gmail.com';
	$hari  = defined( 'KH_ARSIP_HARI' ) ? (int) KH_ARSIP_HARI : 180;

	return array(
		'privasi'    => array(
			'title'   => 'Kebijakan Privasi',
			'excerpt' => 'Data apa yang kami kumpulkan, untuk apa, siapa yang bisa melihat, dan cara meminta penghapusan.',
			'content' => <<<HTML
<p>Halaman ini menjelaskan data apa yang dikumpulkan <strong>Peta Kendaraan Hilang</strong> (hilang.refanoandreas.com), untuk apa data itu dipakai, siapa yang bisa melihatnya, dan bagaimana kamu bisa meminta data dihapus.</p>

<h2>1. Data yang kami kumpulkan</h2>
<ul>
	<li><strong>Data akun Google (saat melapor):</strong> nama, alamat email, dan foto profil. Hanya dipakai untuk menandai siapa pelapornya dan mencegah laporan palsu berulang.</li>
	<li><strong>Data kendaraan &amp; kejadian:</strong> nomor polisi, merk/model/tahun, warna, ciri khusus, lokasi kejadian, tanggal/jam, nomor STTLP (kalau diisi), dan keterangan tambahan.</li>
	<li><strong>Info penemuan dari pengunjung:</strong> lokasi terlihat, keterangan, dan kontak (nomor WA atau email — wajib, atau terverifikasi lewat login Google).</li>
	<li><strong>Data teknis:</strong> alamat IP dan jenis perangkat/browser disimpan bersama info penemuan untuk menelusuri kiriman palsu; alamat IP juga dipakai untuk membatasi jumlah kiriman per hari.</li>
</ul>

<h2>2. Apa yang tampil publik</h2>
<p>Yang tampil di peta dan halaman detail hanya <strong>informasi kendaraan</strong> (termasuk nomor polisi penuh — ini disengaja agar warga bisa mengenali kendaraan), lokasi, waktu, dan status verifikasi.</p>
<p><strong>Alamat email dan kontak pelapor tidak pernah ditampilkan ke publik.</strong> Informasi penemuan dari identitas yang belum terverifikasi hanya masuk ke admin — admin menghubungi kontak tersebut lebih dulu dan baru meneruskannya ke pelapor setelah dipastikan. Kontak pengirim tidak diberikan langsung ke pihak lain.</p>
<p>Kami <strong>tidak berafiliasi</strong> dengan media mana pun. Atas izin pelapor (centang opsional saat melapor), <strong>data kendaraan</strong> — tanpa identitas pelapor — dapat kami sampaikan sebagai informasi kepada redaksi media untuk dicek. Keputusan menerbitkan sepenuhnya milik redaksi tersebut.</p>

<h2>3. Dasar pemrosesan</h2>
<ul>
	<li><strong>Persetujuan</strong> — kamu mencentang pernyataan sebelum mengirim laporan.</li>
	<li><strong>Kepentingan sah</strong> — membantu menemukan kendaraan yang hilang dan mencegah penipuan.</li>
</ul>

<h2>4. Layanan pihak ketiga</h2>
<ul>
	<li><strong>Google Sign-In</strong> — untuk masuk dengan akun Google.</li>
	<li><strong>OpenFreeMap / OpenStreetMap</strong> — penyedia data &amp; tile peta.</li>
	<li><strong>Photon (Komoot) &amp; Nominatim (OpenStreetMap)</strong> — pencarian nama jalan di form lapor.</li>
	<li><strong>Hostinger</strong> — penyedia hosting dan database.</li>
	<li><strong>Penyedia email</strong> — untuk mengirim notifikasi laporan dan info penemuan.</li>
	<li><strong>DeepSeek</strong> — membantu admin membaca <em>gambar infografis</em> dan teks berita saat mengisi laporan. Gambar yang diunggah admin dikirim ke layanan ini hanya ketika fitur itu dipakai, dan tidak disimpan di server kami; yang masuk database hanya datanya.</li>
</ul>
<p>Kami tidak menjual data ke pihak ketiga dan tidak memasang iklan.</p>

<h2>5. Berapa lama disimpan</h2>
<ul>
	<li>Laporan yang sudah lebih dari <strong>{$hari} hari</strong> otomatis dipindah ke status arsip dan tidak tampil di peta utama.</li>
	<li>Data kontak pelapor disimpan selama laporan masih aktif, dan dihapus atau dianonimkan paling lambat <strong>12 bulan</strong> setelah laporan tidak aktif.</li>
	<li>Log IP untuk pembatasan spam disimpan maksimal <strong>24 jam</strong>.</li>
</ul>

<h2>6. Hak kamu</h2>
<p>Kamu berhak meminta salinan, perbaikan, atau penghapusan data yang berkaitan denganmu. Caranya:</p>
<ul>
	<li>Klik tombol <strong>“Laporkan data ini”</strong> di halaman detail laporan, atau</li>
	<li>Kirim email ke <a href="mailto:{$email}">{$email}</a> dengan subjek <em>Permintaan Data</em>.</li>
</ul>
<p>Kami tanggapi paling lambat <strong>1×24 jam</strong> pada hari kerja. Kalau datamu memang keliru, laporan kami sembunyikan lebih dulu sambil diperiksa.</p>

<h2>7. Cookie</h2>
<p>Situs ini memakai cookie yang diperlukan (sesi login WordPress dan sesi akun Google) serta cookie analitik dari Google Analytics 4. Tidak ada cookie iklan.</p>

<h2>8. Anak-anak</h2>
<p>Situs ini ditujukan untuk pengguna berusia 18 tahun ke atas, atau di bawah pengawasan orang tua/wali.</p>

<h2>9. Perubahan</h2>
<p>Kebijakan ini bisa diperbarui. Perubahan penting akan dicantumkan di halaman ini beserta tanggal pembaruannya.</p>

<h2>Impor artikel &amp; gambar oleh admin</h2>
<p>Saat admin memasukkan laporan dari tautan berita atau dari <strong>gambar infografis/poster</strong>, judul &amp; isi artikel yang <strong>sudah publik</strong>, atau gambar yang diunggah admin, dikirim ke layanan AI (DeepSeek) untuk diubah menjadi data terstruktur (plat, model, lokasi, waktu). Yang disimpan di situs hanya hasil datanya — bukan teks artikel dan bukan gambarnya: berkas gambar tidak disimpan di server kami setelah dibaca. Bahan yang dikirim tidak dipakai untuk pelatihan model dan tidak disimpan di database kami.</p>

<p class="kh-legal-meta">Terakhir diperbarui: 11 November 2026.</p>
HTML,
		),

		'tentang'    => array(
			'title'   => 'Tentang Situs Ini',
			'excerpt' => 'Apa itu Peta Kendaraan Hilang, dari mana datanya, dan bagaimana laporan diverifikasi.',
			'content' => <<<HTML
<p><strong>Peta Kendaraan Hilang</strong> adalah peta terbuka yang menampilkan laporan kehilangan motor dan mobil di Jawa Timur — terutama Surabaya, Sidoarjo, dan sekitarnya — lengkap dengan nomor polisi agar warga bisa mengenali kendaraannya.</p>

<h2>Dari mana datanya?</h2>
<ul>
	<li><strong>Pemberitaan media</strong> — laporan yang sudah tayang di media (mis. Suara Surabaya), disertai tautan ke sumber aslinya.</li>
	<li><strong>Laporan warga</strong> — dikirim lewat situs ini dengan akun Google, lalu diperiksa admin sebelum tayang.</li>
</ul>

<h2>Status verifikasi</h2>
<table>
	<tbody>
		<tr><td><strong>Belum diverifikasi</strong></td><td>Laporan warga yang baru masuk dan sudah lolos pemeriksaan dasar. Tetap perlu dikonfirmasi sendiri.</td></tr>
		<tr><td><strong>Terverifikasi media</strong></td><td>Ada pemberitaan media yang bisa ditelusuri (tautan sumber tersedia di halaman laporan).</td></tr>
		<tr><td><strong>Sudah lapor polisi</strong></td><td>Pelapor mencantumkan nomor STTLP dan admin sudah memeriksanya.</td></tr>
		<tr><td><strong>Ditemukan</strong></td><td>Kendaraan sudah ditemukan atau perkara selesai.</td></tr>
	</tbody>
</table>

<h2>Yang perlu dipahami</h2>
<ul>
	<li>Situs ini <strong>bukan</strong> milik/kantor kepolisian dan tidak menerbitkan surat kehilangan.</li>
	<li>Kami tidak memungut biaya apa pun, dan <strong>tidak pernah</strong> meminta transfer uang.</li>
	<li>Kalau ada orang mengaku menemukan kendaraanmu lalu meminta biaya di muka: itu modus penipuan. Serah terima sebaiknya di kantor polisi.</li>
	<li>Laporan bisa saja keliru. Kalau kamu pemilik kendaraan yang disebut dan datanya salah, gunakan tombol <strong>“Laporkan data ini”</strong> — kami periksa dan sembunyikan dalam 1×24 jam.</li>
</ul>

<h2>Ingin ikut membantu?</h2>
<p>Kalau melihat kendaraan yang cocok dengan salah satu laporan, buka laporannya dan tekan <strong>“Saya menemukan kendaraan ini”</strong>. Info itu masuk ke admin dan pelapor — bukan ke kolom komentar publik.</p>

<p>Pertanyaan lain? Lihat <a href="/kontak/">halaman kontak</a>.</p>
HTML,
		),

		'kontak'     => array(
			'title'   => 'Kontak',
			'excerpt' => 'Cara menghubungi pengelola situs, termasuk permintaan penghapusan data.',
			'content' => <<<HTML
<p>Untuk pertanyaan, koreksi data, kerja sama, atau permintaan penghapusan laporan, hubungi:</p>

<p><strong>Email:</strong> <a href="mailto:{$email}">{$email}</a><br>
<strong>Subjek yang disarankan:</strong> Koreksi Data / Permintaan Hapus / Kerja Sama / Lainnya</p>

<h2>Kalau datanya keliru atau mengganggu</h2>
<p>Jangan menunggu balasan email — tekan tombol <strong>“Laporkan data ini”</strong> di halaman laporan yang bermasalah. Keberatan langsung masuk ke antrean admin dan kami upayakan tersembunyi dalam <strong>1×24 jam</strong>.</p>

<h2>Waktu respons</h2>
<p>Email dibalas pada hari kerja, umumnya dalam 1×24 jam. Laporan yang menyangkut keselamatan (mis. dugaan penguntitan) kami dahulukan.</p>

<h2>Bukan jalur darurat</h2>
<p>Situs ini bukan layanan darurat. Untuk kejadian yang sedang berlangsung atau tindak pidana, hubungi <strong>110</strong> atau kantor polisi terdekat.</p>
HTML,
		),

		'disclaimer' => array(
			'title'   => 'Disclaimer & Ketentuan',
			'excerpt' => 'Batasan tanggung jawab, aturan penggunaan, dan kebijakan penghapusan data.',
			'content' => <<<HTML
<h2>1. Bukan lembaga resmi</h2>
<p>Peta Kendaraan Hilang adalah inisiatif independen. Kami tidak berafiliasi dengan Kepolisian Negara Republik Indonesia, Samsat, maupun media mana pun. Data di sini bersifat informasi, bukan bukti hukum. Kami dapat menyampaikan informasi kepada redaksi media atas izin pelapor; itu bukan bentuk afiliasi, kerja sama, maupun dukungan.</p>

<h2>2. Akurasi data</h2>
<p>Sebagian laporan berasal dari warga dan belum tentu benar. Selalu cocokkan dengan sumber asli (tautan media atau laporan polisi/STTLP) sebelum mengambil keputusan.</p>

<h2>3. Nomor polisi ditampilkan lengkap</h2>
<p>Nomor polisi ditampilkan tanpa disensor agar warga bisa mengenali kendaraan dan melapor. Kalau kamu pemilik kendaraan yang disebut tetapi datanya salah — atau tidak ingin datanya tampil — gunakan tombol <strong>“Laporkan data ini”</strong>. Kami periksa dan sembunyikan paling lambat 1×24 jam.</p>

<h2>4. Larangan</h2>
<ul>
	<li>Mengirim laporan palsu atau sengaja menyesatkan.</li>
	<li>Memakai data di situs ini untuk mengintimidasi, menguntit, atau menagih utang.</li>
	<li>Meminta atau memberi imbalan uang atas penemuan kendaraan.</li>
	<li>Mengambil data secara massal (scraping) untuk tujuan komersial.</li>
</ul>
<p>Pelanggaran dapat berujung pada penghapusan data dan, bila perlu, pelaporan ke pihak berwenang sesuai UU ITE.</p>

<h2>5. Tanggung jawab pelapor</h2>
<p>Pengirim laporan menyatakan bahwa informasinya benar dan bersedia bertanggung jawab bila terbukti palsu. Ini disyaratkan lewat centang pernyataan sebelum laporan dikirim.</p>

<h2>6. Isi buatan pengguna (laporan warga)</h2>
<p>Laporan warga adalah isi buatan pengguna. Sebelum mengirim, pelapor wajib masuk dengan akun Google agar setiap laporan punya identitas yang bisa dihubungi. Dengan mengirim laporan, pelapor menyetujui bahwa isinya tidak palsu atau menyesatkan, tidak memuat fitnah, tidak mengandung kebencian atas dasar suku, agama, ras, dan antargolongan (SARA), tidak cabul, serta tidak merendahkan atau mendiskriminasi orang lain.</p>
<p>Kami berwenang menyunting atau menghapus laporan yang melanggar ketentuan tersebut. Pengaduan disampaikan lewat tombol <strong>“Laporkan data ini”</strong> di halaman laporan atau email ke <a href="mailto:business.refanoandreas@gmail.com">business.refanoandreas@gmail.com</a>; kami tindak lanjuti paling lambat <strong>1×24 jam</strong>.</p>

<h2>7. Tautan pihak ketiga</h2>
<p>Laporan yang dikutip dari media menyertakan tautan ke sumber aslinya. Kami tidak mengendalikan isi situs pihak ketiga tersebut.</p>

<h2>8. Hak cipta &amp; permintaan penghapusan</h2>
<p>Laporan di situs ini menyebut <strong>data faktual</strong> (nomor polisi, model, warna, waktu, lokasi) yang dikutip dari media atau dikirim pelapor. Yang dikutip hanya datanya — bukan desain, tata letak, foto, atau karya grafis milik media tersebut.</p>
<p>Hak cipta atas artikel dan foto tetap milik masing-masing pemiliknya. Situs ini tidak berafiliasi dengan Suara Surabaya maupun media lain, dan tidak mengklaim kepemilikan atas karya mereka. Pemegang hak cipta yang keberatan dapat menghubungi <a href="mailto:business.refanoandreas@gmail.com">business.refanoandreas@gmail.com</a> dengan subjek <em>Permintaan Hapus</em>; kami tindak lanjuti paling lambat <strong>1×24 jam</strong>.</p>

<h2>9. Perubahan ketentuan</h2>
<p>Ketentuan ini dapat diperbarui sewaktu-waktu. Versi terbaru selalu ada di halaman ini.</p>

<p class="kh-legal-meta">Terakhir diperbarui: 11 November 2026.</p>
HTML,
		),
	);
}

/**
 * Apakah halaman ini salah satu halaman informasi (legal) atau cek-status?
 */
function kh_is_info_page() {
	if ( ! is_page() ) {
		return false;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	$list = array_keys( kh_legal_pages() );
	$list[] = 'cek-status';
	return in_array( $slug, $list, true );
}

/**
 * Buat halaman sekali saja (jalan otomatis, tidak perlu aktivasi ulang).
 */
add_action( 'init', 'kh_seed_legal_pages' );
function kh_seed_legal_pages() {
	if ( get_option( 'kh_legal_seeded' ) ) {
		return;
	}
	update_option( 'kh_legal_seeded', 1 );

	foreach ( kh_legal_pages() as $slug => $data ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		wp_insert_post(
			array(
				'post_title'   => $data['title'],
				'post_name'    => $slug,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $data['content'],
				'post_excerpt' => $data['excerpt'],
			)
		);
	}
}

/**
 * Halaman legal memakai template gelap sendiri.
 */
add_filter( 'template_include', function ( $template ) {
	if ( ! is_page() ) {
		return $template;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	if ( ! array_key_exists( $slug, kh_legal_pages() ) ) {
		return $template;
	}
	$custom = KH_CORE_DIR . 'templates/page-dark.php';
	return file_exists( $custom ) ? $custom : $template;
}, 20 );

/**
 * Menu legal (dipakai di footer semua template).
 */
function kh_legal_nav() {
	$items = array( '/tentang/' => 'Tentang' );

	/* Hub wilayah: memberi setiap halaman jalan ke indeks wilayah. Hanya
	   ditautkan kalau halamannya sudah dibuat (inc/hub.php). */
	if ( get_option( 'kh_wilayah_page' ) ) {
		$items['/wilayah/'] = 'Wilayah';
	}

	$items['/panduan/']    = 'Panduan';
	$items['/analitik/']   = 'Statistik';
	$items['/privasi/']    = 'Kebijakan Privasi';
	$items['/disclaimer/'] = 'Disclaimer';
	$items['/kontak/']     = 'Kontak';

	echo '<nav class="kh-legal-nav">';
	foreach ( $items as $url => $label ) {
		printf( '<a href="%s">%s</a>', esc_url( home_url( $url ) ), esc_html( $label ) );
	}
	echo '</nav>';
}
