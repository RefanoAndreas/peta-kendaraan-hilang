<?php
/**
 * REST API untuk peta & pencarian.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'KH_ARSIP_HARI' ) ) {
	define( 'KH_ARSIP_HARI', 180 );
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
				'jenis'      => array( 'sanitize_callback' => 'sanitize_title' ),
				'wilayah'    => array( 'sanitize_callback' => 'sanitize_title' ),
				'status'     => array( 'sanitize_callback' => 'sanitize_title' ),
				'verifikasi' => array( 'sanitize_callback' => 'sanitize_key' ),
				'arsip'      => array( 'sanitize_callback' => 'rest_sanitize_boolean' ),
				'limit'      => array( 'sanitize_callback' => 'absint' ),
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

	$verif = $req->get_param( 'verifikasi' );
	if ( $verif && in_array( $verif, array( 'warga', 'media', 'polisi' ), true ) ) {
		$args['meta_query'][] = array(
			'key'   => 'kh_verifikasi',
			'value' => $verif,
		);
	}

	$q       = new WP_Query( $args );
	$items   = array();
	$hari    = KH_ARSIP_HARI;
	$today   = current_time( 'Y-m-d' );
	$arsip_q = $req->get_param( 'arsip' );

	foreach ( $q->posts as $p ) {
		$lat = get_post_meta( $p->ID, 'kh_lat', true );
		$lng = get_post_meta( $p->ID, 'kh_lng', true );
		if ( '' === $lat || '' === $lng ) {
			continue;
		}

		$tanggal = kh_normalize_tanggal( get_post_meta( $p->ID, 'kh_tanggal', true ) );
		$acuan   = $tanggal ? $tanggal : substr( $p->post_date, 0, 10 );
		$umur    = (int) floor( ( strtotime( $today ) - strtotime( $acuan ) ) / 86400 );
		$arsip   = $umur > $hari;

		if ( $arsip && ! $arsip_q ) {
			continue;
		}

		$jenis   = wp_get_post_terms( $p->ID, 'kendaraan_jenis', array( 'fields' => 'names' ) );
		$wilayah = wp_get_post_terms( $p->ID, 'kendaraan_wilayah', array( 'fields' => 'names' ) );
		$status  = wp_get_post_terms( $p->ID, 'kendaraan_status', array( 'fields' => 'names' ) );
		$verif_v = get_post_meta( $p->ID, 'kh_verifikasi', true );
		$verif_v = $verif_v ? $verif_v : 'warga';

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
			'verifikasi'  => $verif_v,
			'arsip'       => $arsip,
			'umur_hari'   => $umur,
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
	$total     = wp_count_posts( 'kendaraan' );
	$published = isset( $total->publish ) ? (int) $total->publish : 0;
	$wilayah   = get_terms(
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

/* ------------------------------------------------------------------
 * REST: cari nama jalan / tempat (geocoding untuk form pelapor)
 * Photon (komoot) sebagai sumber utama, Nominatim sebagai cadangan.
 * Di-cache 1 jam supaya tidak membanjiri layanan gratis.
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route(
		'kh/v1',
		'/cari',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'kh_rest_cari',
			'args'                => array(
				'q' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
} );

function kh_rest_cari( WP_REST_Request $req ) {
	$q = trim( (string) $req->get_param( 'q' ) );
	if ( mb_strlen( $q ) < 3 ) {
		return new WP_REST_Response( array( 'items' => array() ), 200 );
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	$key = 'kh_geo_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n > 40 ) {
		return new WP_Error( 'kh_rate', 'Terlalu banyak pencarian. Tunggu sebentar.', array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, MINUTE_IN_SECONDS );

	$cache = 'kh_geo3_' . md5( mb_strtolower( $q ) );
	$hit   = get_transient( $cache );
	if ( is_array( $hit ) && isset( $hit['items'] ) ) {
		return new WP_REST_Response( $hit, 200 );
	}

	/* "Jl. Rungkut Asri" gagal di Nominatim, "Rungkut Asri" berhasil → buang prefiks jalan. */
	$qs = preg_replace( '/^\s*(jl\.?|jln\.?|jalan)\s+/i', '', $q );

	$items  = array();
	$sumber = 'osm';
	$galat  = '';

	/* Mesin utama: Google Places (dipakai kalau API key diisi di Pengaturan). */
	if ( function_exists( 'kh_google_aktif' ) && kh_google_aktif() ) {
		$g = kh_google_cari( $q );
		if ( is_wp_error( $g ) ) {
			$galat = $g->get_error_message();
		} elseif ( $g ) {
			$items  = $g;
			$sumber = 'google';
		}
	}

	/* Cadangan: Photon + Nominatim (OpenStreetMap), juga saat key kosong/galat. */
	if ( ! $items ) {
		$items = kh_geocode_gabung( $qs );
		if ( ! $items && $qs !== $q ) {
			$items = kh_geocode_gabung( $q );
		}
	}

	$payload = array(
		'items'  => $items,
		'sumber' => $sumber,
	);
	if ( '' !== $galat ) {
		$payload['galat_google'] = $galat;
	}

	/* hasil kosong disimpan singkat saja, supaya salah ketik tidak "mengunci" pencarian */
	set_transient( $cache, $payload, $items ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS );

	return new WP_REST_Response( $payload, 200 );
}

