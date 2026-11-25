<?php
/**
 * Moderasi cepat di daftar kendaraan:
 * - Aksi baris: setujui & tayangkan, tandai verifikasi, tandai ditemukan
 * - Bulk action: setujui banyak sekaligus
 * - Tampilan "Perlu ditinjau (>24 jam)"
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL aksi cepat.
 */
function kh_quick_url( $post_id, $do, $extra = array() ) {
	$args = array_merge(
		array(
			'action' => 'kh_quick',
			'post'   => $post_id,
			'do'     => $do,
		),
		$extra
	);
	$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
	return wp_nonce_url( $url, 'kh_quick_' . $post_id );
}

/**
 * Tambah aksi di baris daftar.
 */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'kendaraan' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$quick = array();

	if ( 'pending' === $post->post_status ) {
		$quick['kh_approve'] = '<a href="' . esc_url( kh_quick_url( $post->ID, 'approve' ) ) . '" style="color:#00794a;font-weight:600">Setujui &amp; tayangkan</a>';
	}

	if ( 'polisi' !== kh_verifikasi_of( $post->ID ) ) {
		$quick['kh_verif_polisi'] = '<a href="' . esc_url( kh_quick_url( $post->ID, 'verif', array( 'level' => 'polisi' ) ) ) . '">Tandai lapor polisi</a>';
	}

	if ( 'media' !== kh_verifikasi_of( $post->ID ) ) {
		$quick['kh_verif_media'] = '<a href="' . esc_url( kh_quick_url( $post->ID, 'verif', array( 'level' => 'media' ) ) ) . '">Tandai terverifikasi media</a>';
	}

	$status = wp_get_post_terms( $post->ID, 'kendaraan_status', array( 'fields' => 'names' ) );
	if ( $status && 'ditemukan' === strtolower( $status[0] ) ) {
		/* Jalan balik kalau salah tandai — tanpa ini satu klik keliru mengubah
		   laporan jadi "selesai" secara permanen. */
		$quick['kh_unfound'] = '<a href="' . esc_url( kh_quick_url( $post->ID, 'status', array( 'to' => 'hilang' ) ) ) . '">Batalkan tanda ditemukan</a>';
	} else {
		$quick['kh_found'] = '<a href="' . esc_url( kh_quick_url( $post->ID, 'status', array( 'to' => 'ditemukan' ) ) ) . '">Tandai ditemukan</a>';
	}

	return array_merge( $quick, $actions );
}, 10, 2 );

/**
 * Handler aksi cepat.
 */
add_action( 'admin_post_kh_quick', function () {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$do      = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';

	if ( ! $post_id || 'kendaraan' !== get_post_type( $post_id ) ) {
		wp_die( 'Data tidak ditemukan.' );
	}
	check_admin_referer( 'kh_quick_' . $post_id );

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( 'Tidak punya izin.' );
	}

	$done = '';

	if ( 'approve' === $do ) {
		if ( current_user_can( 'publish_posts' ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
			$done = 'approved';
		}
	} elseif ( 'verif' === $do ) {
		$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		if ( in_array( $level, array( 'media', 'polisi', 'warga' ), true ) ) {
			update_post_meta( $post_id, 'kh_verifikasi', $level );
			$done = 'verif_' . $level;
		}
	} elseif ( 'status' === $do ) {
		$to = isset( $_GET['to'] ) ? sanitize_key( wp_unslash( $_GET['to'] ) ) : '';
		if ( in_array( $to, array( 'ditemukan', 'hilang' ), true ) ) {
			wp_set_object_terms( $post_id, 'ditemukan' === $to ? 'Ditemukan' : 'Hilang', 'kendaraan_status' );
			if ( 'ditemukan' === $to ) {
				update_post_meta( $post_id, 'kh_diff', current_time( 'mysql' ) );
			}
			$done = 'ditemukan' === $to ? 'found' : 'unfound';
		}
	}

	$back = wp_get_referer();
	if ( ! $back ) {
		$back = admin_url( 'edit.php?post_type=kendaraan' );
	}
	wp_safe_redirect( add_query_arg( 'kh_done', $done ? $done : 'none', $back ) );
	exit;
} );

/**
 * Bulk action: setujui & tayangkan.
 */
