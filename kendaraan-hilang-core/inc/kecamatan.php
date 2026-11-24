<?php
/**
 * Kecamatan: halaman long-tail `/kecamatan/<slug>/`.
 *
 * Kenapa: teks lokasi laporan hampir tidak pernah menyebut nama kecamatan, padahal
 * pencarian warga justru memakai nama kecamatan ("motor hilang rungkut"). Data 31
 * titik pusat kecamatan Surabaya sudah ada di `inc/data-kecamatan.php` (dipakai
 * halaman analitik), jadi kecamatan ditaksir dari **koordinat laporan**, bukan dari
 * teks: laporan diberi term kecamatan terdekat bila jaraknya ≤ KH_KEC_RADIUS meter
 * dan wilayahnya Surabaya (mencegah laporan Sidoarjo ikut terpasang ke kecamatan
 * Surabaya yang berbatasan).
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Jarak maksimum (meter) untuk menetapkan laporan ke sebuah kecamatan. */
if ( ! defined( 'KH_KEC_RADIUS' ) ) {
	define( 'KH_KEC_RADIUS', 3500 );
}

/**
 * Data pusat kecamatan: 'Nama' => array( region, lat, lng ).
 *
 * @return array
 */
function kh_kecamatan_data() {
	static $data = null;
	if ( null === $data ) {
		$data = include KH_CORE_DIR . 'inc/data-kecamatan.php';
		if ( ! is_array( $data ) ) {
			$data = array();
		}
	}
	return $data;
}

/**
 * Jarak dua titik (meter) — haversine.
 */
function kh_kec_jarak_m( $lat1, $lng1, $lat2, $lng2 ) {
	$r    = 6371000;
	$dla  = deg2rad( $lat2 - $lat1 );
	$dlo  = deg2rad( $lng2 - $lng1 );
	$a    = sin( $dla / 2 ) * sin( $dla / 2 )
		+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlo / 2 ) * sin( $dlo / 2 );
	return (int) round( $r * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) ) );
}

/**
 * Kecamatan terdekat dari sebuah koordinat.
 *
 * @return array{0:string,1:int} Nama kecamatan ('' kalau data kosong) dan jarak meter.
 */
function kh_kec_terdekat( $lat, $lng ) {
	$lat = (float) $lat;
	$lng = (float) $lng;
	$best = '';
	$bd   = PHP_INT_MAX;
	foreach ( kh_kecamatan_data() as $nama => $d ) {
		$dist = kh_kec_jarak_m( $lat, $lng, (float) $d[1], (float) $d[2] );
		if ( $dist < $bd ) {
			$bd   = $dist;
			$best = $nama;
		}
	}
	return array( $best, (int) $bd );
}

/**
 * Apakah laporan ini milik wilayah Surabaya?
 */
function kh_kec_apakah_surabaya( $post_id ) {
	$terms = wp_get_post_terms( $post_id, 'kendaraan_wilayah', array( 'fields' => 'slugs' ) );
	return ( ! is_wp_error( $terms ) && in_array( 'surabaya', $terms, true ) );
}

/**
 * Tetapkan term kecamatan untuk satu laporan (dipanggil saat simpan & migrasi).
 *
 * PENTING: pengecekan wilayah Surabaya TIDAK boleh dilewati (dulu parameter $paksa
 * melewatinya saat migrasi → 18 laporan Sidoarjo ikut mendapat kecamatan Surabaya
 * yang berbatasan, mis. Waru/Kalijaten masuk Karang Pilang). Parameter $paksa
 * sekarang hanya berarti "hitung ulang walau sudah punya term".
 *
 * @return string Nama kecamatan yang dipasang, '' kalau tidak dipasang.
 */
