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

	/* Slug berbasis nomor polisi baru bisa dihitung setelah meta tersimpan. */
	kh_pakai_slug_plat( $post_id );
}

/**
 * Slug dari nomor polisi: "l-4137-cam-honda-beat-2023".
 *
 * Plat ditaruh paling depan supaya kendaraan langsung dikenali dari URL
 * (warga menyebut "L 4137 CAM"), sementara merek/model/tahun menjaga kata
 * kunci untuk pencarian. Plat praktis unik: importer & form lapor sama-sama
 * menolak plat duplikat.
 */
function kh_slug_dari_plat( $plat, $merk = '', $model = '', $tahun = '' ) {
	$plat = kh_normalize_plat( $plat );
	if ( '' === $plat ) {
		return '';
	}
	$bagian = array( $plat );
	foreach ( array( $merk, $model, $tahun ) as $b ) {
		$b = trim( (string) $b );
		if ( '' !== $b ) {
			$bagian[] = $b;
		}
	}
	return sanitize_title( implode( ' ', $bagian ) );
}

/**
 * Terapkan slug berbasis plat (sekali saja per perubahan).
 *
 * Aturan aman:
 * - hanya CPT kendaraan, dan hanya kalau plat terisi;
 * - slug yang sudah berawalan plat tidak disentuh (biarkan akhiran -2 dari WP);
 * - slug bawaan WordPress (kosong / diawali "hilang-" / sama dengan judul)
 *   diganti; slug yang disunting admin sendiri dibiarkan.
 *
 * WordPress menyimpan _wp_old_slug saat slug berubah, jadi URL lama tetap
 * 301 ke yang baru (wp_old_slug_redirect).
 */
function kh_pakai_slug_plat( $post_id ) {
	static $jalan = false;
	if ( $jalan || 'kendaraan' !== get_post_type( $post_id ) ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'auto-draft' === $post->post_status ) {
		return;
	}

	$plat = kh_normalize_plat( get_post_meta( $post_id, 'kh_plat', true ) );
	if ( '' === $plat ) {
		return;
	}

	$slug = kh_slug_dari_plat(
		$plat,
		get_post_meta( $post_id, 'kh_merk', true ),
		get_post_meta( $post_id, 'kh_model', true ),
		get_post_meta( $post_id, 'kh_tahun', true )
	);
	if ( '' === $slug ) {
		return;
	}

	$sekarang  = (string) $post->post_name;
	$plat_slug = sanitize_title( $plat );

	if ( '' !== $sekarang && 0 === strpos( $sekarang, $plat_slug ) ) {
		return; // sudah berbasis plat
	}

	$bawaan = ( '' === $sekarang
		|| $sekarang === $slug
		|| 0 === strpos( $sekarang, 'hilang-' )
		|| $sekarang === sanitize_title( $post->post_title ) );
	if ( ! $bawaan ) {
		return; // slug karangan admin — jangan diubah
	}

	$baru = wp_unique_post_slug( $slug, $post_id, $post->post_status, 'kendaraan', $post->post_parent );
	if ( '' === $baru || $baru === $sekarang ) {
		return;
	}

	$jalan = true;
	wp_update_post( array( 'ID' => $post_id, 'post_name' => $baru ) );
	$jalan = false;
}

add_action( 'save_post_kendaraan', 'kh_pakai_slug_plat', 20 );
add_action( 'acf/save_post', 'kh_pakai_slug_plat', 30 );

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
/**
 * Ubah berbagai bentuk tanggal menjadi Y-m-d (atau '' kalau tidak bisa dibaca).
 *
 * Menerima: 2026-08-02, 20260802, 02/08/2026, 2-8-2026, "2 Agustus 2026",
 * dan menambal keluaran ACF yang rusak seperti "Y-8-2" (bulan-tanggal,
 * tahun berjalan) — nilai rusak itu muncul kalau tanggal diketik manual
 * dengan tahun 4 digit pada isian berformat dd/mm/yy.
 */
