<?php
/**
 * Input cepat dari infografis / poster (banyak kendaraan sekaligus).
 *
 * Yang dipakai HANYA datanya (plat, model, tahun, warna, lokasi, waktu, polsek).
 * Desain/ekspresi sumber tidak pernah diambil.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ringkasan fakta hasil susun sendiri (bukan kutipan artikel).
 */
function kh_buat_ringkasan( $item, $tanggal = '' ) {
	$kendaraan = trim( (string) ( $item['merk'] ?? '' ) . ' ' . ( $item['model'] ?? '' ) );

	$bagian = array();
	$bagian[] = 'Hilang ' . ( $kendaraan ? $kendaraan : 'kendaraan' )
		. ( ! empty( $item['tahun'] ) ? ' ' . $item['tahun'] : '' )
		. ( ! empty( $item['warna'] ) ? ' warna ' . strtolower( $item['warna'] ) : '' )
		. ( ! empty( $item['plat'] ) ? ' (' . $item['plat'] . ')' : '' ) . '.';

	if ( ! empty( $item['lokasi'] ) ) {
		$bagian[] = 'Terakhir terlihat di ' . $item['lokasi'] . '.';
	}

	$waktu = '';
	if ( $tanggal ) {
		$waktu = date_i18n( 'j F Y', strtotime( $tanggal ) );
	}
	if ( ! empty( $item['jam'] ) ) {
		$waktu .= ( $waktu ? ' pukul ' : 'Pukul ' ) . $item['jam'] . ' WIB';
	}
	if ( $waktu ) {
		$bagian[] = 'Kejadian ' . trim( $waktu ) . '.';
	}

	if ( ! empty( $item['polsek'] ) ) {
		$bagian[] = 'Sudah dilaporkan ke ' . $item['polsek'] . '.';
	}

	$out = wp_strip_all_tags( implode( ' ', $bagian ) );
	$out = trim( preg_replace( '/\s+/', ' ', $out ) );

	return mb_substr( $out, 0, 300 );
}

/**
 * Kotak bantuan AI di atas tabel: unggah gambar infografis ATAU tempel teks.
 * Hasilnya TIDAK langsung disimpan — hanya mengisi tabel untuk diperiksa admin.
 */