function kh_kec_assign( $post_id, $paksa = false ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || 'kendaraan' !== get_post_type( $post_id ) ) {
		return '';
	}

	$lat = get_post_meta( $post_id, 'kh_lat', true );
	$lng = get_post_meta( $post_id, 'kh_lng', true );
	if ( '' === $lat || '' === $lng ) {
		return '';
	}
	if ( ! kh_kec_apakah_surabaya( $post_id ) ) {
		return '';
	}

	list( $nama, $jarak ) = kh_kec_terdekat( $lat, $lng );
	if ( '' === $nama || $jarak > KH_KEC_RADIUS ) {
		return '';
	}

	$ada = term_exists( $nama, 'kendaraan_kecamatan' );
	if ( ! $ada ) {
		$ada = wp_insert_term( $nama, 'kendaraan_kecamatan', array( 'slug' => sanitize_title( $nama ) ) );
	}
	if ( is_wp_error( $ada ) ) {
		return '';
	}
	$term_id = is_array( $ada ) ? (int) $ada['term_id'] : (int) $ada;

	wp_set_object_terms( $post_id, array( $term_id ), 'kendaraan_kecamatan', false );
	return $nama;
}

/* ------------------------------------------------------------------ */
/* Seed term + migrasi data lama                                       */
/* ------------------------------------------------------------------ */

/**
 * Isi term kecamatan (31) sekali per versi plugin.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_kec_seed' ) === KH_CORE_VERSION ) {
		return;
	}
	if ( ! taxonomy_exists( 'kendaraan_kecamatan' ) ) {
		return;
	}
	foreach ( kh_kecamatan_data() as $nama => $d ) {
		if ( ! term_exists( $nama, 'kendaraan_kecamatan' ) ) {
			wp_insert_term( $nama, 'kendaraan_kecamatan', array( 'slug' => sanitize_title( $nama ) ) );
		}
	}
	update_option( 'kh_kec_seed', KH_CORE_VERSION, false );
} );

/**
 * Migrasi sekali-jalan: pasang term kecamatan untuk laporan lama berdasarkan koordinat.
 *
 * Dibatasi 120 laporan per request admin (aman untuk shared hosting) dan bisa
 * dilanjutkan otomatis pada request berikutnya lewat opsi `kh_kec_offset`.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_kec_migrasi' ) === KH_CORE_VERSION ) {
		return;
	}
	if ( ! taxonomy_exists( 'kendaraan_kecamatan' ) ) {
		return;
	}

	$offset = (int) get_option( 'kh_kec_offset', 0 );
	$ids    = get_posts(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'publish',
			'posts_per_page' => 120,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => 'kh_lat',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	if ( ! $ids ) {
		update_option( 'kh_kec_migrasi', KH_CORE_VERSION, false );
		update_option( 'kh_kec_offset', 0, false );
		return;
	}

	$n = 0;
	foreach ( $ids as $id ) {
		if ( kh_kec_assign( $id, true ) ) {
			$n++;
		}
	}
	update_option( 'kh_kec_offset', $offset + 120, false );

	/* Batch terakhir (kurang dari limit) = migrasi selesai. */
	if ( count( $ids ) < 120 ) {
		update_option( 'kh_kec_migrasi', KH_CORE_VERSION, false );
		update_option( 'kh_kec_offset', 0, false );
	}
} );

/**
 * Pembersihan sekali-jalan: buang term kecamatan dari laporan yang wilayahnya BUKAN
 * Surabaya (sisa bug migrasi 0.45.0 — pengecekan wilayah dulu dilewati saat migrasi,
 * akibatnya 18 laporan Sidoarjo seperti Kalijaten/Waru masuk kecamatan Surabaya yang
 * berbatasan), lalu hitung ulang laporan Surabaya.
 *
 * Memakai kursor ID, bukan offset: baris yang dibersihkan keluar dari hasil query
 * sehingga offset akan melewatkan baris berikutnya.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_kec_bersih' ) === KH_CORE_VERSION ) {
		return;
	}
	if ( ! taxonomy_exists( 'kendaraan_kecamatan' ) ) {
		return;
	}
	if ( get_option( 'kh_kec_migrasi' ) !== KH_CORE_VERSION ) {
		return; /* tunggu migrasi utama selesai dulu */
	}

	global $wpdb;
	$terakhir = (int) get_option( 'kh_kec_bersih_last', 0 );
	$ids      = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 WHERE tt.taxonomy = %s AND p.post_type = 'kendaraan' AND p.ID > %d
			 ORDER BY p.ID ASC
			 LIMIT 120",
			'kendaraan_kecamatan',
			$terakhir
		)
	);

	if ( ! $ids ) {
		update_option( 'kh_kec_bersih', KH_CORE_VERSION, false );
		update_option( 'kh_kec_bersih_last', 0, false );
		return;
	}

	foreach ( $ids as $id ) {
		if ( kh_kec_apakah_surabaya( $id ) ) {
			kh_kec_assign( (int) $id, true );
		} else {
			wp_set_object_terms( (int) $id, array(), 'kendaraan_kecamatan', false );
		}
	}
	update_option( 'kh_kec_bersih_last', (int) max( $ids ), false );
} );