function kh_parse_tanggal( $value ) {
	$v = trim( (string) $value );
	if ( '' === $v ) {
		return '';
	}

	/* 2026-08-02 / 2026-8-2 */
	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m ) ) {
		return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
	}

	/* 20260802 */
	if ( preg_match( '/^(\d{8})$/', $v ) ) {
		return substr( $v, 0, 4 ) . '-' . substr( $v, 4, 2 ) . '-' . substr( $v, 6, 2 );
	}

	/* 02/08/2026, 2-8-2026, 2.8.2026 */
	if ( preg_match( '/^(\d{1,2})[\/\.,-](\d{1,2})[\/\.,-](\d{4})$/', $v, $m ) ) {
		return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
	}

	/* 02/08/26 */
	if ( preg_match( '/^(\d{1,2})[\/\.,-](\d{1,2})[\/\.,-](\d{2})$/', $v, $m ) ) {
		return sprintf( '%04d-%02d-%02d', 2000 + (int) $m[3], $m[2], $m[1] );
	}

	/* keluaran ACF rusak: "Y-8-2" / "y-8-2" → 2 Agustus tahun berjalan */
	if ( preg_match( '/^[Yy][-\/](\d{1,2})[-\/](\d{1,2})$/', $v, $m ) ) {
		$bulan = (int) $m[1];
		$hari  = (int) $m[2];
		if ( $bulan >= 1 && $bulan <= 12 && $hari >= 1 && $hari <= 31 ) {
			return sprintf( '%04d-%02d-%02d', (int) current_time( 'Y' ), $bulan, $hari );
		}
	}

	/* 2 Agustus 2026 / 2 agustus */
	$nama_bulan = array(
		'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
		'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
	);
	if ( preg_match( '/^(\d{1,2})\s+([A-Za-z]+)(?:\s+(\d{4}))?$/u', $v, $m ) ) {
		$kunci = strtolower( $m[2] );
		if ( isset( $nama_bulan[ $kunci ] ) ) {
			$tahun = ! empty( $m[3] ) ? (int) $m[3] : (int) current_time( 'Y' );
			return sprintf( '%04d-%02d-%02d', $tahun, $nama_bulan[ $kunci ], (int) $m[1] );
		}
	}

	/* percobaan terakhir */
	$ts = strtotime( $v );
	if ( $ts ) {
		return wp_date( 'Y-m-d', $ts );
	}

	return '';
}

/**
 * Nama lama, dipertahankan untuk pemanggil yang sudah ada.
 */
function kh_normalize_tanggal( $value ) {
	return kh_parse_tanggal( $value );
}

/**
 * Tanggal siap tampil: "2 Agustus 2026".
 */
function kh_tanggal_teks( $value, $kosong = '' ) {
	$d = kh_parse_tanggal( $value );
	if ( ! $d ) {
		return $kosong;
	}
	return date_i18n( 'j F Y', strtotime( $d ) );
}

/**
 * Rapikan nilai tanggal di database setelah disimpan (ACF / editor mana pun).
 */
add_action( 'acf/save_post', function ( $post_id ) {
	if ( 'kendaraan' !== get_post_type( $post_id ) ) {
		return;
	}
	$raw = get_post_meta( $post_id, 'kh_tanggal', true );
	if ( '' === trim( (string) $raw ) ) {
		return;
	}
	$fix = kh_parse_tanggal( $raw );
	if ( $fix && $fix !== $raw ) {
		update_post_meta( $post_id, 'kh_tanggal', $fix );
	}
}, 20 );

/**
 * Tambal sekali data lama yang tersimpan dalam bentuk rusak (mis. "Y-8-2").
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_tgl_fix_v1' ) ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		$raw = trim( (string) get_post_meta( $id, 'kh_tanggal', true ) );
		if ( '' === $raw ) {
			continue;
		}
		$fix = kh_parse_tanggal( $raw );
		if ( $fix && $fix !== $raw ) {
			update_post_meta( $id, 'kh_tanggal', $fix );
		}
	}

	update_option( 'kh_tgl_fix_v1', 1 );
} );

/**
 * Sekali-jalan: terapkan slug berbasis nomor polisi ke laporan yang sudah ada.
 * Bergerbang option, jadi hanya jalan sekali per situs.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_slug_plat_v1' ) ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		kh_pakai_slug_plat( $id );
	}

	update_option( 'kh_slug_plat_v1', 1 );
} );

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
			$t = kh_normalize_tanggal( get_post_meta( $post_id, 'kh_tanggal', true ) );
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
	wp_localize_script(
		'kh-admin-picker',
		'KH_ADMIN',
		array(
			'cari'      => esc_url_raw( rest_url( 'kh/v1/cari' ) ),
			'koordinat' => esc_url_raw( rest_url( 'kh/v1/koordinat' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
		)
	);
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
