<?php
/**
 * Pencarian daftar laporan: ikut mencari nomor polisi dan kolom data lainnya.
 *
 * Pencarian bawaan WordPress hanya menyisir judul/isi/kutipan, sedangkan nomor
 * polisi, lokasi, polsek, warna, dan ciri disimpan sebagai meta. Akibatnya
 * mengetik "L 3051 BBE" tidak menemukan apa pun. Filter di bawah menambahkan
 * pencarian ke meta tersebut, termasuk pencocokan tanpa spasi ("L3051BBE").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kolom meta yang ikut dicari.
 */
function kh_search_meta_keys() {
	return array( 'kh_plat', 'kh_lokasi', 'kh_polsek', 'kh_warna', 'kh_ciri', 'kh_merk', 'kh_model', 'kh_sttlp', 'kh_sumber_url' );
}

/**
 * Sisipkan kondisi pencarian meta ke query daftar admin.
 */
add_filter( 'posts_search', function ( $search, $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) {
		return $search;
	}
	if ( 'kendaraan' !== $q->get( 'post_type' ) ) {
		return $search;
	}

	$kata = trim( (string) $q->get( 's' ) );
	if ( '' === $kata ) {
		return $search;
	}

	global $wpdb;
	$longgar = '%' . $wpdb->esc_like( $kata ) . '%';
	$rapat   = '%' . $wpdb->esc_like( preg_replace( '/\s+/', '', $kata ) ) . '%';
	$kunci   = "'" . implode( "','", array_map( 'esc_sql', kh_search_meta_keys() ) ) . "'";

	$meta = $wpdb->prepare(
		"EXISTS ( SELECT 1 FROM {$wpdb->postmeta} khpm
			WHERE khpm.post_id = {$wpdb->posts}.ID
			  AND khpm.meta_key IN ($kunci)
			  AND ( khpm.meta_value LIKE %s OR REPLACE( khpm.meta_value, ' ', '' ) LIKE %s ) )",
		$longgar,
		$rapat
	);

	$dasar = preg_replace( '/^\s*AND\s*/i', '', (string) $search );

	return ' AND ( ' . ( $dasar ? $dasar . ' OR ' : '' ) . $meta . ' ) ';
}, 10, 2 );

/**
 * Petunjuk di kotak pencarian supaya jelas apa saja yang bisa dicari.
 */
add_action( 'admin_footer-edit.php', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'kendaraan' !== $screen->post_type ) {
		return;
	}
	?>
	<script>
	( function () {
		var kotak = document.getElementById( 'post-search-input' );
		if ( ! kotak ) { return; }
		if ( ! kotak.placeholder ) { kotak.placeholder = 'Cari plat, lokasi, polsek, atau judul…'; }
		kotak.style.minWidth = '280px';
	} )();
	</script>
	<?php
} );
