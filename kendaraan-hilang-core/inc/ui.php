<?php
/**
 * UI peta full-screen (gaya Ingress, single page).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pakai template full-screen untuk halaman depan.
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_front_page() && ! is_admin() ) {
		$custom = KH_CORE_DIR . 'templates/front-peta.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}, 20 );

/**
 * Aset UI.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_front_page() ) {
		return;
	}

	wp_enqueue_style(
		'kh-fonts',
		'https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css', array(), '4.7.1' );
	wp_enqueue_style( 'kh-ui', KH_CORE_URL . 'assets/ui.css', array( 'maplibre' ), KH_CORE_VERSION );

	wp_enqueue_script( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js', array(), '4.7.1', true );
	wp_enqueue_script( 'kh-style', KH_CORE_URL . 'assets/map-style.js', array(), KH_CORE_VERSION, true );
	wp_enqueue_script( 'kh-ui', KH_CORE_URL . 'assets/ui.js', array( 'maplibre', 'kh-style' ), KH_CORE_VERSION, true );

	$data = array(
		'endpoint' => esc_url_raw( rest_url( 'kh/v1/peta' ) ),
		'home'     => esc_url_raw( home_url( '/' ) ),
		'wilayah'  => kh_ui_wilayah(),
		'brand'    => 'Peta Kendaraan Hilang',
		'tagline'  => 'Laporan warga Jawa Timur',
	);
	wp_add_inline_script( 'kh-ui', 'window.KH_UI = ' . wp_json_encode( $data ) . ';', 'before' );
} );

/**
 * Aset untuk halaman detail & arsip (tema gelap).
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'kendaraan' ) && ! is_post_type_archive( 'kendaraan' ) && ! is_tax( 'kendaraan_wilayah' ) ) {
		return;
	}

	wp_enqueue_style(
		'kh-fonts',
		'https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Rajdhani:wght@400;500;600&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css', array(), '4.7.1' );
	wp_enqueue_style( 'kh-site', KH_CORE_URL . 'assets/site.css', array( 'maplibre', 'kh-fonts' ), KH_CORE_VERSION );
	wp_enqueue_style( 'kh-map-hex', KH_CORE_URL . 'assets/ui.css', array(), KH_CORE_VERSION );

	wp_enqueue_script( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js', array(), '4.7.1', true );
	wp_enqueue_script( 'kh-style', KH_CORE_URL . 'assets/map-style.js', array(), KH_CORE_VERSION, true );
} );

/**
 * Aset halaman informasi (legal / cek-status) — tema gelap, tanpa peta.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! kh_is_info_page() ) {
		return;
	}

	wp_enqueue_style(
		'kh-fonts',
		'https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Rajdhani:wght@400;500;600&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'kh-site', KH_CORE_URL . 'assets/site.css', array( 'kh-fonts' ), KH_CORE_VERSION );
} );

/**
 * Aset halaman form pelapor.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page( 'lapor' ) ) {
		return;
	}

	wp_enqueue_style(
		'kh-fonts',
		'https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Rajdhani:wght@400;500;600&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_style( 'kh-site', KH_CORE_URL . 'assets/site.css', array( 'leaflet', 'kh-fonts' ), KH_CORE_VERSION );

	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	wp_enqueue_script( 'kh-form', KH_CORE_URL . 'assets/form.js', array( 'leaflet' ), KH_CORE_VERSION, true );

	$user  = wp_get_current_user();
	$data  = array(
		'rest'      => esc_url_raw( rest_url( 'kh/v1/lapor' ) ),
		'cari'      => esc_url_raw( rest_url( 'kh/v1/cari' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'clientId'  => get_option( 'kh_google_client_id', '' ),
		'loggedIn'  => is_user_logged_in(),
		'userEmail' => is_user_logged_in() ? $user->user_email : '',
	);
	wp_add_inline_script( 'kh-form', 'window.KH_FORM = ' . wp_json_encode( $data ) . ';', 'before' );
} );

/**
 * Daftar wilayah untuk filter (nama + slug + jumlah).
 */
function kh_ui_wilayah() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'kendaraan_wilayah',
			'hide_empty' => true,
		)
	);
	$out = array();
	if ( is_wp_error( $terms ) ) {
		return $out;
	}
	foreach ( $terms as $t ) {
		$out[] = array(
			'name'  => $t->name,
			'slug'  => $t->slug,
			'count' => (int) $t->count,
		);
	}
	return $out;
}

/**
 * Matikan admin bar di front page supaya tampilan benar-benar full-screen.
 */
add_filter( 'show_admin_bar', function ( $show ) {
	if ( is_front_page() && ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	return $show;
} );