function kh_bulk_ai_box() {
	$pesan = '';
	if ( isset( $_GET['kh_ai_msg'] ) ) {
		$pesan = sanitize_text_field( rawurldecode( (string) wp_unslash( $_GET['kh_ai_msg'] ) ) );
	}
	$ai_siap = kh_ai_enabled();
	?>
	<div class="card" style="max-width:1400px;padding:12px 16px;margin:16px 0">
		<h2 style="margin-top:0">Bantuan AI (opsional)</h2>
		<p style="max-width:80ch;margin-top:0">
			Unggah <strong>infografis/poster/screenshot</strong> atau tempel teksnya — AI akan membaca dan
			<strong>mengisi tabel di bawah</strong> supaya bisa kamu periksa dan koreksi dulu.
			Gambar dikirim sekali ke layanan AI (DeepSeek) untuk dibaca dan <em>tidak disimpan</em> di server;
			yang masuk database hanya datanya, desain sumber tidak pernah disalin.
		</p>
		<?php if ( $pesan ) : ?>
			<div class="notice notice-info inline"><p><?php echo esc_html( $pesan ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! $ai_siap ) : ?>
			<p><em>Kunci API AI belum diisi, jadi bantuan AI tidak aktif.
				Isi di <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=kendaraan&page=kh-settings' ) ); ?>">Pengaturan</a>.</em></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="kh_bulk_ai">
				<?php wp_nonce_field( 'kh_bulk_ai', 'kh_bulk_ai_nonce' ); ?>
				<p>
					<label for="kh_gambar"><strong>1. Unggah gambar</strong></label><br>
					<input type="file" id="kh_gambar" name="kh_gambar" accept="image/png,image/jpeg,image/webp,image/gif">
					<span class="description">PNG/JPG/WebP, maks 6 MB. Sekali unggah boleh memuat banyak kendaraan.</span>
				</p>
				<p>
					<label for="kh_teks"><strong>2. atau tempel teks</strong></label><br>
					<textarea id="kh_teks" name="kh_teks" rows="4" class="large-text code" placeholder="Tempel teks infografis / caption di sini (lebih murah, tanpa kirim gambar)."></textarea>
				</p>
				<p style="margin-bottom:0">
					<?php submit_button( 'Baca dengan AI → isi tabel', 'secondary', 'submit', false ); ?>
					<span class="description">Perkiraan: 2–4 detik, biaya beberapa rupiah per infografis.</span>
				</p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Satu baris tabel input. $v = nilai awal (kosong untuk baris kosong).
 */
function kh_bulk_row( $v, $wilayah ) {
	$plat    = isset( $v['plat'] ) ? $v['plat'] : '';
	$jenis   = isset( $v['jenis'] ) ? $v['jenis'] : 'Motor';
	$merk    = isset( $v['merk'] ) ? $v['merk'] : '';
	$model   = isset( $v['model'] ) ? $v['model'] : '';
	$tahun   = isset( $v['tahun'] ) ? $v['tahun'] : '';
	$warna   = isset( $v['warna'] ) ? $v['warna'] : '';
	$lokasi  = isset( $v['lokasi'] ) ? $v['lokasi'] : '';
	$wil     = isset( $v['wilayah'] ) ? $v['wilayah'] : '';
	$tanggal = isset( $v['tanggal'] ) ? $v['tanggal'] : '';
	$jam     = isset( $v['jam'] ) ? $v['jam'] : '';
	$polsek  = isset( $v['polsek'] ) ? $v['polsek'] : '';
	?>
	<tr>
		<td><input name="plat[]" class="regular-text" style="width:100%" placeholder="L 3502 CAU" value="<?php echo esc_attr( $plat ); ?>"></td>
		<td>
			<select name="jenis[]" style="width:100%">
				<option value="Motor" <?php selected( $jenis, 'Motor' ); ?>>Motor</option>
				<option value="Mobil" <?php selected( $jenis, 'Mobil' ); ?>>Mobil</option>
			</select>
		</td>
		<td><input name="merk[]" style="width:100%" placeholder="Honda" value="<?php echo esc_attr( $merk ); ?>"></td>
		<td><input name="model[]" style="width:100%" placeholder="Beat" value="<?php echo esc_attr( $model ); ?>"></td>
		<td><input name="tahun[]" style="width:100%" inputmode="numeric" placeholder="2019" value="<?php echo esc_attr( $tahun ); ?>"></td>
		<td><input name="warna[]" style="width:100%" placeholder="Putih" value="<?php echo esc_attr( $warna ); ?>"></td>
		<td><input name="lokasi[]" style="width:100%" placeholder="Minimarket Mulyosari, Surabaya" value="<?php echo esc_attr( $lokasi ); ?>"></td>
		<td>
			<select name="wilayah[]" style="width:100%">
				<option value="">—</option>
				<?php foreach ( $wilayah as $t ) : ?>
					<option value="<?php echo esc_attr( $t->name ); ?>" <?php selected( $wil, $t->name ); ?>><?php echo esc_html( $t->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><input type="date" name="tanggal[]" style="width:100%" value="<?php echo esc_attr( $tanggal ); ?>"></td>
		<td><input type="time" name="jam[]" style="width:100%" value="<?php echo esc_attr( $jam ); ?>"></td>
		<td><input name="polsek[]" style="width:100%" placeholder="Polres Sidoarjo" title="Boleh Polsek, Polres, Polresta, Polrestabes, atau Polda" value="<?php echo esc_attr( $polsek ); ?>"></td>
	</tr>
	<?php
}

/**
 * Hasil AI yang menunggu diperiksa (per admin, 15 menit).
 */
function kh_bulk_prefill( $hapus = false ) {
	$key = 'kh_ai_prefill_' . get_current_user_id();
	$isi = get_transient( $key );
	if ( $hapus ) {
		delete_transient( $key );
	}
	return is_array( $isi ) ? $isi : array();
}

/**
 * Proses "Baca dengan AI": gambar (vision) atau teks → isi tabel.
 */
add_action( 'admin_post_kh_bulk_ai', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Akses ditolak.' );
	}
	check_admin_referer( 'kh_bulk_ai', 'kh_bulk_ai_nonce' );

	$balik = admin_url( 'edit.php?post_type=kendaraan&page=kh-bulk' );

	if ( ! kh_ai_enabled() ) {
		wp_safe_redirect( add_query_arg( 'kh_ai_msg', rawurlencode( 'Kunci API AI belum diisi.' ), $balik ) );
		exit;
	}

	$catatan = isset( $_POST['kh_teks'] ) ? trim( (string) wp_unslash( $_POST['kh_teks'] ) ) : '';
	$hasil   = null;

	if ( ! empty( $_FILES['kh_gambar']['tmp_name'] ) && is_uploaded_file( $_FILES['kh_gambar']['tmp_name'] ) ) {
		$file = $_FILES['kh_gambar'];

		if ( (int) $file['size'] > 6 * MB_IN_BYTES ) {
			$hasil = new WP_Error( 'kh_ai', 'Gambar lebih dari 6 MB — kecilkan dulu.' );
		} elseif ( ! preg_match( '/^image\//', (string) $file['type'] ) ) {
			$hasil = new WP_Error( 'kh_ai', 'File bukan gambar.' );
		} else {
			$info = @getimagesize( $file['tmp_name'] );
			$mime = $info && ! empty( $info['mime'] ) ? $info['mime'] : '';
			if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' ), true ) ) {
				$hasil = new WP_Error( 'kh_ai', 'Format gambar tidak didukung (pakai PNG/JPG/WebP).' );
			} else {
				$b64   = base64_encode( (string) file_get_contents( $file['tmp_name'] ) );
				$hasil = kh_ai_extract_visi( $b64, $mime, $catatan );
			}
		}

		/* gambar tidak disimpan di server */
		@unlink( $file['tmp_name'] );
	} elseif ( '' !== $catatan ) {
		$hasil = kh_ai_extract_kendaraan( 'Infografis (teks)', $catatan );
	} else {
		$hasil = new WP_Error( 'kh_ai', 'Unggah gambar atau tempel teksnya dulu.' );
	}

	if ( is_wp_error( $hasil ) ) {
		wp_safe_redirect( add_query_arg( 'kh_ai_msg', rawurlencode( 'Gagal: ' . $hasil->get_error_message() ), $balik ) );
		exit;
	}

	$items = $hasil['items'];
	set_transient( 'kh_ai_prefill_' . get_current_user_id(), $items, 15 * MINUTE_IN_SECONDS );

	$pesan = sprintf(
		'AI membaca %d kendaraan. Periksa tabel di bawah, koreksi kalau ada yang salah, lalu klik "Buat laporan".',
		count( $items )
	);
	wp_safe_redirect( add_query_arg( 'kh_ai_msg', rawurlencode( $pesan ), $balik ) );
	exit;
} );
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=kendaraan',
		'Input dari Infografis',
		'Input dari Infografis',
		'edit_posts',
		'kh-bulk',
		'kh_bulk_page'
	);
} );