/**
 * Laporan baru: pasang kecamatan setelah meta tersimpan.
 */
add_action( 'acf/save_post', function ( $post_id ) {
	if ( 'kendaraan' === get_post_type( $post_id ) ) {
		kh_kec_assign( $post_id );
	}
}, 30 );
add_action( 'save_post_kendaraan', function ( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	kh_kec_assign( $post_id );
}, 30 );

/* ------------------------------------------------------------------ */
/* Alamat & template                                                   */
/* ------------------------------------------------------------------ */

/**
 * Flush rewrite sekali per versi supaya `/kecamatan/<slug>/` langsung hidup.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_kec_rewrite' ) !== KH_CORE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'kh_kec_rewrite', KH_CORE_VERSION, false );
	}
} );

/**
 * Template halaman kecamatan.
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_tax( 'kendaraan_kecamatan' ) ) {
		$file = KH_CORE_DIR . 'templates/taxonomy-kecamatan.php';
		if ( file_exists( $file ) ) {
			return $file;
		}
	}
	return $template;
} );

/**
 * Statistik ringkas satu kecamatan (dipakai template halaman kecamatan).
 *
 * @param WP_Term $term Term kecamatan.
 * @return array
 */
function kh_kec_statistik( $term ) {
	$cache = get_transient( 'kh_kec_stat_' . $term->term_id );
	if ( is_array( $cache ) ) {
		return $cache;
	}

	$q = new WP_Query(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'kendaraan_kecamatan',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
		)
	);

	$jam    = array();
	$model  = array();
	$baru   = '';
	$hilang = 0;
	foreach ( $q->posts as $p ) {
		$h = substr( (string) get_post_meta( $p->ID, 'kh_jam', true ), 0, 2 );
		if ( ctype_digit( $h ) ) {
			$jam[ $h ] = isset( $jam[ $h ] ) ? $jam[ $h ] + 1 : 1;
		}
		$m = trim( get_post_meta( $p->ID, 'kh_merk', true ) . ' ' . get_post_meta( $p->ID, 'kh_model', true ) );
		if ( $m ) {
			$model[ $m ] = isset( $model[ $m ] ) ? $model[ $m ] + 1 : 1;
		}
		$t = kh_normalize_tanggal( get_post_meta( $p->ID, 'kh_tanggal', true ) );
		if ( $t && $t > $baru ) {
			$baru = $t;
		}
		$hilang++;
	}

	arsort( $jam );
	arsort( $model );

	$out = array(
		'jumlah'    => max( (int) $term->count, count( $q->posts ) ),
		'jam'       => $jam,
		'model'     => $model,
		'terbaru'   => $baru,
		'tahun_max' => $baru ? date_i18n( 'Y', strtotime( $baru ) ) : '',
	);
	set_transient( 'kh_kec_stat_' . $term->term_id, $out, HOUR_IN_SECONDS * 3 );
	return $out;
}

/**
 * Bersihkan cache statistik kecamatan saat ada laporan baru/berubah.
 */
add_action( 'save_post_kendaraan', function ( $post_id ) {
	$terms = wp_get_post_terms( $post_id, 'kendaraan_kecamatan' );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			delete_transient( 'kh_kec_stat_' . $t->term_id );
		}
	}
}, 40 );
