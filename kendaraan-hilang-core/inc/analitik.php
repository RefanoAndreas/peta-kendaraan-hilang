<?php
/**
 * Halaman Analitik — infografis laporan kendaraan hilang (full-frame).
 *
 * Alamat: /analitik/
 * Akses : admin saja sampai opsi `kh_analitik_publik` = '1' (tanpa perlu ubah kode).
 *
 * Data diambil di browser dari REST `/kh/v1/peta?limit=1000&arsip=1` (418+ laporan,
 * ±270 KB → ±60 KB setelah gzip), lalu diolah di klien dengan D3. Tidak ada
 * query berat di server, jadi halaman tetap cepat dan angkanya selalu segar.
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apakah pengunjung sekarang boleh melihat halaman analitik?
 */
function kh_analitik_boleh() {
	if ( '1' === (string) get_option( 'kh_analitik_publik', '0' ) ) {
		return true;
	}
	return current_user_can( 'manage_options' );
}

/**
 * Apakah request sekarang adalah halaman analitik?
 */
function kh_is_analitik() {
	return ! is_admin() && is_page( 'analitik' );
}

/**
 * Pastikan halaman `analitik` ada (dibuat otomatis; isi halaman tidak dipakai,
 * template yang menggambar). Halaman WordPress dipakai — bukan rewrite ke query
 * var kosong — karena query var kosong membuat WP mengirim status 404.
 */
add_action( 'admin_init', function () {
	$id = (int) get_option( 'kh_analitik_page' );
	if ( $id && get_post( $id ) ) {
		return;
	}
	$ada = get_page_by_path( 'analitik' );
	if ( $ada ) {
		update_option( 'kh_analitik_page', $ada->ID );
		return;
	}
	$baru = wp_insert_post(
		array(
			'post_title'   => 'Statistik Kendaraan Hilang',
			'post_name'    => 'analitik',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		)
	);
	if ( $baru && ! is_wp_error( $baru ) ) {
		update_option( 'kh_analitik_page', $baru );
	}
} );

/**
 * Flush rewrite sekali per versi plugin. PENTING pada rilis ini: aturan lama
 * `^analitik/?$` (dari percobaan query-var) sudah terlanjur tersimpan di DB dan
 * akan mencegat /analitik/ sebelum halaman WordPress sempat diresolusi — flush
 * menghapusnya karena aturan itu tidak lagi didaftarkan.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_analitik_rewrite' ) !== KH_CORE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'kh_analitik_rewrite', KH_CORE_VERSION );
	}
} );

/**
 * Template full-frame. Prioritas 21 = sesudah handler 404/front page di inc/ui.php.
 */
add_filter( 'template_include', function ( $template ) {
	if ( ! kh_is_analitik() || is_admin() ) {
		return $template;
	}

	if ( ! kh_analitik_boleh() ) {
		status_header( 404 );
		nocache_headers();
		$notfound = KH_CORE_DIR . 'templates/404.php';
		return file_exists( $notfound ) ? $notfound : $template;
	}

	$file = KH_CORE_DIR . 'templates/analitik.php';
	return file_exists( $file ) ? $file : $template;
}, 21 );

/**
 * Jangan tampilkan admin bar supaya benar-benar full-frame.
 */
add_filter( 'show_admin_bar', function ( $show ) {
	return kh_is_analitik() ? false : $show;
} );

/**
 * Judul dokumen (tema memakai title-tag; jangan cetak <title> manual di template
 * supaya tidak dobel).
 */
add_filter( 'document_title_parts', function ( $parts ) {
	if ( kh_is_analitik() ) {
		$parts['title'] = 'Statistik Kendaraan Hilang';
	}
	return $parts;
} );

/**
 * Aset halaman analitik (semua library di-self-host, tidak ada CDN).
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! kh_is_analitik() || ! kh_analitik_boleh() ) {
		return;
	}

	wp_enqueue_style(
		'kh-fonts',
		'https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&family=Orbitron:wght@500;700;900&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'maplibre', KH_CORE_URL . 'assets/vendor/maplibre-gl.css', array(), '4.7.1' );
	wp_enqueue_style( 'kh-analitik', KH_CORE_URL . 'assets/analitik.css', array( 'kh-fonts' ), KH_CORE_VERSION );

	wp_enqueue_script( 'maplibre', KH_CORE_URL . 'assets/vendor/maplibre-gl.js', array(), '4.7.1', true );
	wp_enqueue_script( 'kh-style', KH_CORE_URL . 'assets/map-style.js', array(), KH_CORE_VERSION, true );
	wp_enqueue_script( 'd3', KH_CORE_URL . 'assets/vendor/d3.min.js', array(), '7.9.0', true );
	wp_enqueue_script( 'anime', KH_CORE_URL . 'assets/vendor/anime.min.js', array(), '3.2.2', true );
	wp_enqueue_script( 'gsap', KH_CORE_URL . 'assets/vendor/gsap.min.js', array(), '3.13.0', true );
	wp_enqueue_script( 'gsap-st', KH_CORE_URL . 'assets/vendor/ScrollTrigger.min.js', array( 'gsap' ), '3.13.0', true );
	wp_enqueue_script(
		'kh-analitik',
		KH_CORE_URL . 'assets/analitik.js',
		array( 'maplibre', 'kh-style', 'd3', 'anime', 'gsap', 'gsap-st' ),
		KH_CORE_VERSION,
		true
	);

	$kec = KH_CORE_DIR . 'inc/data-kecamatan.php';
	$data = array(
		'endpoint'  => esc_url_raw( rest_url( 'kh/v1/peta' ) ),
		'home'      => esc_url_raw( home_url( '/' ) ),
		'peta'      => esc_url_raw( home_url( '/#kh-peta' ) ),
		'brand'     => 'Peta Kendaraan Hilang',
		'kecamatan' => file_exists( $kec ) ? include $kec : array(),
		'publik'    => '1' === (string) get_option( 'kh_analitik_publik', '0' ),
	);
	wp_add_inline_script( 'kh-analitik', 'window.KH_AN = ' . wp_json_encode( $data ) . ';', 'before' );
} );