function kh_bulk_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Akses ditolak.' );
	}

	$wilayah = get_terms( array( 'taxonomy' => 'kendaraan_wilayah', 'hide_empty' => false ) );
	if ( is_wp_error( $wilayah ) ) {
		$wilayah = array();
	}
	?>
	<div class="wrap">
		<h1>Input dari Infografis / Poster</h1>
		<p style="max-width:70ch">
			Masukkan <strong>data</strong> dari infografis atau poster (plat, model, tahun, warna, lokasi, waktu, polisi).
			Tampilan desain sumber tidak disalin — situs ini membuat kartunya sendiri.
			Duplikat plat otomatis dilewati.
			<br>Kolom terakhir menerima <strong>Polsek, Polres, Polresta, Polrestabes, atau Polda</strong> —
			jenis unitnya dideteksi otomatis (nama wilayah saja dianggap Polsek).
			<br>Malas mengetik? Pakai <strong>Bantuan AI</strong> di bawah: unggah gambar infografisnya,
			AI mengisi tabel, kamu yang memutuskan sebelum simpan.
		</p>

		<?php if ( isset( $_GET['kh_bulk_ok'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php
			$ok    = absint( $_GET['kh_bulk_ok'] ); // phpcs:ignore WordPress.Security.NonceVerification
			$skip  = absint( $_GET['kh_bulk_skip'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			$nogeo = absint( $_GET['kh_bulk_nogeo'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php echo (int) $ok; ?> laporan dibuat.</strong>
					<?php if ( $skip ) : ?><?php echo (int) $skip; ?> dilewati karena plat sudah ada.<?php endif; ?>
					<?php if ( $nogeo ) : ?><?php echo (int) $nogeo; ?> belum punya titik peta (isi koordinat manual di editor).<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=kendaraan' ) ); ?>">Lihat daftar laporan</a>
				</p>
			</div>
		<?php endif; ?>

		<?php kh_bulk_ai_box(); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'kh_bulk_add', 'kh_bulk_nonce' ); ?>
			<input type="hidden" name="action" value="kh_bulk_add">

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kh_bulk_sumber">Sumber</label></th>
					<td>
						<input type="text" class="regular-text" id="kh_bulk_sumber" name="sumber_nama" value="Suara Surabaya FM 100">
						<p class="description">Nama media/sumber yang ditampilkan di halaman laporan.</p>
					</td>
				</tr>
				<tr>
					<th><label for="kh_bulk_url">URL sumber</label></th>
					<td>
						<input type="url" class="regular-text" id="kh_bulk_url" name="sumber_url" placeholder="https://www.suarasurabaya.net/...">
						<p class="description">Opsional. Dipakai sebagai tautan balik ke artikel/posting aslinya.</p>
					</td>
				</tr>
			</table>

			<h2>Daftar kendaraan</h2>
			<table class="widefat striped" id="kh-bulk-table" style="max-width:1400px">
				<thead>
					<tr>
						<th style="width:110px">Plat *</th>
						<th style="width:90px">Jenis</th>
						<th style="width:90px">Merk</th>
						<th style="width:100px">Model</th>
						<th style="width:70px">Tahun</th>
						<th style="width:110px">Warna</th>
						<th>Lokasi</th>
						<th style="width:130px">Wilayah</th>
						<th style="width:130px">Tanggal</th>
						<th style="width:90px">Jam</th>
						<th style="width:170px">Polsek / Polres / Polda</th>
					</tr>
				</thead>
				<tbody>
				<?php
				$prefill = kh_bulk_prefill();
				$baris   = $prefill ? $prefill : array();
				$total   = max( 4, count( $baris ) + ( $baris ? 1 : 0 ) );
				for ( $i = 0; $i < $total; $i++ ) {
					kh_bulk_row( isset( $baris[ $i ] ) ? $baris[ $i ] : array(), $wilayah );
				}
				?>
				</tbody>
			</table>

			<p style="margin-top:14px">
				<button type="button" class="button" id="kh-bulk-add-row">+ Tambah baris</button>
			</p>

			<?php submit_button( 'Buat laporan dari data ini', 'primary', 'submit', false ); ?>
			<p class="description" style="margin-top:8px">
				Laporan dibuat sebagai <strong>tayang</strong> dengan status verifikasi <em>Terverifikasi media</em>.
				Koordinat diambil otomatis dari nama lokasi; kalau gagal, titik peta bisa diisi manual di editor.
			</p>
		</form>
	</div>

	<script>
	document.getElementById('kh-bulk-add-row').addEventListener('click', function () {
		var body = document.querySelector('#kh-bulk-table tbody');
		var row = body.rows[0].cloneNode(true);
		row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
		body.appendChild(row);
	});
	</script>
	<?php
}

/**
 * Proses simpan.
 */
add_action( 'admin_post_kh_bulk_add', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Akses ditolak.' );
	}
	check_admin_referer( 'kh_bulk_add', 'kh_bulk_nonce' );

	$plat_arr = isset( $_POST['plat'] ) ? (array) wp_unslash( $_POST['plat'] ) : array();
	$get      = function ( $key, $i, $default = '' ) {
		$arr = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array();
		$val = isset( $arr[ $i ] ) ? $arr[ $i ] : $default;
		return $val;
	};

	$sumber_nama = sanitize_text_field( wp_unslash( $_POST['sumber_nama'] ?? 'Suara Surabaya FM 100' ) );
	$sumber_url  = esc_url_raw( wp_unslash( $_POST['sumber_url'] ?? '' ) );

	$dibuat = 0;
	$lewat  = 0;
	$nogeo  = 0;

	foreach ( $plat_arr as $i => $raw ) {
		$plat = kh_normalize_plat( $raw );
		if ( strlen( $plat ) < 4 ) {
			continue;
		}

		/* duplikat? */
		$ada = get_posts(
			array(
				'post_type'      => 'kendaraan',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => 'kh_plat',
						'value' => $plat,
					),
				),
			)
		);
		if ( $ada ) {
			$lewat++;
			continue;
		}

		$item = array(
			'plat'   => $plat,
			'jenis'  => sanitize_text_field( $get( 'jenis', $i, 'Motor' ) ),
			'merk'   => sanitize_text_field( $get( 'merk', $i ) ),
			'model'  => sanitize_text_field( $get( 'model', $i ) ),
			'tahun'  => sanitize_text_field( $get( 'tahun', $i ) ),
			'warna'  => sanitize_text_field( $get( 'warna', $i ) ),
			'lokasi' => sanitize_text_field( $get( 'lokasi', $i ) ),
		);

		$wilayah = sanitize_text_field( $get( 'wilayah', $i ) );
		$tanggal = sanitize_text_field( $get( 'tanggal', $i ) );
		$jam     = sanitize_text_field( $get( 'jam', $i ) );
		$polsek_raw = sanitize_text_field( $get( 'polsek', $i ) );
		$unit_info  = kh_deteksi_unit( $polsek_raw );
		$polsek     = $unit_info['nama'];

		/* koordinat dari nama lokasi */
		$lat = '';
		$lng = '';
		if ( $item['lokasi'] ) {
			$geo = kh_geocode_lokasi( $item['lokasi'], $wilayah );
			if ( $geo ) {
				$lat = $geo[0];
				$lng = $geo[1];
			}
		}
		if ( ! $lat ) {
			$nogeo++;
		}

		$judul = 'Hilang ' . trim( $item['merk'] . ' ' . $item['model'] . ( $item['tahun'] ? ' ' . $item['tahun'] : '' ) )
			. ' di ' . ( $item['lokasi'] ? $item['lokasi'] : $wilayah );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'kendaraan',
				'post_status'  => 'publish',
				'post_title'   => $judul,
				'post_content' => kh_buat_ringkasan( $item, $tanggal ),
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}

		kh_save_meta_values(
			$post_id,
			array(
				'plat'        => $item['plat'],
				'merk'        => $item['merk'],
				'model'       => $item['model'],
				'tahun'       => $item['tahun'],
				'warna'       => $item['warna'],
				'tanggal'     => $tanggal,
				'jam'         => $jam,
				'lokasi'      => $item['lokasi'],
				'lat'         => $lat,
				'lng'         => $lng,
				'polsek'      => $polsek,
				'unit'        => $unit_info['level'],
				'sumber_nama' => $sumber_nama,
				'sumber_url'  => $sumber_url,
			)
		);

		update_post_meta( $post_id, 'kh_verifikasi', 'media' );

		if ( ! empty( $item['jenis'] ) ) {
			wp_set_object_terms( $post_id, $item['jenis'], 'kendaraan_jenis' );
		}
		if ( $wilayah ) {
			wp_set_object_terms( $post_id, $wilayah, 'kendaraan_wilayah' );
		}
		wp_set_object_terms( $post_id, 'Hilang', 'kendaraan_status' );

		$dibuat++;
	}

	/* hasil AI sudah dipakai — jangan muncul lagi di kunjungan berikutnya */
	kh_bulk_prefill( true );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'         => 'kh-bulk',
				'kh_bulk_ok'   => $dibuat,
				'kh_bulk_skip' => $lewat,
				'kh_bulk_nogeo' => $nogeo,
			),
			admin_url( 'edit.php?post_type=kendaraan' )
		)
	);
	exit;
} );
