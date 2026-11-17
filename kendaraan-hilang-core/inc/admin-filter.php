<?php
/**
 * Sortir & filter kolom "Tanggal Hilang" di daftar admin Kendaraan Hilang.
 *
 * Masalah: meta `kh_tanggal` bisa tersimpan dalam beberapa bentuk mentah
 * (`20261112` dari ACF, `Y-m-d` dari perbaikan otomatis, dsb). Kalau kolom
 * disortir langsung dari meta itu, urutannya kacau karena dibandingkan sebagai
 * teks. Solusinya: simpan kunci sort terpisah `kh_tgl` berisi Ymd sebagai angka,
 * lalu sortir & filter memakai kunci itu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hitung kunci sort (Ymd, integer) dari tanggal kejadian.
 * Fallback ke tanggal publikasi kalau tanggal kejadian kosong.
 */
function kh_tgl_hitung( $post_id ) {
	$tgl = kh_normalize_tanggal( get_post_meta( $post_id, 'kh_tanggal', true ) );
	if ( ! $tgl ) {
		$tgl = get_post_time( 'Y-m-d', false, $post_id );
	}
	return $tgl ? (int) str_replace( '-', '', $tgl ) : 0;
}

/**
 * Jaga kunci sort tetap sinkron setiap laporan disimpan.
 * Dua jalur: simpan biasa dan simpan lewat ACF (meta ACF ditulis belakangan).
 */
add_action( 'save_post_kendaraan', function ( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'kh_tgl', kh_tgl_hitung( $post_id ) );
}, 99 );

add_action( 'acf/save_post', function ( $post_id ) {
	if ( is_string( $post_id ) && 0 === strpos( $post_id, 'kendaraan_' ) ) {
		$post_id = (int) substr( $post_id, 10 );
	}
	if ( ! $post_id || 'kendaraan' !== get_post_type( $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'kh_tgl', kh_tgl_hitung( $post_id ) );
}, 30 );

/**
 * Isi kunci sort untuk data lama. Dikerjakan bertahap (500 per muat halaman
 * admin) supaya tidak berat, lalu ditandai selesai lewat opsi.
 */
add_action( 'admin_init', function () {
	if ( 'v1' === get_option( 'kh_tgl_migrasi' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => 'kh_tgl',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	foreach ( $ids as $id ) {
		update_post_meta( $id, 'kh_tgl', kh_tgl_hitung( $id ) );
	}

	if ( count( $ids ) < 500 ) {
		update_option( 'kh_tgl_migrasi', 'v1', false );
	}
}, 20 );

/**
 * Kolom "Tanggal Hilang" bisa diklik untuk sortir.
 */
add_filter( 'manage_edit-kendaraan_sortable_columns', function ( $cols ) {
	$cols['kh_tanggal'] = 'kh_tanggal';
	return $cols;
} );

/**
 * Urutkan pakai kunci numerik, bukan teks mentah.
 */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( 'kendaraan' !== $q->get( 'post_type' ) ) {
		return;
	}
	if ( 'kh_tanggal' === $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', 'kh_tgl' );
		$q->set( 'orderby', 'meta_value_num' );
	}
}, 10 );

/**
 * Daftar bulan yang benar-benar ada datanya (YYYYMM).
 */
function kh_tgl_bulan_tersedia() {
	global $wpdb;
	$rows = $wpdb->get_col(
		"SELECT DISTINCT FLOOR(CAST(pm.meta_value AS UNSIGNED)/100) AS ym
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = 'kh_tgl' AND p.post_type = 'kendaraan' AND p.post_status <> 'trash'
		   AND CAST(pm.meta_value AS UNSIGNED) >= 19000101
		 ORDER BY ym DESC"
	);
	return array_map( 'intval', (array) $rows );
}

/**
 * Dua dropdown di atas tabel: bulan kejadian & rentang waktu.
 */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'kendaraan' !== $post_type ) {
		return;
	}

	$pilih_bulan = isset( $_GET['kh_bulan'] ) ? sanitize_text_field( wp_unslash( $_GET['kh_bulan'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<select name="kh_bulan" style="min-width:150px"><option value="">Semua bulan hilang</option>';
	foreach ( kh_tgl_bulan_tersedia() as $ym ) {
		$label = date_i18n( 'F Y', strtotime( substr( $ym, 0, 4 ) . '-' . substr( $ym, 4, 2 ) . '-01' ) );
		printf(
			'<option value="%1$d"%2$s>%3$s</option>',
			(int) $ym,
			selected( $pilih_bulan, (string) $ym, false ),
			esc_html( $label )
		);
	}
	echo '</select>';

	$pilih_rentang = isset( $_GET['kh_rentang'] ) ? sanitize_key( wp_unslash( $_GET['kh_rentang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$rentang       = array(
		''       => 'Semua waktu',
		'7'      => '7 hari terakhir',
		'30'     => '30 hari terakhir',
		'90'     => '90 hari terakhir',
		'kosong' => 'Tanpa tanggal',
	);
	echo '<select name="kh_rentang"><option value="">Semua waktu</option>';
	foreach ( $rentang as $val => $label ) {
		if ( '' === $val ) {
			continue;
		}
		printf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $val ),
			selected( $pilih_rentang, $val, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}, 10, 1 );

/**
 * Terapkan filter ke query daftar.
 */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( 'kendaraan' !== $q->get( 'post_type' ) ) {
		return;
	}

	$mq  = (array) $q->get( 'meta_query' );
	$add = false;

	$bulan = isset( $_GET['kh_bulan'] ) ? preg_replace( '/\D/', '', (string) wp_unslash( $_GET['kh_bulan'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 6 === strlen( $bulan ) ) {
		$mq[] = array(
			'key'     => 'kh_tgl',
			'value'   => array( (int) ( $bulan . '01' ), (int) ( $bulan . '31' ) ),
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		);
		$add = true;
	}

	$rentang = isset( $_GET['kh_rentang'] ) ? sanitize_key( wp_unslash( $_GET['kh_rentang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'kosong' === $rentang ) {
		$mq[] = array(
			'key'     => 'kh_tgl',
			'value'   => 0,
			'compare' => '<=',
			'type'    => 'NUMERIC',
		);
		$add = true;
	} elseif ( in_array( $rentang, array( '7', '30', '90' ), true ) ) {
		$mq[] = array(
			'key'     => 'kh_tgl',
			'value'   => (int) wp_date( 'Ymd', strtotime( '-' . (int) $rentang . ' days' ) ),
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
		$add = true;
	}

	if ( $add ) {
		if ( count( $mq ) > 1 && ! isset( $mq['relation'] ) ) {
			$mq['relation'] = 'AND';
		}
		$q->set( 'meta_query', $mq );
	}
}, 20 );
