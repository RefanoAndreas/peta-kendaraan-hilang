<?php
/**
 * Penyelarasan dengan Pedoman Pemberitaan Media Siber (Dewan Pers).
 *
 * Dua hal yang dikerjakan di sini:
 * 1. Catatan koreksi: setiap perubahan data penting dicatat + waktu, ditampilkan
 *    di halaman detail (butir 4.b/4.c: ralat/koreksi wajib ditautkan & bertanggal).
 * 2. Pemantau sumber: artikel sumber dicek berkala; kalau hilang/dicabut, admin
 *    diberi tahu (butir 4.d.2/5.2: ikut mengoreksi/mencabut bila sumber berubah).
 *
 * Situs ini bukan media siber, jadi pedoman itu tidak mengikat langsung — modul ini
 * menjaga agar praktik kita tidak bertentangan dengannya.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field yang perubahannya dicatat sebagai koreksi (meta key => label).
 */
function kh_koreksi_fields() {
	return array(
		'kh_plat'     => 'nomor polisi',
		'kh_merk'     => 'merek',
		'kh_model'    => 'model',
		'kh_tahun'    => 'tahun',
		'kh_warna'    => 'warna',
		'kh_lokasi'   => 'lokasi',
		'kh_tanggal'  => 'tanggal kejadian',
		'kh_jam'      => 'jam',
		'kh_polsek'   => 'unit polisi',
		'kh_verifikasi' => 'level verifikasi',
	);
}

/**
 * Apakah perubahan ini benar-benar berbeda (bukan sekadar normalisasi teknis)?
 *
 * ACF menyimpan tanggal sebagai Ymd lalu normalizer kita menulis Y-m-d —
 * itu bukan koreksi data, jadi tidak boleh muncul di halaman publik.
 */
function kh_koreksi_sama( $key, $lama, $baru ) {
	switch ( $key ) {
		case 'kh_tanggal':
			return kh_parse_tanggal( $lama ) === kh_parse_tanggal( $baru );
		case 'kh_plat':
			return kh_normalize_plat( $lama ) === kh_normalize_plat( $baru );
		case 'kh_jam':
			return substr( trim( (string) $lama ), 0, 5 ) === substr( trim( (string) $baru ), 0, 5 );
		default:
			return mb_strtolower( trim( (string) $lama ) ) === mb_strtolower( trim( (string) $baru ) );
	}
}

/**
 * Catat perubahan meta penting sebagai koreksi.
 *
 * Dipasang pada filter `update_post_metadata` (berjalan SEBELUM nilai baru
 * disimpan), jadi nilai lama masih bisa dibaca. Nilai kembalian `$check`
 * dibiarkan apa adanya supaya update tetap berjalan normal.
 */
add_filter( 'update_post_metadata', function ( $check, $post_id, $meta_key, $meta_value ) {
	if ( null !== $check ) {
		return $check;
	}

	$fields = kh_koreksi_fields();
	if ( ! isset( $fields[ $meta_key ] ) || 'kendaraan' !== get_post_type( $post_id ) ) {
		return $check;
	}

	$lama = get_post_meta( $post_id, $meta_key, true );

	/* pengisian pertama (data masih kosong) bukan koreksi */
	if ( '' === trim( (string) $lama ) ) {
		return $check;
	}
	if ( kh_koreksi_sama( $meta_key, $lama, $meta_value ) ) {
		return $check;
	}

	$log = get_post_meta( $post_id, 'kh_koreksi', true );
	$log = is_array( $log ) ? $log : array();

	$log[] = array(
		'waktu' => current_time( 'mysql' ),
		'field' => $meta_key,
		'label' => $fields[ $meta_key ],
		'lama'  => mb_substr( (string) $lama, 0, 80 ),
		'baru'  => mb_substr( (string) $meta_value, 0, 80 ),
	);

	/* simpan maksimal 30 catatan terakhir */
	$log = array_slice( $log, -30 );

	/* hindari rekursi: update_post_meta('kh_koreksi') tidak termasuk field yang dicatat */
	update_post_meta( $post_id, 'kh_koreksi', $log );

	return $check;
}, 10, 4 );

