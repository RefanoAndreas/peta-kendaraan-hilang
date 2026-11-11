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
 * Menu admin.
 */
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
						<th style="width:150px">Polsek</th>
					</tr>
				</thead>
				<tbody>
				<?php for ( $i = 0; $i < 4; $i++ ) : ?>
					<tr>
						<td><input name="plat[]" class="regular-text" style="width:100%" placeholder="L 3502 CAU"></td>
						<td>
							<select name="jenis[]" style="width:100%">
								<option value="Motor">Motor</option>
								<option value="Mobil">Mobil</option>
							</select>
						</td>
						<td><input name="merk[]" style="width:100%" placeholder="Honda"></td>
						<td><input name="model[]" style="width:100%" placeholder="Beat"></td>
						<td><input name="tahun[]" style="width:100%" inputmode="numeric" placeholder="2019"></td>
						<td><input name="warna[]" style="width:100%" placeholder="Putih"></td>
						<td><input name="lokasi[]" style="width:100%" placeholder="Minimarket Mulyosari, Surabaya"></td>
						<td>
							<select name="wilayah[]" style="width:100%">
								<option value="">—</option>
								<?php foreach ( $wilayah as $t ) : ?>
									<option value="<?php echo esc_attr( $t->name ); ?>"><?php echo esc_html( $t->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="date" name="tanggal[]" style="width:100%"></td>
						<td><input type="time" name="jam[]" style="width:100%"></td>
						<td><input name="polsek[]" style="width:100%" placeholder="Polsek Menganti"></td>
					</tr>
				<?php endfor; ?>
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
		$polsek  = sanitize_text_field( $get( 'polsek', $i ) );

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
