<?php
/**
 * Panduan Admin — halaman dokumentasi internal.
 *
 * Hanya untuk administrator (capability `manage_options`). Isinya langkah kerja
 * sehari-hari, arti tiap level verifikasi, koreksi/hak jawab, pemantau sumber,
 * status integrasi, troubleshooting, dan checklist sebelum rilis.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=kendaraan',
		'Panduan Admin',
		'Panduan Admin',
		'manage_options',
		'kh-panduan',
		'kh_panduan_page'
	);
} );

/**
 * Angka ringkas untuk bagian atas halaman.
 */
function kh_panduan_statistik() {
	$hitung = function ( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_type'      => 'kendaraan',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$q = new WP_Query( $args );
		return (int) $q->found_posts;
	};

	$level = array( 'warga' => 0, 'media' => 0, 'polisi' => 0 );
	$ids   = get_posts(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'fields'         => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		$l = get_post_meta( $id, 'kh_verifikasi', true );
		$l = isset( $level[ $l ] ) ? $l : 'warga';
		$level[ $l ]++;
	}

	return array(
		'tayang'   => $hitung( array( 'post_status' => 'publish' ) ),
		'pending'  => $hitung( array( 'post_status' => 'pending' ) ),
		'draft'    => $hitung( array( 'post_status' => 'draft' ) ),
		'sumber'   => $hitung(
			array(
				'post_status' => 'any',
				'meta_query'  => array( array( 'key' => 'kh_sumber_status', 'value' => 'hilang' ) ),
			)
		),
		'level'    => $level,
	);
}

/**
 * Label status integrasi (terisi / kosong).
 */
function kh_panduan_status_integrasi() {
	return array(
		'Google OAuth Client ID' => array( get_option( 'kh_google_client_id', '' ), 'dipakai tombol "Masuk dengan Google" di halaman lapor' ),
		'DeepSeek API key'       => array( get_option( 'kh_ai_key', '' ), 'importer URL & bantuan AI infografis' ),
		'Model AI'               => array( kh_ai_model(), 'harus mendukung gambar (vision) untuk infografis' ),
		'Telegram bot token'     => array( get_option( 'kh_tg_token', '' ), 'notifikasi laporan baru & info temuan' ),
		'Telegram chat ID'       => array( get_option( 'kh_tg_chat', '' ), 'tujuan pesan bot' ),
		'Email notifikasi'       => array( get_option( 'kh_notif_email', '' ), 'penerima email admin + peringatan sumber hilang' ),
		'GA4 Measurement ID'     => array( get_option( 'kh_ga_id', '' ), 'analytics, dimuat setelah interaksi/idle 8 detik' ),
	);
}

