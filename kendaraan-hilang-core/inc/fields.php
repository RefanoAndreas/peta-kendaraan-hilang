<?php
/**
 * Custom fields (meta box) + helper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'kh_register_cpt' );
add_action( 'init', 'kh_register_taxonomies' );

/**
 * Definisi field.
 */
function kh_meta_fields() {
	return array(
		'plat'         => array( 'label' => 'Nomor Polisi (lengkap)', 'type' => 'text', 'hint' => 'Contoh: L 3183 DAC — ditampilkan penuh di situs agar warga bisa mengenali dan melapor' ),
		'merk'         => array( 'label' => 'Merk', 'type' => 'text', 'hint' => 'Honda, Yamaha, Suzuki, ...' ),
		'model'        => array( 'label' => 'Model', 'type' => 'text', 'hint' => 'Beat, Vario, Scoopy, ...' ),
		'tahun'        => array( 'label' => 'Tahun', 'type' => 'text' ),
		'warna'        => array( 'label' => 'Warna / ciri', 'type' => 'text' ),
		'ciri'         => array( 'label' => 'Ciri khusus', 'type' => 'textarea' ),
		'tanggal'      => array( 'label' => 'Tanggal kejadian', 'type' => 'date' ),
		'jam'          => array( 'label' => 'Jam (WIB)', 'type' => 'time' ),
		'lokasi'       => array( 'label' => 'Lokasi kejadian', 'type' => 'text', 'hint' => 'Tulis level area/kelurahan, bukan alamat rumah lengkap' ),
		'lat'          => array( 'label' => 'Latitude', 'type' => 'text' ),
		'lng'          => array( 'label' => 'Longitude', 'type' => 'text' ),
		'polsek'       => array( 'label' => 'Lapor ke', 'type' => 'text', 'hint' => 'Contoh: Polsek Sedati' ),
		'sttlp'        => array( 'label' => 'Nomor STTLP', 'type' => 'text', 'hint' => 'Nomor surat tanda terima laporan polisi (opsional)' ),
		'verifikasi'   => array( 'label' => 'Status verifikasi', 'type' => 'text', 'hint' => 'warga | media | polisi' ),
		'sumber_nama'  => array( 'label' => 'Sumber', 'type' => 'text', 'hint' => 'Contoh: Suara Surabaya FM 100' ),
		'sumber_url'   => array( 'label' => 'URL sumber', 'type' => 'url' ),
	);
}

/**
 * Meta box.
 */
