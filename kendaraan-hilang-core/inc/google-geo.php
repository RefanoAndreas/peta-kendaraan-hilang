<?php
/**
 * Pencarian lokasi lewat Google Places API (New).
 *
 * Dipakai sebagai mesin utama kotak "Cari nama jalan" di admin (layar edit &
 * edit cepat). Kalau API key kosong atau Google membalas galat, pemanggil
 * otomatis jatuh kembali ke Photon + Nominatim (OpenStreetMap).
 *
 * Catatan biaya/kuota: Places API (New) Text Search **Pro** punya jatah gratis
 * 5.000 panggilan/bulan (sejak 1 Mar 2025, pengganti kredit USD 200/bulan; SKU
 * Text Search "IDs only" gratis tanpa batas tapi tidak memberi koordinat);
 * sesudah kuota USD 32/1.000 panggilan. Hasil pencarian di-cache 1 jam di
 * transient supaya panggilan tidak berulang untuk kata kunci yang sama.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API key Google Maps Platform (disimpan di layar Pengaturan).
 */
function kh_google_key() {
	return trim( (string) get_option( 'kh_google_key', '' ) );
}

function kh_google_aktif() {
	return '' !== kh_google_key();
}

/**
 * Cari lokasi lewat Places API (New) — Text Search.
 *
 * @param string $q Kata kunci (nama jalan, POI, atau alamat).
 * @return array|WP_Error Daftar array( 'label', 'lat', 'lng' ).
 */
function kh_google_cari( $q ) {
	$key = kh_google_key();
	if ( '' === $key ) {
		return new WP_Error( 'kh_google_key', 'API key Google belum diisi.' );
	}
	if ( mb_strlen( trim( $q ) ) < 3 ) {
		return array();
	}

	$body = array(
		'textQuery'      => $q,
		'languageCode'   => 'id',
		'regionCode'     => 'ID',
		'maxResultCount' => 6,
		'locationBias'   => array(
			'circle' => array(
				'center' => array(
					'latitude'  => -7.30,
					'longitude' => 112.72,
				),
				'radius' => 45000.0,
			),
		),
	);

	$res = wp_remote_post(
		'https://places.googleapis.com/v1/places:searchText',
		array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type'     => 'application/json',
				'X-Goog-Api-Key'   => $key,
				'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.location',
			),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$kode = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );

	if ( 200 !== $kode ) {
		$pesan = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'HTTP ' . $kode;
		return new WP_Error( 'kh_google_http', $pesan );
	}

	$out = array();
	foreach ( (array) ( isset( $data['places'] ) ? $data['places'] : array() ) as $p ) {
		$lat = isset( $p['location']['latitude'] ) ? (float) $p['location']['latitude'] : null;
		$lng = isset( $p['location']['longitude'] ) ? (float) $p['location']['longitude'] : null;
		if ( null === $lat || null === $lng ) {
			continue;
		}

		$nama   = isset( $p['displayName']['text'] ) ? trim( (string) $p['displayName']['text'] ) : '';
		$alamat = isset( $p['formattedAddress'] ) ? trim( (string) $p['formattedAddress'] ) : '';

		if ( $nama && $alamat && false === stripos( $alamat, $nama ) ) {
			$label = $nama . ' · ' . $alamat;
		} elseif ( $alamat ) {
			$label = $alamat;
		} else {
			$label = $nama;
		}

		$out[] = array(
			'label' => $label,
			'lat'   => round( $lat, 6 ),
			'lng'   => round( $lng, 6 ),
		);
	}

	return $out;
}

/**
 * Uji cepat dari layar Pengaturan: pastikan key valid dan Places API aktif.
 *
 * @return true|WP_Error
 */
function kh_google_tes() {
	$r = kh_google_cari( 'Tunjungan Plaza Surabaya' );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( ! $r ) {
		return new WP_Error( 'kh_google_kosong', 'Google membalas tanpa hasil. Pastikan "Places API (New)" aktif dan billing menyala.' );
	}
	return true;
}
