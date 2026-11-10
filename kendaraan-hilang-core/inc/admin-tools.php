<?php
/**
 * Alat bantu admin: jalankan cek ulang manual + tautan konfirmasi pelapor.
 * Di-load setelah admin-mod.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tombol "jalankan cek ulang" di atas daftar kendaraan.
 */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kendaraan' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=kh_recheck_now' ),
		'kh_recheck_now'
	);
	echo '<div class="notice notice-info"><p><strong>Cek ulang otomatis</strong> berjalan tiap hari (email ke pelapor laporan berumur 25–40 hari). '
		. 'Jalankan sekarang: <a href="' . esc_url( $url ) . '">kirim cek ulang hari ini</a>.</p></div>';

	if ( isset( $_GET['kh_recheck'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$n = absint( $_GET['kh_recheck'] ); // phpcs:ignore WordPress.Security.NonceVerification
		printf( '<div class="notice notice-success is-dismissible"><p>%d email cek ulang terkirim.</p></div>', (int) $n );
	}
} );

add_action( 'admin_post_kh_recheck_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak punya izin.' );
	}
	check_admin_referer( 'kh_recheck_now' );

	$n    = kh_run_recheck();
	$back = admin_url( 'edit.php?post_type=kendaraan' );
	wp_safe_redirect( add_query_arg( 'kh_recheck', (int) $n, $back ) );
	exit;
} );

/**
 * Meta box: tautan konfirmasi (untuk dikirim manual / diuji).
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'kh_cek_link',
		'Tautan konfirmasi pelapor',
		function ( $post ) {
			$email = get_post_meta( $post->ID, 'kh_pelapor_email', true );
			$sent  = get_post_meta( $post->ID, 'kh_recheck_sent', true );

			echo '<p style="margin:0 0 6px">Email pelapor: <code>' . esc_html( $email ? $email : '— belum ada' ) . '</code></p>';
			echo '<p style="margin:0 0 6px">Cek ulang terakhir: ' . esc_html( $sent ? $sent : '— belum pernah' ) . '</p>';

			$still = kh_cek_url( $post->ID, 'masih' );
			$found = kh_cek_url( $post->ID, 'ditemukan' );
			echo '<p style="margin:8px 0 4px"><strong>Masih hilang:</strong><br><input type="text" readonly style="width:100%;font-size:11px" value="' . esc_attr( $still ) . '" onclick="this.select()"></p>';
			echo '<p style="margin:8px 0 4px"><strong>Sudah ditemukan:</strong><br><input type="text" readonly style="width:100%;font-size:11px" value="' . esc_attr( $found ) . '" onclick="this.select()"></p>';
			echo '<p class="description" style="margin-top:8px">Tautan ini mengubah status laporan. Kirim hanya ke pelapor.</p>';
		},
		'kendaraan',
		'side',
		'default'
	);
} );
