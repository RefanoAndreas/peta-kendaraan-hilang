<?php
/**
 * Custom post type + taxonomy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrasi sekali jalan: plat penuh (tanpa sensor) + koordinat lokasi yang benar.
 */
add_action( 'init', 'kh_migrate_data_v2' );
function kh_migrate_data_v2() {
	if ( get_option( 'kh_mig_data_v2' ) ) {
		return;
	}
	update_option( 'kh_mig_data_v2', 1 );

	$fix = array(
		'hilang-beat-2022-abu-abu-gelap-di-warung-kopi-jalan-lontar'  => array( -7.28773, 112.66264 ),
		'hilang-scoopy-2019-merah-di-kawasan-ampel-mulia'             => array( -7.22982, 112.74352 ),
		'hilang-beat-2016-putih-strip-biru-di-sunandar-prijosoedarmo' => array( -7.41067, 112.57846 ),
		'hilang-beat-2020-hitam-standar-di-perumahan-sedati'          => array( -7.36687, 112.78323 ),
	);

	$q = new WP_Query(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $q->posts as $pid ) {
		$plat = get_post_meta( $pid, 'kh_plat', true );
		if ( $plat ) {
			update_post_meta( $pid, 'kh_plat_masked', kh_normalize_plat( $plat ) );
		}
		$slug = get_post_field( 'post_name', $pid );
		if ( isset( $fix[ $slug ] ) ) {
			update_post_meta( $pid, 'kh_lat', $fix[ $slug ][0] );
			update_post_meta( $pid, 'kh_lng', $fix[ $slug ][1] );
		}
	}
}

function kh_register_cpt() {
	register_post_type(
		'kendaraan',
		array(
			'labels'       => array(
				'name'               => 'Kendaraan Hilang',
				'singular_name'      => 'Kendaraan',
				'menu_name'          => 'Kendaraan Hilang',
				'add_new'            => 'Tambah Laporan',
				'add_new_item'       => 'Tambah Laporan Kendaraan',
				'edit_item'          => 'Edit Laporan Kendaraan',
				'new_item'           => 'Laporan Baru',
				'view_item'          => 'Lihat Laporan',
				'search_items'       => 'Cari Kendaraan',
				'not_found'          => 'Belum ada laporan',
				'not_found_in_trash' => 'Tidak ada laporan di sampah',
			),
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'kendaraan', 'with_front' => false ),
			'menu_icon'    => 'dashicons-car',
			'menu_position' => 5,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'show_in_rest' => true,
			'rest_base'    => 'kendaraan',
		)
	);
}

function kh_register_taxonomies() {
	register_taxonomy(
		'kendaraan_jenis',
		'kendaraan',
		array(
			'labels'            => array(
				'name'          => 'Jenis Kendaraan',
				'singular_name' => 'Jenis',
				'add_new_item'  => 'Tambah Jenis',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'jenis' ),
		)
	);

	register_taxonomy(
		'kendaraan_wilayah',
		'kendaraan',
		array(
			'labels'            => array(
				'name'          => 'Wilayah',
				'singular_name' => 'Wilayah',
				'add_new_item'  => 'Tambah Wilayah',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'wilayah' ),
		)
	);

	register_taxonomy(
		'kendaraan_status',
		'kendaraan',
		array(
			'labels'            => array(
				'name'          => 'Status',
				'singular_name' => 'Status',
				'add_new_item'  => 'Tambah Status',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'status' ),
		)
	);
}

/**
 * Term default (hanya menambah kalau belum ada).
 */
function kh_seed_terms() {
	$defaults = array(
		'kendaraan_jenis'    => array(
			'Motor' => array(),
			'Mobil' => array(),
			'Truk'  => array(),
			'Lain'  => array(),
		),
		'kendaraan_wilayah'  => array(
			'Surabaya' => array(),
			'Sidoarjo' => array(),
			'Gresik'   => array(),
			'Jombang'  => array(),
			'Pasuruan' => array(),
			'Probolinggo' => array(),
			'Lainnya'  => array(),
		),
		'kendaraan_status'   => array(
			'Hilang'   => array(),
			'Ditemukan' => array(),
		),
	);

	foreach ( $defaults as $tax => $terms ) {
		foreach ( $terms as $name => $args ) {
			if ( ! term_exists( $name, $tax ) ) {
				wp_insert_term( $name, $tax, $args );
			}
		}
	}
}

/**
 * Buat halaman depan "Peta Kendaraan Hilang" + shortcode, set sebagai front page.
 */
