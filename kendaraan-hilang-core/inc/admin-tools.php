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
 * Meta box: riwayat info penemuan (dengan status identitas).
 *
 * Yang belum terverifikasi TIDAK diteruskan ke pelapor — admin perlu lihat
 * kontaknya di sini, bukan hanya di email/Telegram.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'kh_temuan_list',
		'Info penemuan masuk',
		function ( $post ) {
			$list = get_post_meta( $post->ID, 'kh_temuan', true );
			if ( ! is_array( $list ) || ! $list ) {
				echo '<p style="margin:0;color:#646970">Belum ada info penemuan.</p>';
				return;
			}

			echo '<table class="widefat striped" style="font-size:12px"><tbody>';
			foreach ( array_reverse( $list ) as $t ) {
				$verif = ! empty( $t['terverifikasi'] );
				$label = $verif
					? '<span style="color:#00794a;font-weight:600">terverifikasi</span>'
					: '<span style="color:#b32d2e;font-weight:600">belum terverifikasi</span>';

				printf(
					'<tr><td><strong>%s</strong><br>%s<br>Kontak: <code>%s</code><br>Identitas: %s%s<br>IP: <code>%s</code></td></tr>',
					esc_html( $t['waktu'] ?? '' ),
					esc_html( $t['lokasi'] ?? '' ),
					esc_html( ( $t['kontak'] ?? '' ) !== '' ? $t['kontak'] : '—' ),
					wp_kses_post( $label ),
					! empty( $t['email'] ) ? '<br>Google: <code>' . esc_html( $t['email'] ) . '</code>' : '',
					esc_html( $t['ip'] ?? '' )
				);
				if ( ! empty( $t['catatan'] ) ) {
					echo '<tr><td style="background:#fff"><em>' . esc_html( wp_trim_words( $t['catatan'], 40, '…' ) ) . '</em></td></tr>';
				}
			}
			echo '</tbody></table>';
			echo '<p class="description" style="margin-top:8px">Hubungi kontak di atas untuk memastikan sebelum meneruskan ke pelapor. Jangan pernah meminta atau menerima uang.</p>';
		},
		'kendaraan',
		'normal',
		'default'
	);
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