/**
 * Gabungkan Photon + Nominatim (Photon lemah untuk alamat Indonesia).
 */
function kh_geocode_gabung( $q ) {
	$items = kh_geocode_photon( $q );

	if ( count( $items ) < 3 ) {
		$known = array();
		foreach ( $items as $it ) {
			$known[ $it['label'] ] = true;
		}
		foreach ( kh_geocode_nominatim( $q ) as $it ) {
			if ( isset( $known[ $it['label'] ] ) ) {
				continue;
			}
			$items[] = $it;
			if ( count( $items ) >= 8 ) {
				break;
			}
		}
	}

	return $items;
}

function kh_geocode_photon( $q ) {
	$url = add_query_arg(
		array(
			'q'                  => $q,
			'limit'              => 6,
			'lat'                => -7.30,
			'lon'                => 112.72,
			'location_bias_scale' => 0.35,
			'zoom'               => 11,
		),
		'https://photon.komoot.io/api/'
	);

	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 9,
			'headers' => array( 'User-Agent' => 'PetaKendaraanHilang/1.0 (+https://hilang.refanoandreas.com)' ),
		)
	);

	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['features'] ) || ! is_array( $body['features'] ) ) {
		return array();
	}

	$out = array();
	foreach ( $body['features'] as $f ) {
		$c = isset( $f['geometry']['coordinates'] ) ? $f['geometry']['coordinates'] : null;
		if ( ! $c || ! isset( $c[0], $c[1] ) ) {
			continue;
		}
		$p    = isset( $f['properties'] ) ? $f['properties'] : array();
		$main = '';
		if ( ! empty( $p['name'] ) ) {
			$main = $p['name'];
		} elseif ( ! empty( $p['street'] ) ) {
			$main = $p['street'] . ( ! empty( $p['housenumber'] ) ? ' ' . $p['housenumber'] : '' );
		}
		if ( '' === $main ) {
			continue;
		}
		$tail = array();
		foreach ( array( 'district', 'city', 'county', 'state' ) as $k ) {
			if ( ! empty( $p[ $k ] ) && ! in_array( $p[ $k ], $tail, true ) && $p[ $k ] !== $main ) {
				$tail[] = $p[ $k ];
			}
		}
		$tail = array_slice( array_unique( $tail ), 0, 3 );

		$out[] = array(
			'label' => $main . ( $tail ? ' · ' . implode( ', ', $tail ) : '' ),
			'kota'  => isset( $p['city'] ) ? $p['city'] : ( isset( $p['county'] ) ? $p['county'] : '' ),
			'lat'   => round( (float) $c[1], 6 ),
			'lng'   => round( (float) $c[0], 6 ),
		);
		if ( count( $out ) >= 6 ) {
			break;
		}
	}

	return $out;
}

function kh_geocode_nominatim( $q ) {
	$url = add_query_arg(
		array(
			'q'            => $q,
			'format'       => 'jsonv2',
			'limit'        => 6,
			'countrycodes' => 'id',
			'accept-language' => 'id',
			'viewbox'      => '111.4,-8.6,114.8,-6.4',
			'bounded'      => 0,
		),
		'https://nominatim.openstreetmap.org/search'
	);

	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 9,
			'headers' => array( 'User-Agent' => 'PetaKendaraanHilang/1.0 (+https://hilang.refanoandreas.com)' ),
		)
	);

	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $body ) ) {
		return array();
	}

	$out = array();
	foreach ( $body as $r ) {
		if ( empty( $r['lat'] ) || empty( $r['lon'] ) ) {
			continue;
		}
		$out[] = array(
			'label' => isset( $r['display_name'] ) ? $r['display_name'] : (string) $r['lat'],
			'kota'  => '',
			'lat'   => round( (float) $r['lat'], 6 ),
			'lng'   => round( (float) $r['lon'], 6 ),
		);
	}
	return $out;
}
