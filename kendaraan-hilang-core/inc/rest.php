<?php
/**
 * REST API untuk peta & pencarian.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'kh/v1',
		'/peta',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'kh_rest_peta',
			'args'                => array(
				'jenis'   => array( 'sanitize_callback' => 'sanitize_title' ),
				'wilayah' => array( 'sanitize_callback' => 'sanitize_title' ),
				'status'  => array( 'sanitize_callback' => 'sanitize_title' ),
				'limit'   => array( 'sanitize_callback' => 'absint' ),
			),
		)
	);

	register_rest_route(
		'kh/v1',
		'/ringkasan',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'kh_rest_ringkasan',
		)
	);
} );

function kh_rest_peta( WP_REST_Request $req ) {
	$limit = $req->get_param( 'limit' );
	$limit = $limit ? min( (int) $limit, 1000 ) : 500;

	$args = array(
		'post_type'      => 'kendaraan',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'     => 'kh_lat',
				'value'   => '',
				'compare' => '!=',
			),
		),
		'tax_query'      => array(),
	);

	foreach ( array( 'jenis' => 'kendaraan_jenis', 'wilayah' => 'kendaraan_wilayah', 'status' => 'kendaraan_status' ) as $param => $tax ) {
		$val = $req->get_param( $param );
		if ( $val ) {
			$args['tax_query'][] = array(
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $val,
			);
		}
	}

	$q     = new WP_Query( $args );
	$items = array();

	foreach ( $q->posts as $p ) {
		$lat = get_post_meta( $p->ID, 'kh_lat', true );
		$lng = get_post_meta( $p->ID, 'kh_lng', true );
		if ( '' === $lat || '' === $lng ) {
			continue;
		}
		$tanggal = kh_normalize_tanggal( get_post_meta( $p->ID, 'kh_tanggal', true ) );
		$jenis   = wp_get_post_terms( $p->ID, 'kendaraan_jenis', array( 'fields' => 'names' ) );
		$wilayah = wp_get_post_terms( $p->ID, 'kendaraan_wilayah', array( 'fields' => 'names' ) );
		$status  = wp_get_post_terms( $p->ID, 'kendaraan_status', array( 'fields' => 'names' ) );

		$items[] = array(
			'id'          => $p->ID,
			'judul'       => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'plat'        => get_post_meta( $p->ID, 'kh_plat_masked', true ),
			'merk'        => get_post_meta( $p->ID, 'kh_merk', true ),
			'model'       => get_post_meta( $p->ID, 'kh_model', true ),
			'tahun'       => get_post_meta( $p->ID, 'kh_tahun', true ),
			'warna'       => get_post_meta( $p->ID, 'kh_warna', true ),
			'tanggal'     => $tanggal,
			'jam'         => get_post_meta( $p->ID, 'kh_jam', true ),
			'lokasi'      => get_post_meta( $p->ID, 'kh_lokasi', true ),
			'lat'         => (float) $lat,
			'lng'         => (float) $lng,
			'jenis'       => $jenis ? $jenis[0] : '',
			'wilayah'     => $wilayah ? $wilayah[0] : '',
			'status'      => $status ? $status[0] : 'Hilang',
			'polsek'      => get_post_meta( $p->ID, 'kh_polsek', true ),
			'ciri'        => get_post_meta( $p->ID, 'kh_ciri', true ),
			'url'         => get_permalink( $p ),
			'sumber_nama' => get_post_meta( $p->ID, 'kh_sumber_nama', true ),
			'sumber_url'  => get_post_meta( $p->ID, 'kh_sumber_url', true ),
		);
	}

	$res = new WP_REST_Response(
		array(
			'count' => count( $items ),
			'items' => $items,
		),
		200
	);
	$res->header( 'Cache-Control', 'public, max-age=60' );
	return $res;
}

function kh_rest_ringkasan() {
	$total    = wp_count_posts( 'kendaraan' );
	$published = isset( $total->publish ) ? (int) $total->publish : 0;
	$wilayah  = get_terms(
		array(
			'taxonomy'   => 'kendaraan_wilayah',
			'hide_empty' => true,
			'fields'     => 'names',
		)
	);

	return new WP_REST_Response(
		array(
			'total'   => $published,
			'wilayah' => is_wp_error( $wilayah ) ? array() : $wilayah,
		),
		200
	);
}
