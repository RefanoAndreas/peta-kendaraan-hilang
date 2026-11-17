<?php
/**
 * Koordinat dari URL Google Maps yang ditempel admin.
 *
 * Kenapa ada: Google Places API butuh billing account (di Indonesia kena
 * prepayment IDR 500.000 untuk aktivasi free trial). Jalur ini tidak butuh
 * API key sama sekali — admin mencari sendiri lokasinya di Google Maps, lalu
 * menempel URL-nya, dan koordinatnya dibaca dari URL itu.
 *
 * Format URL yang dikenali (urutan prioritas):
 *   1. ...!3d-7.2593!4d112.7389...        → koordinat pin asli (paling akurat)
 *   2. .../@-7.2593,112.7389,17z           → titik tengah peta
 *   3. ...?q=-7.2593,112.7389 / ll= / sll= / query= / daddr= / center=
 *   4. teks polos "-7.2593, 112.7389"
 * Link pendek (maps.app.goo.gl, goo.gl/maps) diurai dulu lewat redirect
 * server-side (JS di browser tidak bisa karena CORS).
 *
 * Catatan kepatuhan: kita hanya membaca angka yang sudah ada di URL, tidak
 * memanggil layanan Google dan tidak menyimpan apa pun dari Google selain
 * koordinat yang admin pilih sendiri. Tidak ada atribusi yang dilanggar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ambil URL pertama dari teks yang ditempel (mis. share text dari HP).
 *
 * @param string $teks Tempelan mentah.
 * @return string URL atau string kosong.
 */
function kh_url_dari_teks( $teks ) {
	$teks = (string) $teks;

	if ( preg_match( '#https?://[^\s<>"\']+#i', $teks, $m ) ) {
		return $m[0];
	}

	/* tempelan tanpa skema: "maps.app.goo.gl/abc", "www.google.com/maps/..." */
	if ( preg_match( '#(?:^|\s)((?:www\.)?(?:google\.[a-z.]+|goo\.gl|g\.co|maps\.app\.goo\.gl)/[^\s<>"\']*)#i', $teks, $m ) ) {
		$calon = ltrim( $m[1], '/' );

		/* "google.com/maps/..." tanpa www tetap sah */
		return 'https://' . $calon;
	}

	return '';
}

/**
 * Uraikan link pendek Google (maps.app.goo.gl / goo.gl/maps) jadi URL panjang.
 *
 * @param string $url URL yang mungkin pendek.
 * @return string URL panjang (atau URL asli kalau gagal/ bukan link pendek).
 */
