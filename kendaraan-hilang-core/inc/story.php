<?php
/**
 * Kartu "Story" (1080x1920) untuk dibagikan ke Instagram / Facebook Story.
 *
 * IG & FB Story tidak menerima URL dari browser, jadi yang dibagikan adalah
 * GAMBAR berisi info kendaraan + QR ke halaman detail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QR code sebagai data URI (di-cache 30 hari per URL).
 */
function kh_story_qr_uri( $url ) {
	$key = 'kh_qr_' . md5( (string) $url );
	$hit = get_transient( $key );
	if ( is_string( $hit ) && '' !== $hit ) {
		return $hit;
	}

	$uri = '';

	$sources = array(
		'https://quickchart.io/qr?size=520&margin=8&ecLevel=M&text=' . rawurlencode( $url ),
		'https://api.qrserver.com/v1/create-qr-code/?size=520x520&margin=8&data=' . rawurlencode( $url ),
	);

	foreach ( $sources as $api ) {
		$res = wp_remote_get(
			$api,
			array(
				'timeout' => 10,
				'headers' => array( 'User-Agent' => 'PetaKendaraanHilang/1.0 (+https://hilang.refanoandreas.com)' ),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			continue;
		}
		$body = wp_remote_retrieve_body( $res );
		if ( strlen( $body ) > 500 ) {
			$uri = 'data:image/png;base64,' . base64_encode( $body ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			break;
		}
	}

	set_transient( $key, $uri, 30 * DAY_IN_SECONDS );

	return $uri;
}

/**
 * Data untuk generator kartu story.
 */
function kh_story_data( $post_id ) {
	$plat    = get_post_meta( $post_id, 'kh_plat_masked', true );
	$merk    = get_post_meta( $post_id, 'kh_merk', true );
	$model   = get_post_meta( $post_id, 'kh_model', true );
	$tahun   = get_post_meta( $post_id, 'kh_tahun', true );
	$warna   = get_post_meta( $post_id, 'kh_warna', true );
	$lokasi  = get_post_meta( $post_id, 'kh_lokasi', true );
	$tanggal = kh_normalize_tanggal( get_post_meta( $post_id, 'kh_tanggal', true ) );
	$status  = wp_get_post_terms( $post_id, 'kendaraan_status', array( 'fields' => 'names' ) );
	$status  = $status ? $status[0] : 'Hilang';
	$verif   = kh_verifikasi_of( $post_id );
	$labels  = kh_verifikasi_levels();
	$sumber  = get_post_meta( $post_id, 'kh_sumber_nama', true );

	$tgl_txt = '';
	if ( $tanggal ) {
		$tgl_txt = date_i18n( 'j F Y', strtotime( $tanggal ) );
	}

	$is_got = ( 'ditemukan' === strtolower( $status ) );

	$keadaan = $is_got
		? 'Sudah ditemukan'
		: ( 'polisi' === $verif ? 'Sudah lapor polisi' : ( 'media' === $verif ? 'Terverifikasi media' : 'Belum diverifikasi' ) );

	$warna_map = array(
		'warga'  => '#ffb02e',
		'media'  => '#2fb4ff',
		'polisi' => '#35f0c8',
		'got'    => '#b06bff',
	);

	$url = get_permalink( $post_id );

	return array(
		'plat'      => $plat,
		'kendaraan' => trim( $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) ),
		'warna'     => $warna,
		'lokasi'    => $lokasi,
		'tanggal'   => $tgl_txt,
		'status'    => $is_got ? 'DITEMUKAN' : 'HILANG',
		'statusCol' => $is_got ? '#b06bff' : '#ff5a3c',
		'keadaan'   => $keadaan,
		'keadaanCol'=> $is_got ? $warna_map['got'] : $warna_map[ $verif ],
		'url'       => $url,
		'sumber'    => $sumber ? $sumber : 'Laporan warga',
		'qr'        => kh_story_qr_uri( $url ),
	);
}

/**
 * Enqueue generator kartu story di halaman detail.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'kendaraan' ) ) {
		return;
	}
	wp_enqueue_script( 'kh-story', KH_CORE_URL . 'assets/story.js', array(), KH_CORE_VERSION, true );
} );