function kh_panduan_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Halaman ini hanya untuk administrator.' );
	}

	$st       = kh_panduan_statistik();
	$integrasi = kh_panduan_status_integrasi();
	$arsip    = defined( 'KH_ARSIP_HARI' ) ? (int) KH_ARSIP_HARI : 180;
	$cron_ok  = (bool) wp_next_scheduled( 'kh_harian_sumber' );
	$cron_tgl = $cron_ok ? wp_date( 'j F Y, H:i', wp_next_scheduled( 'kh_harian_sumber' ) ) : '';
	?>
	<div class="wrap">
		<h1>Panduan Admin</h1>
		<p style="max-width:80ch">
			Halaman ini hanya terlihat oleh administrator. Isinya cara kerja harian situs
			<strong>Peta Kendaraan Hilang</strong> — dari menambah laporan sampai menangani koreksi.
			Versi plugin: <code><?php echo esc_html( defined( 'KH_CORE_VERSION' ) ? KH_CORE_VERSION : '?' ); ?></code>.
		</p>

		<h2>1. Ringkasan hari ini</h2>
		<table class="widefat striped" style="max-width:760px">
			<tbody>
				<tr><td>Laporan tayang</td><td><strong><?php echo (int) $st['tayang']; ?></strong></td></tr>
				<tr><td>Menunggu persetujuan (pending)</td><td><strong><?php echo (int) $st['pending']; ?></strong></td></tr>
				<tr><td>Draft</td><td><strong><?php echo (int) $st['draft']; ?></strong></td></tr>
				<tr><td>Level verifikasi (tayang)</td>
					<td>belum diverifikasi <strong><?php echo (int) $st['level']['warga']; ?></strong> ·
						terverifikasi media <strong><?php echo (int) $st['level']['media']; ?></strong> ·
						sudah lapor polisi <strong><?php echo (int) $st['level']['polisi']; ?></strong></td></tr>
				<tr><td>Sumber tidak ditemukan</td>
					<td><strong><?php echo (int) $st['sumber']; ?></strong>
						<?php if ( $st['sumber'] ) : ?>
							— <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=kendaraan&kh_sumber_hilang=1' ) ); ?>">lihat daftarnya</a>
						<?php endif; ?></td></tr>
				<tr><td>Ambang arsip</td><td>laporan lebih dari <strong><?php echo (int) $arsip; ?> hari</strong> otomatis tidak tampil di peta utama (<code>KH_ARSIP_HARI</code>)</td></tr>
				<tr><td>Pemantau sumber</td><td><?php echo $cron_ok ? 'terjadwal: <strong>' . esc_html( $cron_tgl ) . '</strong>' : '<strong style="color:#b32d2e">belum terjadwal</strong>'; ?></td></tr>
			</tbody>
		</table>

		<h2>2. Alur kerja harian</h2>
		<ol style="max-width:80ch">
			<li><strong>Cek laporan masuk.</strong> Menu <em>Kendaraan Hilang</em> → lihat tab <strong>Pending</strong> dan
				<strong>Perlu ditinjau &gt;24 jam</strong>. Laporan warga masuk sebagai pending (belum tayang).</li>
			<li><strong>Periksa isinya.</strong> Cocokkan dengan sumber (artikel/postingan/infografis), cek nomor polisi,
				lokasi, dan waktu. Klik <em>Edit</em> kalau ada yang perlu diperbaiki — setiap perubahan tercatat otomatis
				sebagai koreksi (lihat bagian 5).</li>
			<li><strong>Tayangkan.</strong> Per baris: <em>Setujui &amp; tayangkan</em>; atau centang beberapa baris →
				Bulk actions → <em>Setujui &amp; tayangkan</em>.</li>
			<li><strong>Naikkan level verifikasi.</strong> Per baris: <em>Tandai terverifikasi media</em> /
				<em>Tandai lapor polisi</em>; massal lewat Bulk actions. Impor dari URL otomatis <em>media</em>;
				Input dari Infografis (termasuk hasil AI) sengaja <em>belum diverifikasi</em> sampai kamu cek.</li>
			<li><strong>Tangani koreksi/keberatan.</strong> Kalau ada pemilik/penyedia data yang mengoreksi, perbaiki datanya
				(tercatat otomatis) atau sembunyikan laporannya (status → Draft/Private).</li>
		</ol>

		<h2>3. Cara menambah laporan</h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th style="width:230px">Jalur</th><th>Dipakai untuk</th><th style="width:120px">Level awal</th></tr></thead>
			<tbody>
				<tr><td><strong>Tambah Laporan Kendaraan</strong> (editor manual)</td>
					<td>Satu laporan, data lengkap, sekaligus titik peta (pilih di peta admin / isi koordinat).</td>
					<td>sesuai pilihan di panel Verifikasi</td></tr>
				<tr><td><strong>Import dari URL</strong></td>
					<td>Artikel berita (mis. Suara Surabaya). AI membaca judul + isi, membuat <strong>draft</strong>,
						koordinat dicari otomatis. Periksa sebelum tayang.</td>
					<td>terverifikasi media</td></tr>
				<tr><td><strong>Input dari Infografis</strong></td>
					<td>Banyak kendaraan sekaligus. Pakai <strong>Bantuan AI</strong>: unggah gambar infografis
						(PNG/JPG/WebP) <em>atau</em> tempel teksnya → AI mengisi tabel, kamu koreksi, lalu simpan.
						Gambar tidak disimpan di server.</td>
					<td>belum diverifikasi</td></tr>
				<tr><td><strong>Halaman /lapor/</strong> (publik)</td>
					<td>Warga mengirim sendiri (wajib login Google, boleh unggah foto, STTLP opsional).
						Masuk sebagai <strong>pending</strong>.</td>
					<td>belum diverifikasi</td></tr>
			</tbody>
		</table>

		<h2>4. Arti level verifikasi</h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th style="width:200px">Level</th><th style="width:110px">Warna marker</th><th>Kapan dipakai</th></tr></thead>
			<tbody>
				<tr><td>Belum diverifikasi</td><td>kuning</td><td>Laporan warga / hasil AI yang belum dicocokkan ke sumber.</td></tr>
				<tr><td>Terverifikasi media</td><td>biru</td><td>Datanya tertulis di pemberitaan/media, ada tautan sumbernya.</td></tr>
				<tr><td>Sudah lapor polisi</td><td>teal</td><td>Ada bukti laporan kepolisian (STTLP) atau disebut jelas di sumber.</td></tr>
				<tr><td>Status “Ditemukan” (terpisah dari level)</td><td>violet</td><td>Kendaraan sudah ditemukan — pakai aksi <em>Tandai ditemukan</em>.</td></tr>
			</tbody>
		</table>

		<h2>5. Koreksi &amp; hak jawab</h2>
		<p style="max-width:80ch">
			Setiap perubahan pada <strong>nomor polisi, merek, model, tahun, warna, lokasi, tanggal, jam, unit polisi,
			dan level verifikasi</strong> dicatat otomatis beserta waktu dan nilai lama/barunya. Catatan terakhir
			ditampilkan di halaman detail publik sebagai <em>“Pernah dikoreksi: &lt;tanggal&gt; — lokasi diperbarui”</em>.
			Menyimpan ulang tanpa mengubah apa pun <strong>tidak</strong> membuat catatan.
		</p>
		<p style="max-width:80ch">
			Pemilik kendaraan atau pihak yang keberatan memakai tombol <em>“Laporkan data ini”</em> di halaman detail
			(atau email ke alamat notifikasi). Target tindak lanjut: <strong>1×24 jam</strong> — sembunyikan dulu
			laporannya sambil diperiksa kalau datanya diragukan.
		</p>

		<h3 style="margin-bottom:6px">Info penemuan dari pengunjung</h3>
		<p style="max-width:80ch">
			Formulir <em>“Saya menemukan kendaraan ini”</em> mewajibkan <strong>nomor WA/email</strong> atau
			<strong>login Google</strong>. Panel <em>“Info penemuan masuk”</em> di halaman edit menampilkan tiap kiriman
			beserta status identitasnya:
			<strong>terverifikasi</strong> (lewat Google — otomatis diteruskan ke pelapor) atau
			<strong>belum terverifikasi</strong> (hanya tersimpan + email ke admin, <em>tidak</em> diteruskan).
			Untuk yang belum terverifikasi: hubungi kontaknya dulu, pastikan, baru teruskan sendiri.
			Jangan pernah meminta atau menerima uang — arahkan serah terima ke kantor polisi.
		</p>

		<h2>6. Pemantau artikel sumber</h2>
		<p style="max-width:80ch">
			Setiap hari sistem memeriksa tautan sumber laporan (maksimal 20 per putaran, yang paling lama tidak dicek
			duluan). Kalau artikelnya hilang (404/410) — misalnya dicabut redaksi — admin dikirimi <strong>satu email</strong>
			dan laporannya masuk tab <em>“Sumber tidak ditemukan”</em>. Tidak ada penyembunyian otomatis: kamu yang memutuskan
			(perbaiki data, ganti sumber, atau sembunyikan laporan). Jalankan manual lewat tombol
			<strong>“cek sumber hari ini”</strong> di atas daftar laporan.
		</p>

		<h2>7. Pengaturan &amp; status integrasi</h2>
		<p>
			Semua diatur di <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=kendaraan&page=kh-settings' ) ); ?>"><strong>Pengaturan</strong></a>.
			Status saat ini:
		</p>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th style="width:230px">Item</th><th style="width:110px">Status</th><th>Dipakai untuk</th></tr></thead>
			<tbody>
				<?php foreach ( $integrasi as $nama => $info ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $nama ); ?></strong></td>
						<td>
							<?php if ( '' !== trim( (string) $info[0] ) ) : ?>
								<span style="color:#00794a;font-weight:600">terisi</span>
							<?php else : ?>
								<span style="color:#b32d2e;font-weight:600">kosong</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $info[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2>8. Aturan internal yang perlu diketahui</h2>
		<ul style="max-width:85ch">
			<li><strong>Alamat laporan memakai nomor polisi</strong> — contoh <code>/kendaraan/l-4137-cam-honda-beat-2023/</code>.
				Slug dibuat sekali saat laporan dibuat; kalau nomor polisi dikoreksi, alamat lama tetap berlaku (dialihkan 301
				ke yang baru) supaya tautan yang sudah tersebar tidak mati.</li>
			<li><strong>Lingkaran 1 km</strong> di peta menandai <em>area perkiraan</em>: koordinat dari berita bukan lokasi
				pasti. Lingkaran hanya muncul untuk laporan yang sedang dipilih, di peta utama maupun peta mini halaman detail.</li>
			<li><strong>Arsip <?php echo (int) $arsip; ?> hari</strong> — laporan lama otomatis keluar dari peta utama, kecuali
				centang “tampilkan arsip”. Ubah ambang hanya lewat konstanta <code>KH_ARSIP_HARI</code>.</li>
			<li><strong>Nomor polisi ditampilkan penuh</strong> (tanpa sensor) — keputusan produk agar warga bisa mengenali
				kendaraan; keberatan pemilik ditangani lewat tombol “Laporkan data ini”.</li>
			<li><strong>Halaman legal</strong> (/privasi/, /disclaimer/, /tentang/, /kontak/) diedit seperti halaman biasa di
				wp-admin. Kalau sebuah layanan baru dikirim datanya ke pihak ketiga, perbarui halaman privasi saat itu juga.</li>
		</ul>

		<h2>9. Kalau ada masalah</h2>
		<table class="widefat striped" style="max-width:980px">
			<thead><tr><th style="width:280px">Gejala</th><th>Sebab &amp; tindakan</th></tr></thead>
			<tbody>
				<tr><td>Laporan ada tapi tidak muncul di peta</td>
					<td>Cek umurnya — laporan &gt;<?php echo (int) $arsip; ?> hari masuk arsip. Cek juga level/“Ditemukan”, atau pastikan koordinatnya terisi.</td></tr>
				<tr><td>Tanggal kejadian kosong di editor</td>
					<td>Seharusnya sudah beres sejak v0.26 (penyebabnya setelan format tanggal). Kalau muncul lagi, laporkan ke developer.</td></tr>
				<tr><td>Import AI: “Jawaban AI kosong”</td>
					<td>Cek API key &amp; kuota DeepSeek. Kalau teks artikel terlalu panjang, importer otomatis mencoba ulang dengan teks lebih pendek.</td></tr>
				<tr><td>Bantuan AI infografis: “File bukan gambar”</td>
					<td>Format harus PNG/JPG/WebP (maks 6 MB). Kalau gambar buram, hasil plat bisa keliru — selalu cocokkan.</td></tr>
				<tr><td>Tab “Sumber tidak ditemukan” bertambah</td>
					<td>Artikel sumber dicabut/dipindah. Buka artikelnya dulu; kalau memang dicabut, perbaiki/koreksi atau sembunyikan laporan.</td></tr>
				<tr><td>Laporan warga tidak bisa dikirim</td>
					<td>Pelapor harus login Google dan mencentang pernyataan. Cek juga Google OAuth Client ID masih terisi.</td></tr>
			</tbody>
		</table>

		<h2>10. Checklist sebelum rilis</h2>
		<ul style="max-width:85ch">
			<li>Hapus data uji (atau pastikan memang data nyata).</li>
			<li>Isi data produksi: impor artikel/infografis, lalu cocokkan koordinat tiap laporan di editor.</li>
			<li>Publish aplikasi Google OAuth supaya pelapor tidak melihat peringatan “aplikasi belum diverifikasi”.</li>
			<li>Pastikan email notifikasi &amp; Telegram sudah menerima pesan uji.</li>
			<li>Buka situs sebagai pengunjung biasa (bukan admin): peta, filter, halaman detail, form lapor, halaman legal.</li>
		</ul>

		<p style="margin-top:24px;color:#646970">
			Halaman ini hanya untuk administrator situs. Butuh perubahan alur? Hubungi developer
			(DEV-PARTNER) atau lihat <code>HANDOFF.md</code> di folder proyek.
		</p>
	</div>
	<?php
}