/**
 * Ambil catatan koreksi terbaru (maks $n), terbaru dulu.
 */
function kh_koreksi_terbaru( $post_id, $n = 3 ) {
	$log = get_post_meta( $post_id, 'kh_koreksi', true );
	if ( ! is_array( $log ) || ! $log ) {
		return array();
	}
	/*
	 * Entri identik (waktu + label sama) kadang tersimpan dobel oleh skrip
	 * perbaikan data. Halaman detail sempat menampilkan "Pernah dikoreksi:
	 * 16 November 2026, 15:42 — lokasi diperbarui" dua kali. Tampilkan sekali.
	 */
	$unik = array();
	foreach ( $log as $k ) {
		if ( ! is_array( $k ) ) {
			continue;
		}
		$key            = ( isset( $k['waktu'] ) ? $k['waktu'] : '' ) . '|' . ( isset( $k['label'] ) ? $k['label'] : '' );
		$unik[ $key ]   = $k;
	}
	$log = array_values( $unik );

	return array_reverse( array_slice( $log, -$n ) );
}

/**
 * Teks tanggal untuk catatan koreksi.
 */
function kh_koreksi_waktu( $mysql ) {
	$ts = strtotime( (string) $mysql );
	return $ts ? date_i18n( 'j F Y, H:i', $ts ) : '';
}

/* ------------------------------------------------------------------ */
/* Pemantau artikel sumber                                            */
/* ------------------------------------------------------------------ */

/**
 * Cek satu URL: hidup atau tidak. Mengembalikan 'ok' | 'hilang' | 'error'.
 */
function kh_cek_satu_sumber( $url ) {
	$resp = wp_remote_head( $url, array( 'timeout' => 12, 'redirection' => 3, 'user-agent' => 'PetaKendaraanHilang/1.0 (+cek sumber)' ) );

	if ( is_wp_error( $resp ) ) {
		/* sebagian server menolak HEAD — coba GET sekali */
		$resp = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'user-agent' => 'PetaKendaraanHilang/1.0 (+cek sumber)' ) );
	}
	if ( is_wp_error( $resp ) ) {
		return 'error';
	}

	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( in_array( $code, array( 404, 410 ), true ) ) {
		return 'hilang';
	}
	if ( $code >= 400 ) {
		return 'error';
	}
	return 'ok';
}

/**
 * Cek semua laporan yang punya sumber_url (dibatasi per run, paling lama dicek dulu).
 *
 * @return array( 'cek' => n, 'hilang' => array of array( 'id','judul','url' ) )
 */
function kh_cek_sumber_semua( $limit = 20 ) {
	/*
	 * Ambil kandidat lalu urutkan di PHP. JANGAN pakai 'meta_key' di WP_Query:
	 * itu INNER JOIN ke meta kh_sumber_cek, sehingga laporan yang belum pernah
	 * dicek (meta belum ada) tidak pernah ikut — hasilnya selalu "0 diperiksa".
	 */
	$kandidat = get_posts(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => 'kh_sumber_url',
					'value'   => '',
					'compare' => '!=',
				),
			),
		)
	);

	usort(
		$kandidat,
		function ( $a, $b ) {
			$ta = (string) get_post_meta( $a, 'kh_sumber_cek', true );
			$tb = (string) get_post_meta( $b, 'kh_sumber_cek', true );
			if ( $ta === $tb ) {
				return 0;
			}
			if ( '' === $ta ) {
				return -1; /* belum pernah dicek → didahulukan */
			}
			if ( '' === $tb ) {
				return 1;
			}
			return strcmp( $ta, $tb ); /* paling lama dicek → didahulukan */
		}
	);

	$ids = array_slice( $kandidat, 0, max( 1, (int) $limit ) );

	$hilang = array();

	foreach ( $ids as $id ) {
		$url = (string) get_post_meta( $id, 'kh_sumber_url', true );
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			continue;
		}

		$status = kh_cek_satu_sumber( $url );
		$lama   = (string) get_post_meta( $id, 'kh_sumber_status', true );

		update_post_meta( $id, 'kh_sumber_status', $status );
		update_post_meta( $id, 'kh_sumber_cek', current_time( 'mysql' ) );

		if ( 'hilang' === $status && 'hilang' !== $lama ) {
			$hilang[] = array(
				'id'    => $id,
				'judul' => get_the_title( $id ),
				'url'   => $url,
			);
		}
	}

	/* satu email per run, hanya kalau ada yang baru hilang */
	if ( $hilang ) {
		$ke   = get_option( 'kh_notif_email', get_option( 'admin_email' ) );
		$body = "Artikel sumber berikut tidak bisa diakses lagi (mungkin dicabut/dipindah).\n"
			. "Periksa apakah laporannya perlu dikoreksi atau disembunyikan.\n\n";
		foreach ( $hilang as $h ) {
			$body .= '- ' . $h['judul'] . "\n  " . $h['url'] . "\n  " . admin_url( 'post.php?post=' . $h['id'] . '&action=edit' ) . "\n\n";
		}
		wp_mail( $ke, '[Peta Kendaraan Hilang] ' . count( $hilang ) . ' sumber tidak dapat diakses', $body );
	}

	return array( 'cek' => count( $ids ), 'hilang' => $hilang );
}