add_action( 'add_meta_boxes', 'kh_add_meta_box' );
function kh_add_meta_box() {
	// Kalau ACF aktif, field dikelola ACF (inc/acf.php) — jangan tampilkan meta box native.
	if ( function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	add_meta_box( 'kh_detail', 'Detail Kendaraan', 'kh_render_meta_box', 'kendaraan', 'normal', 'high' );
}

function kh_render_meta_box( $post ) {
	wp_nonce_field( 'kh_save_detail', 'kh_nonce' );

	echo '<table class="form-table"><tbody>';
	foreach ( kh_meta_fields() as $key => $conf ) {
		$value = get_post_meta( $post->ID, 'kh_' . $key, true );
		echo '<tr><th style="width:180px"><label for="kh_' . esc_attr( $key ) . '">' . esc_html( $conf['label'] ) . '</label></th><td>';
		if ( 'textarea' === $conf['type'] ) {
			echo '<textarea id="kh_' . esc_attr( $key ) . '" name="kh_' . esc_attr( $key ) . '" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input type="' . esc_attr( $conf['type'] ) . '" id="kh_' . esc_attr( $key ) . '" name="kh_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
		}
		if ( ! empty( $conf['hint'] ) ) {
			echo '<p class="description">' . esc_html( $conf['hint'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '<tr><th>Pilih titik di peta</th><td><div id="kh-picker" style="height:320px;border:1px solid #ccd0d4;border-radius:4px"></div><p class="description">Klik peta untuk mengisi Latitude/Longitude. Kalau kosong, pilih perkiraan lokasi kejadian.</p></td></tr>';
	echo '</tbody></table>';
}

/**
 * Simpan meta.
 */
add_action( 'save_post_kendaraan', 'kh_save_meta_box', 10, 2 );
function kh_save_meta_box( $post_id, $post ) {
	if ( ! isset( $_POST['kh_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['kh_nonce'] ) ), 'kh_save_detail' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$data = array();
	foreach ( kh_meta_fields() as $key => $conf ) {
		$raw = isset( $_POST[ 'kh_' . $key ] ) ? wp_unslash( $_POST[ 'kh_' . $key ] ) : '';
		$data[ $key ] = ( 'url' === $conf['type'] ) ? esc_url_raw( $raw ) : sanitize_text_field( $raw );
	}
	kh_save_meta_values( $post_id, $data );
}

/**
 * Simpan seluruh nilai meta + hitung plat yang di-mask.
 */
function kh_save_meta_values( $post_id, $data ) {
	foreach ( $data as $key => $value ) {
		if ( in_array( $key, array( 'jenis', 'wilayah' ), true ) ) {
			continue;
		}
		update_post_meta( $post_id, 'kh_' . $key, $value );
	}
	$plat = isset( $data['plat'] ) ? kh_normalize_plat( $data['plat'] ) : '';
	update_post_meta( $post_id, 'kh_plat', $plat );
	update_post_meta( $post_id, 'kh_plat_masked', kh_mask_plat( $plat ) );
}

/**
 * Level verifikasi laporan.
 */
function kh_verifikasi_levels() {
	return array(
		'warga'  => 'Belum diverifikasi',
		'media'  => 'Terverifikasi media',
		'polisi' => 'Sudah lapor polisi',
	);
}

function kh_verifikasi_of( $post_id ) {
	$v = get_post_meta( $post_id, 'kh_verifikasi', true );
	return isset( kh_verifikasi_levels()[ $v ] ) ? $v : 'warga';
}

/**
 * Normalisasi tanggal: ACF bisa menyimpan Ymd (20261109) atau Y-m-d.
 */
function kh_normalize_tanggal( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( preg_match( '/^\d{8}$/', $value ) ) {
		return substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 );
	}
	return $value;
}

function kh_normalize_plat( $plat ) {
	$plat = strtoupper( trim( (string) $plat ) );
	$plat = preg_replace( '/[^A-Z0-9 ]/', '', $plat );
	return preg_replace( '/\s+/', ' ', $plat );
}

/**
 * Nomor polisi ditampilkan PENUH (mengikuti media: agar warga bisa mengenali
 * kendaraan dan melapor kalau melihatnya). Fungsi dipertahankan agar
 * pemanggil lama tetap jalan.
 */
function kh_mask_plat( $plat ) {
	return kh_normalize_plat( $plat );
}

/**
 * Kolom admin.
 */
add_filter( 'manage_kendaraan_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['kh_plat']     = 'Nomor Polisi';
			$new['kh_verif']    = 'Verifikasi';
			$new['kh_tanggal']  = 'Tanggal';
			$new['kh_lokasi']   = 'Lokasi';
			$new['kh_titik']    = 'Titik peta';
			$new['kh_pelapor']  = 'Pelapor';
		}
	}
	return $new;
} );

add_action( 'manage_kendaraan_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'kh_plat':
			echo esc_html( get_post_meta( $post_id, 'kh_plat', true ) );
			break;
		case 'kh_tanggal':
			$t = get_post_meta( $post_id, 'kh_tanggal', true );
			$j = get_post_meta( $post_id, 'kh_jam', true );
			echo esc_html( trim( $t . ' ' . $j ) );
			break;
		case 'kh_lokasi':
			echo esc_html( get_post_meta( $post_id, 'kh_lokasi', true ) );
			break;
		case 'kh_titik':
			$lat = get_post_meta( $post_id, 'kh_lat', true );
			$lng = get_post_meta( $post_id, 'kh_lng', true );
			echo ( '' !== $lat && '' !== $lng ) ? '<span style="color:#2271b1">✓</span>' : '<span style="color:#d63638">—</span>';
			break;
		case 'kh_verif':
			$v     = kh_verifikasi_of( $post_id );
			$labels = kh_verifikasi_levels();
			$warna  = array( 'warga' => '#b26b00', 'media' => '#135e96', 'polisi' => '#00794a' );
			printf(
				'<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%s;color:#fff;font-size:11px;white-space:nowrap">%s</span>',
				esc_attr( $warna[ $v ] ),
				esc_html( $labels[ $v ] )
			);
			break;
		case 'kh_pelapor':
			$email = get_post_meta( $post_id, 'kh_pelapor_email', true );
			echo $email ? esc_html( $email ) : '<span style="color:#999">—</span>';
			break;
	}
}, 10, 2 );

/**
 * Leaflet picker di layar edit.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'kendaraan' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	wp_enqueue_script( 'kh-admin-picker', KH_CORE_URL . 'assets/admin-picker.js', array( 'leaflet' ), KH_CORE_VERSION, true );
} );

/**
 * Deteksi unit kepolisian tujuan laporan.
 *
 * Menerima "Polsek Dukuh Pakis", "Polresta Surabaya", "Polrestabes Surabaya",
 * "Polda Jatim", "Mapolsek Menganti", "Polsekta Gubeng", atau hanya nama
 * wilayahnya (otomatis dianggap Polsek).
 *
 * @return array{level:string,nama:string,teks:string} level kosong bila input kosong.
 */
function kh_deteksi_unit( $teks ) {
	$asli = trim( preg_replace( '/\s+/u', ' ', (string) $teks ) );
	$out  = array( 'level' => '', 'nama' => '', 'teks' => '' );

	if ( '' === $asli ) {
		return $out;
	}

	/* "Mapolres/Mapolsek/Makopolrestabes" → "polres/polsek/polrestabes" */
	$kerja = preg_replace( '/\b(?:makopol|mapol)/i', 'pol', $asli );

	$label = array(
		'polrestabes' => 'Polrestabes',
		'polresta'    => 'Polresta',
		'polsekta'    => 'Polsek',
		'polsek'      => 'Polsek',
		'polres'      => 'Polres',
		'polda'       => 'Polda',
	);

	$level = '';
	$nama  = '';

	/* urutan penting: polrestabes sebelum polresta sebelum polres */
	if ( preg_match( '/\b(polrestabes|polresta|polsekta|polsek|polres|polda)\b\s*(.*)$/iu', $kerja, $m ) ) {
		$level = $label[ strtolower( $m[1] ) ];
		$nama  = $m[2];
	} else {
		$level = 'Polsek'; /* tanpa awalan unit → anggap Polsek */
		$nama  = $kerja;
	}

	$nama = trim( preg_replace( '/^[\s\.,;:–—-]+/u', '', $nama ) );
	$nama = trim( preg_replace( '/\s+/u', ' ', $nama ) );
	$nama = trim( preg_replace( '/[\s\.,;:–—-]+$/u', '', $nama ) );

	if ( '' !== $nama ) {
		$nama = function_exists( 'mb_convert_case' )
			? mb_convert_case( $nama, MB_CASE_TITLE, 'UTF-8' )
			: ucwords( strtolower( $nama ) );
	}

	$out['level'] = strtolower( $level );
	$out['nama']  = trim( $level . ( $nama ? ' ' . $nama : '' ) );
	$out['teks']  = $out['nama'];

	return $out;
}

/**
 * Simpan/hasilkan label unit untuk tampilan.
 */
function kh_unit_label( $post_id ) {
	$unit = get_post_meta( $post_id, 'kh_unit', true );
	$nama = get_post_meta( $post_id, 'kh_polsek', true );
	if ( $nama ) {
		return $nama;
	}
	return '';
}
