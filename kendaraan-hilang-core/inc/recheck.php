<?php
/**
 * Cek ulang laporan lama: setelah ~30 hari, tanya pelapor "masih hilang?".
 *
 * Tujuan: peta tidak penuh laporan basi, dan pelapor punya jalan menutup laporannya
 * tanpa harus login lagi.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token konfirmasi per laporan.
 */
function kh_cek_token( $post_id ) {
	return substr( wp_hash( 'kh_cek_' . (int) $post_id ), 0, 24 );
}

function kh_cek_url( $post_id, $hasil ) {
	return add_query_arg(
		array(
			'k' => (int) $post_id,
			't' => kh_cek_token( $post_id ),
			'h' => $hasil,
		),
		home_url( '/cek-status/' )
	);
}

/**
 * Jadwalkan cron harian.
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'kh_daily_recheck' ) ) {
		wp_schedule_event( time() + 3600, 'daily', 'kh_daily_recheck' );
	}
} );

add_action( 'kh_daily_recheck', 'kh_run_recheck' );

/**
 * Kirim email cek ulang untuk laporan berumur 25–40 hari.
 */
function kh_run_recheck() {
	$sent = 0;
	$ids  = get_posts(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'column' => 'post_date',
					'after'  => '40 days ago',
					'before' => '25 days ago',
				),
			),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'kh_recheck_sent',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'kh_pelapor_email',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	foreach ( $ids as $id ) {
		$email   = get_post_meta( $id, 'kh_pelapor_email', true );
		$status  = wp_get_post_terms( $id, 'kendaraan_status', array( 'fields' => 'names' ) );
		$is_got  = $status && 'ditemukan' === strtolower( $status[0] );

		if ( ! $email || ! is_email( $email ) || $is_got ) {
			continue;
		}

		$plat   = get_post_meta( $id, 'kh_plat', true );
		$lokasi = get_post_meta( $id, 'kh_lokasi', true );

		$body = array(
			'Halo,',
			'',
			'Se bulan lalu kamu melaporkan kehilangan kendaraan ' . $plat . ' di ' . $lokasi . '.',
			'Kami ingin memastikan datanya tidak basi. Mohon pilih salah satu:',
			'',
			'1) MASIH HILANG — tetap tampil di peta:',
			'   ' . kh_cek_url( $id, 'masih' ),
			'',
			'2) SUDAH DITEMUKAN — kami tandai selesai:',
			'   ' . kh_cek_url( $id, 'ditemukan' ),
			'',
			'Kalau tidak ada jawaban, laporan akan otomatis masuk arsip setelah ' . (int) KH_ARSIP_HARI . ' hari.',
			'',
			'JANGAN pernah mentransfer uang ke orang yang mengaku menemukan kendaraanmu.',
			'Serah terima sebaiknya dilakukan di kantor polisi.',
			'',
			'Halaman laporan: ' . get_permalink( $id ),
		);

		$ok = wp_mail(
			$email,
			'[Peta Kendaraan Hilang] Masih hilang? — ' . $plat,
			implode( "\n", $body )
		);

		if ( $ok ) {
			update_post_meta( $id, 'kh_recheck_sent', current_time( 'mysql' ) );
			$sent++;
		}
	}

	return $sent;
}

/**
 * Halaman /cek-status/ (dibuat otomatis).
 */
add_action( 'init', function () {
	if ( get_option( 'kh_cek_page_seeded' ) ) {
		return;
	}
	if ( ! get_page_by_path( 'cek-status' ) ) {
		wp_insert_post(
			array(
				'post_title'   => 'Konfirmasi Status Laporan',
				'post_name'    => 'cek-status',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Halaman ini dipakai untuk konfirmasi status laporan dari tautan email.',
			)
		);
	}
	update_option( 'kh_cek_page_seeded', 1 );
} );

add_filter( 'template_include', function ( $template ) {
	if ( is_page( 'cek-status' ) ) {
		$custom = KH_CORE_DIR . 'templates/cek-status.php';
		return file_exists( $custom ) ? $custom : $template;
	}
	return $template;
}, 20 );
