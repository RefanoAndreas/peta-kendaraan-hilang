<?php
/**
 * Pengerasan dasar: tutup enumerasi user, XML-RPC, dan sitemap penulis.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Tutup /wp-json/wp/v2/users (bisa dipakai menebak nama admin).
 */
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
		return $endpoints; // admin tetap boleh
	}
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
} );

/**
 * 2. Tutup ?author=N (redirect ke halaman penulis yang membocorkan username).
 */
add_action( 'template_redirect', function () {
	if ( ! is_author() ) {
		return;
	}
	if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
} );

/**
 * 3. Matikan XML-RPC (tidak dipakai, sering jadi target brute force).
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * 4. Jangan cantumkan daftar user di sitemap WordPress.
 */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	if ( 'users' === $name ) {
		return false; // hook yang benar: singular, menerima ($provider, $name)
	}
	return $provider;
}, 10, 2 );

/**
 * 5. Buang header yang membocorkan versi WordPress.
 */
add_filter( 'the_generator', '__return_empty_string' );
remove_action( 'wp_head', 'wp_generator' );