function kh_url_perpanjang( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$pendek = (
		'goo.gl' === $host
		|| 'maps.app.goo.gl' === $host
		|| 'g.co' === $host
		|| '.goo.gl' === substr( $host, -7 )
	);

	if ( ! $pendek ) {
		return $url;
	}

	$sekarang = $url;

	for ( $i = 0; $i < 5; $i++ ) {
		$res = wp_remote_get(
			$sekarang,
			array(
				'timeout'     => 8,
				'redirection' => 0, /* redirect diurus sendiri supaya URL akhirnya kebaca */
				'headers'     => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; PetaKendaraanHilang/1.0; +https://hilang.refanoandreas.com)',
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			break;
		}

		$kode = (int) wp_remote_retrieve_response_code( $res );
		if ( $kode < 300 || $kode >= 400 ) {
			break;
		}

		$tujuan = trim( (string) wp_remote_retrieve_header( $res, 'location' ) );
		if ( '' === $tujuan ) {
			break;
		}
		if ( 0 === strpos( $tujuan, '/' ) ) {
			$tujuan = 'https://www.google.com' . $tujuan;
		}

		$sekarang = $tujuan;

		/* sudah membawa koordinat → tidak perlu ikut redirect lagi */
		if ( preg_match( '/!3d-?\d|@-?\d/', $sekarang ) ) {
			break;
		}
	}

	return $sekarang;
}

/**
 * Nama tempat dari URL Google Maps (kalau ada).
 *
 * @param string $url URL panjang.
 * @return string Nama tempat yang bisa dipakai sebagai "Lokasi kejadian".
 */
function kh_nama_dari_url_google( $url ) {
	$url = (string) $url;

	/* .../maps/place/Masjid+Moch.+Abdullah+Muchtar/@-7.2,...
	   .../maps/place/Jl.+Rungkut+Asri/@...,17z/data=... */
	if ( preg_match( '#/maps/place/([^/@?]+)#i', $url, $m ) ) {
		$nama = rawurldecode( str_replace( '+', ' ', $m[1] ) );
		$nama = trim( preg_replace( '/\s+/u', ' ', $nama ) );
		if ( '' !== $nama && ! preg_match( '/^-?\d/', $nama ) ) {
			return $nama;
		}
	}

	/* .../maps/search/Masjid+Muchtar+Wonokromo?... */
	if ( preg_match( '#/maps/search/([^/@?]+)#i', $url, $m ) ) {
		$nama = trim( preg_replace( '/\s+/u', ' ', rawurldecode( str_replace( '+', ' ', $m[1] ) ) ) );
		if ( '' !== $nama && ! preg_match( '/^-?\d/', $nama ) ) {
			return $nama;
		}
	}

	/* ?q=Nama+Tempat (bukan koordinat) */
	if ( preg_match( '/[?&](?:q|query)=([^&]+)/i', $url, $m ) ) {
		$nama = trim( preg_replace( '/\s+/u', ' ', rawurldecode( str_replace( '+', ' ', $m[1] ) ) ) );
		if ( '' !== $nama && ! preg_match( '/^-?\d{1,3}(?:\.\d+)?\s*,\s*-?\d/', $nama ) ) {
			return $nama;
		}
	}

	return '';
}

/**
 * Baca koordinat dari URL (atau teks) Google Maps.
 *
 * @param string $masukan URL atau tempelan mentah.
 * @return array|WP_Error array( lat, lng, nama, sumber, url ) — lat/lng null kalau
 *                        URL hanya memuat nama tempat tanpa koordinat.
 */
function kh_koordinat_dari_url( $masukan ) {
	$mentah = trim( (string) $masukan );

	if ( '' === $mentah ) {
		return new WP_Error( 'kh_url_kosong', 'Tempel dulu URL Google Maps-nya.' );
	}

	$url  = kh_url_dari_teks( $mentah );
	$teks = $url ? $url : $mentah;

	if ( $url ) {
		$teks = kh_url_perpanjang( $url );
	}

	$teks = rawurldecode( $teks );
	$nama = kh_nama_dari_url_google( $teks );

	/* Urutan pola = urutan kepercayaan. Untuk tiap pola dicoba semua kemunculan
	   (URL bisa memuat beberapa pasangan angka; ambil yang valid pertama). */
	$pola = array(
		'marker' => '/!3d(-?\d{1,3}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',
		'peta'   => '/@(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',
		'query'  => '/(?:[?&](?:q|query|ll|sll|center|daddr|destination)=)(?:loc:)?(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)/i',
		'teks'   => '/(-?\d{1,3}\.\d{2,})\s*[,;]\s*(-?\d{1,3}\.\d{2,})/',
		/* koordinat gaya Indonesia (koma desimal): "-7,2554; 112,7387" */
		'koma'   => '/(-?\d{1,3},\d{2,})\s*[,;]?\s*(-?\d{1,3},\d{2,})/',
	);

	foreach ( $pola as $sumber => $re ) {
		if ( ! preg_match_all( $re, $teks, $semua, PREG_SET_ORDER ) ) {
			continue;
		}

		foreach ( $semua as $m ) {
			$lat = (float) str_replace( ',', '.', $m[1] );
			$lng = (float) str_replace( ',', '.', $m[2] );

			if ( abs( $lat ) > 90 || abs( $lng ) > 180 || ( 0.0 === $lat && 0.0 === $lng ) ) {
				continue;
			}

			return array(
				'lat'    => round( $lat, 6 ),
				'lng'    => round( $lng, 6 ),
				'nama'   => $nama,
				'sumber' => $sumber,
				'url'    => $teks,
			);
		}
	}

	/* URL valid tapi tanpa koordinat: kembalikan namanya supaya UI bisa mencari. */
	if ( '' !== $nama ) {
		return array(
			'lat'    => null,
			'lng'    => null,
			'nama'   => $nama,
			'sumber' => 'nama',
			'url'    => $teks,
		);
	}

	return new WP_Error(
		'kh_url_tanpa_koordinat',
		'URL itu tidak memuat koordinat. Di Google Maps: klik kanan titik lokasi (atau tombol Share) → Copy link → tempel ulang.'
	);
}

/* ------------------------------------------------------------------
 * REST: kh/v1/koordinat (khusus pengguna yang boleh menyunting laporan)
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route(
		'kh/v1',
		'/koordinat',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => 'kh_rest_koordinat',
		)
	);
} );

/**
 * @param WP_REST_Request $req Permintaan REST.
 * @return WP_REST_Response|WP_Error
 */
function kh_rest_koordinat( WP_REST_Request $req ) {
	$url   = $req->get_param( 'url' );
	$hasil = kh_koordinat_dari_url( is_string( $url ) ? $url : '' );

	if ( is_wp_error( $hasil ) ) {
		$data = $hasil->get_error_data();
		if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
			$hasil->add_data( array( 'status' => 422 ) );
		}
		return $hasil;
	}

	return new WP_REST_Response( $hasil, 200 );
}