add_filter( 'bulk_actions-edit-kendaraan', function ( $actions ) {
	$actions['kh_bulk_approve']      = 'Setujui & tayangkan';
	$actions['kh_bulk_verif_media']  = 'Tandai terverifikasi media';
	$actions['kh_bulk_verif_polisi'] = 'Tandai lapor polisi';
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-kendaraan', function ( $redirect, $action, $ids ) {
	/* Verifikasi massal: hanya menulis meta kh_verifikasi, tidak menyentuh status. */
	$peta_verif = array(
		'kh_bulk_verif_media'  => 'media',
		'kh_bulk_verif_polisi' => 'polisi',
	);

	if ( isset( $peta_verif[ $action ] ) ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect;
		}
		$level = $peta_verif[ $action ];
		$n     = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id || 'kendaraan' !== get_post_type( $id ) ) {
				continue;
			}
			update_post_meta( $id, 'kh_verifikasi', $level );
			$n++;
		}
		return add_query_arg( array( 'kh_bulk' => $n, 'kh_bulk_kind' => $level ), $redirect );
	}

	if ( 'kh_bulk_approve' !== $action ) {
		return $redirect;
	}
	if ( ! current_user_can( 'publish_posts' ) ) {
		return $redirect;
	}

	$n = 0;
	foreach ( $ids as $id ) {
		$id = absint( $id );
		if ( ! $id || 'kendaraan' !== get_post_type( $id ) ) {
			continue;
		}
		if ( 'pending' === get_post_status( $id ) || 'draft' === get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
			$n++;
		}
	}

	return add_query_arg( array( 'kh_bulk' => $n, 'kh_bulk_kind' => 'approve' ), $redirect );
}, 10, 3 );

/**
 * Tampilan "Perlu ditinjau (>24 jam)".
 */
add_filter( 'views_edit-kendaraan', function ( $views ) {
	$late = count(
		get_posts(
			array(
				'post_type'      => 'kendaraan',
				'post_status'    => 'pending',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'column'    => 'post_date',
						'before'    => '24 hours ago',
						'inclusive' => false,
					),
				),
			)
		)
	);

	$url      = admin_url( 'edit.php?post_type=kendaraan&kh_late=1' );
	$is_late  = isset( $_GET['kh_late'] ) && '1' === $_GET['kh_late']; // phpcs:ignore WordPress.Security.NonceVerification
	$klass    = $is_late ? ' class="current"' : '';
	$views['kh_late'] = sprintf(
		'<a href="%s"%s>Perlu ditinjau &gt;24 jam <span class="count">(%d)</span></a>',
		esc_url( $url ),
		$klass,
		(int) $late
	);

	return $views;
} );

/**
 * Filter query untuk tampilan tersebut.
 */
add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'kendaraan' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( ! isset( $_GET['kh_late'] ) || '1' !== $_GET['kh_late'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$query->set( 'post_status', 'pending' );
	$query->set(
		'date_query',
		array(
			array(
				'column'    => 'post_date',
				'before'    => '24 hours ago',
				'inclusive' => false,
			),
		)
	);
} );

/**
 * Notifikasi hasil aksi.
 */
add_action( 'admin_notices', function () {
	if ( empty( $_GET['kh_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$done = sanitize_key( wp_unslash( $_GET['kh_done'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$map  = array(
		'approved'   => 'Laporan sudah ditayangkan.',
		'verif_media'  => 'Ditandai: terverifikasi media.',
		'verif_polisi' => 'Ditandai: sudah lapor polisi.',
		'verif_warga'  => 'Ditandai: belum diverifikasi.',
		'found'      => 'Ditandai: sudah ditemukan.',
		'unfound'    => 'Ditandai kembali: masih hilang.',
		'none'       => 'Tidak ada perubahan.',
	);
	$msg = isset( $map[ $done ] ) ? $map[ $done ] : '';
	if ( $msg ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
	}
} );

/**
 * Notifikasi setelah bulk action (approve / verifikasi massal).
 */
add_action( 'admin_notices', function () {
	if ( empty( $_GET['kh_bulk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$n = absint( $_GET['kh_bulk'] ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $n ) {
		return;
	}
	$kind  = isset( $_GET['kh_bulk_kind'] ) ? sanitize_key( wp_unslash( $_GET['kh_bulk_kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$label = array(
		'media'   => 'ditandai terverifikasi media',
		'polisi'  => 'ditandai sudah lapor polisi',
		'approve' => 'berhasil ditayangkan',
	);
	$msg = isset( $label[ $kind ] ) ? $label[ $kind ] : 'diperbarui';
	printf( '<div class="notice notice-success is-dismissible"><p>%d laporan %s.</p></div>', (int) $n, esc_html( $msg ) );
} );
