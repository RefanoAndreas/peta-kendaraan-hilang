<?php
/**
 * Structured data (JSON-LD) supaya Google paham isi halaman.
 *
 * - Beranda: WebSite
 * - Halaman wilayah / arsip: CollectionPage + BreadcrumbList
 * - Detail laporan: Vehicle + Place + BreadcrumbList
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'kh_print_schema', 20 );

function kh_print_schema() {
	$graph = array();
	$base  = home_url( '/' );
	$name  = get_bloginfo( 'name' );

	$graph[] = array(
		'@type' => 'WebSite',
		'@id'   => $base . '#website',
		'url'   => $base,
		'name'  => $name,
		'description' => get_bloginfo( 'description' ),
		'inLanguage'  => 'id-ID',
	);

	if ( is_singular( 'kendaraan' ) ) {
		$id      = get_the_ID();
		$plat    = get_post_meta( $id, 'kh_plat_masked', true );
		$merk    = get_post_meta( $id, 'kh_merk', true );
		$model   = get_post_meta( $id, 'kh_model', true );
		$tahun   = get_post_meta( $id, 'kh_tahun', true );
		$warna   = get_post_meta( $id, 'kh_warna', true );
		$lokasi  = get_post_meta( $id, 'kh_lokasi', true );
		$lat     = get_post_meta( $id, 'kh_lat', true );
		$lng     = get_post_meta( $id, 'kh_lng', true );
		$tanggal = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
		$wilayah = wp_get_post_terms( $id, 'kendaraan_wilayah', array( 'fields' => 'names' ) );
		$status  = wp_get_post_terms( $id, 'kendaraan_status', array( 'fields' => 'names' ) );

		$vehicle = array(
			'@type'       => 'Vehicle',
			'@id'         => get_permalink() . '#vehicle',
			'name'        => trim( $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) ) . ( $plat ? ' (' . $plat . ')' : '' ),
			'description' => wp_strip_all_tags( get_the_excerpt() ? get_the_excerpt() : get_the_title() ),
			'url'         => get_permalink(),
			'inLanguage'  => 'id-ID',
		);

		if ( $merk ) {
			$vehicle['brand'] = array(
				'@type' => 'Brand',
				'name'  => $merk,
			);
		}
		if ( $model ) {
			$vehicle['model'] = $model;
		}
		if ( $tahun ) {
			$vehicle['vehicleModelDate'] = (string) $tahun;
		}
		if ( $warna ) {
			$vehicle['color'] = $warna;
		}
		if ( $plat ) {
			$vehicle['vehicleIdentificationNumber'] = $plat; // plat nomor sebagai pengenal
			$vehicle['additionalProperty']           = array(
				array(
					'@type' => 'PropertyValue',
					'name'  => 'Nomor polisi',
					'value' => $plat,
				),
			);
		}
		if ( $lokasi || ( $lat && $lng ) ) {
			$place = array(
				'@type' => 'Place',
				'name'  => $lokasi ? $lokasi : 'Jawa Timur',
			);
			if ( $lat && $lng ) {
				$place['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $lat,
					'longitude' => (float) $lng,
				);
			}
			$vehicle['locationCreated'] = $place;
		}

		$page = array(
			'@type'      => 'WebPage',
			'@id'        => get_permalink() . '#webpage',
			'url'        => get_permalink(),
			'name'       => get_the_title(),
			'isPartOf'   => array( '@id' => $base . '#website' ),
			'inLanguage' => 'id-ID',
			'mainEntity' => array( '@id' => get_permalink() . '#vehicle' ),
		);
		if ( $tanggal ) {
			$page['datePublished'] = gmdate( 'c', strtotime( $tanggal ) );
		}
		if ( $status ) {
			$page['about'] = strtoupper( $status[0] );
		}

		$graph[] = $page;
		$graph[] = $vehicle;

		$crumbs = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => 'Peta Kendaraan Hilang',
				'item'     => $base,
			),
		);
		if ( $wilayah ) {
			$term  = get_term_by( 'name', $wilayah[0], 'kendaraan_wilayah' );
			$pos   = 2;
			if ( $term && ! is_wp_error( $term ) ) {
				$crumbs[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $wilayah[0],
					'item'     => get_term_link( $term ),
				);
			}
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => $plat ? $plat : get_the_title(),
				'item'     => get_permalink(),
			);
		} else {
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => $plat ? $plat : get_the_title(),
				'item'     => get_permalink(),
			);
		}

		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink() . '#breadcrumb',
			'itemListElement' => $crumbs,
		);
	}

	if ( is_tax( 'kendaraan_kecamatan' ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$stat    = kh_kec_statistik( $term );
			$wilayah = get_term_by( 'slug', 'surabaya', 'kendaraan_wilayah' );

			$graph[] = array(
				'@type'       => 'CollectionPage',
				'@id'         => get_term_link( $term ) . '#collection',
				'url'         => get_term_link( $term ),
				'name'        => 'Motor & Mobil Hilang di Kecamatan ' . $term->name . ', Surabaya',
				'description' => sprintf( '%d laporan kehilangan kendaraan di Kecamatan %s, Surabaya.', (int) $stat['jumlah'], $term->name ),
				'isPartOf'    => array( '@id' => $base . '#website' ),
				'inLanguage'  => 'id-ID',
			);

			$crumbs = array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Peta Kendaraan Hilang',
					'item'     => $base,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => 'Wilayah',
					'item'     => home_url( '/wilayah/' ),
				),
			);
			$pos = 3;
			if ( $wilayah && ! is_wp_error( $wilayah ) ) {
				$crumbs[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => 'Surabaya',
					'item'     => get_term_link( $wilayah ),
				);
			}
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => $term->name,
				'item'     => get_term_link( $term ),
			);

			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $crumbs,
			);
		}
	}

	if ( is_tax( 'kendaraan_wilayah' ) ) {
		$term  = get_queried_object();
		$total = isset( $term->count ) ? (int) $term->count : 0;
		$graph[] = array(
			'@type'      => 'CollectionPage',
			'@id'        => get_term_link( $term ) . '#collection',
			'url'        => get_term_link( $term ),
			'name'       => 'Kendaraan Hilang di ' . $term->name,
			'description' => sprintf( '%d laporan kehilangan kendaraan di %s, lengkap dengan nomor polisi dan status verifikasi.', $total, $term->name ),
			'isPartOf'   => array( '@id' => $base . '#website' ),
			'inLanguage' => 'id-ID',
		);
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Peta Kendaraan Hilang',
					'item'     => $base,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $term->name,
					'item'     => get_term_link( $term ),
				),
			),
		);
	}

	if ( ! $graph ) {
		return;
	}

	echo "\n<script type=\"application/ld+json\">";
	echo wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	echo "</script>\n";
}