function kh_seed_front_page() {
	$slug = 'peta-kendaraan-hilang';
	$page = get_page_by_path( $slug );

	if ( ! $page ) {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'Peta Kendaraan Hilang',
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => "[peta_kendaraan height=\"640\"]\n\n[tabel_kendaraan limit=\"20\"]",
			)
		);
	} else {
		$page_id = $page->ID;
	}

	if ( ! $page_id || is_wp_error( $page_id ) ) {
		return;
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_id );
	update_option( 'blogname', get_option( 'blogname' ) );
	update_option( 'blogdescription', 'Peta laporan kehilangan kendaraan di Jawa Timur' );
}

/**
 * Contoh data awal (bisa dihapus admin kapan saja).
 */
function kh_seed_demo_items() {
	if ( get_option( 'kh_demo_seeded' ) ) {
		return;
	}
	update_option( 'kh_demo_seeded', 1 );

	$items = array(
		array(
			'title'    => 'Hilang Beat 2022 Abu-abu Gelap di Warung Kopi Jalan Lontar',
			'plat'     => 'L 3183 DAC',
			'merk'     => 'Honda',
			'model'    => 'Beat',
			'tahun'    => '2022',
			'warna'    => 'Abu-abu gelap',
			'lokasi'   => 'Warung kopi Jalan Lontar, Surabaya',
			'tanggal'  => '2026-11-05',
			'jam'      => '21:00',
			'jenis'    => 'Motor',
			'wilayah'  => 'Surabaya',
			'lat'      => -7.28773,
			'lng'      => 112.66264,
			'polsek'   => '',
			'sumber_nama' => 'Suara Surabaya FM 100 — poster laporan kehilangan',
			'sumber_url'  => '',
		),
		array(
			'title'    => 'Hilang Scoopy 2019 Merah di Kawasan Ampel Mulia',
			'plat'     => 'L 6692 DD',
			'merk'     => 'Honda',
			'model'    => 'Scoopy',
			'tahun'    => '2019',
			'warna'    => 'Merah',
			'lokasi'   => 'Kawasan Ampel Mulia, Surabaya',
			'tanggal'  => '2026-11-05',
			'jam'      => '18:00',
			'jenis'    => 'Motor',
			'wilayah'  => 'Surabaya',
			'lat'      => -7.22982,
			'lng'      => 112.74352,
			'polsek'   => 'Polres Pelabuhan Tanjung Perak',
			'sumber_nama' => 'Suara Surabaya FM 100 — poster laporan kehilangan',
			'sumber_url'  => '',
		),
		array(
			'title'    => 'Hilang Beat 2016 Putih Strip Biru di Sunandar Prijosoedarmo',
			'plat'     => 'W 6469 XK',
			'merk'     => 'Honda',
			'model'    => 'Beat',
			'tahun'    => '2016',
			'warna'    => 'Putih strip biru',
			'lokasi'   => 'Jalan Gubernur Sunandar Prijosoedarmo, Sidoarjo',
			'tanggal'  => '2026-11-06',
			'jam'      => '09:10',
			'jenis'    => 'Motor',
			'wilayah'  => 'Sidoarjo',
			'lat'      => -7.41067,
			'lng'      => 112.57846,
			'polsek'   => 'Polsek Krian',
			'sumber_nama' => 'Suara Surabaya FM 100 — poster laporan kehilangan',
			'sumber_url'  => '',
		),
		array(
			'title'    => 'Hilang Beat 2020 Hitam Standar di Perumahan Sedati',
			'plat'     => 'W 3626 WT',
			'merk'     => 'Honda',
			'model'    => 'Beat',
			'tahun'    => '2020',
			'warna'    => 'Hitam',
			'lokasi'   => 'Jalan Durian, Pranti, Sedati, Sidoarjo',
			'tanggal'  => '2026-11-06',
			'jam'      => '10:30',
			'jenis'    => 'Motor',
			'wilayah'  => 'Sidoarjo',
			'lat'      => -7.36687,
			'lng'      => 112.78323,
			'polsek'   => 'Polsek Sedati',
			'sumber_nama' => 'Suara Surabaya FM 100 — poster laporan kehilangan',
			'sumber_url'  => '',
		),
	);

	foreach ( $items as $it ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'kendaraan',
				'post_status'  => 'publish',
				'post_title'   => $it['title'],
				'post_content' => '',
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}
		kh_save_meta_values( $post_id, $it );
		wp_set_object_terms( $post_id, $it['jenis'], 'kendaraan_jenis' );
		wp_set_object_terms( $post_id, $it['wilayah'], 'kendaraan_wilayah' );
		wp_set_object_terms( $post_id, 'Hilang', 'kendaraan_status' );
	}
}