/**
 * Cron harian: pantau sumber.
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'kh_harian_sumber' ) ) {
		wp_schedule_event( time() + 5400, 'daily', 'kh_harian_sumber' );
	}
} );
add_action( 'kh_harian_sumber', function () {
	kh_cek_sumber_semua( 20 );
} );

/**
 * Tombol manual + ringkasan di daftar laporan.
 */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kendaraan' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$url = wp_nonce_url( admin_url( 'admin-post.php?action=kh_sumber_now' ), 'kh_sumber_now' );
	echo '<div class="notice notice-info"><p><strong>Pemantau sumber</strong> berjalan tiap hari: artikel sumber dicek, '
		. 'kalau tidak bisa diakses (404/410) admin diberi email agar laporannya dikoreksi/disembunyikan. '
		. 'Jalankan sekarang: <a href="' . esc_url( $url ) . '">cek sumber hari ini</a>.</p></div>';

	if ( isset( $_GET['kh_sumber'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$n = absint( $_GET['kh_sumber'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$h = isset( $_GET['kh_sumber_hilang'] ) ? absint( $_GET['kh_sumber_hilang'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		printf(
			'<div class="notice notice-success is-dismissible"><p>%d sumber diperiksa, %d tidak dapat diakses.</p></div>',
			(int) $n,
			(int) $h
		);
	}
} );

add_action( 'admin_post_kh_sumber_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak punya izin.' );
	}
	check_admin_referer( 'kh_sumber_now' );

	$res  = kh_cek_sumber_semua( 20 );
	$back = admin_url( 'edit.php?post_type=kendaraan' );

	wp_safe_redirect(
		add_query_arg(
			array(
				'kh_sumber'        => (int) $res['cek'],
				'kh_sumber_hilang' => count( $res['hilang'] ),
			),
			$back
		)
	);
	exit;
} );

/**
 * View "Sumber tidak ditemukan" di daftar laporan.
 */
add_filter( 'views_edit-kendaraan', function ( $views ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'kh_sumber_status',
					'value' => 'hilang',
				),
			),
		)
	);

	$url   = admin_url( 'edit.php?post_type=kendaraan&kh_sumber_hilang=1' );
	$aktif = isset( $_GET['kh_sumber_hilang'] ) && '1' === $_GET['kh_sumber_hilang']; // phpcs:ignore WordPress.Security.NonceVerification

	$views['kh_sumber_hilang'] = sprintf(
		'<a href="%s"%s>Sumber tidak ditemukan <span class="count">(%d)</span></a>',
		esc_url( $url ),
		$aktif ? ' class="current"' : '',
		(int) $q->found_posts
	);

	return $views;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'kendaraan' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( ! isset( $_GET['kh_sumber_hilang'] ) || '1' !== $_GET['kh_sumber_hilang'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$query->set(
		'meta_query',
		array(
			array(
				'key'   => 'kh_sumber_status',
				'value' => 'hilang',
			),
		)
	);
} );
