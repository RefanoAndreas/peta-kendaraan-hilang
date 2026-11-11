<?php
/**
 * Ekstraksi data laporan dengan model AI (DeepSeek, format OpenAI-compatible).
 *
 * Opsional: hanya aktif kalau API key diisi di Pengaturan. Kalau AI gagal
 * (kuota habis, jaringan, JSON rusak), importer kembali memakai parser regex.
 *
 * Catatan kepatuhan: yang dikirim ke API hanya judul + teks artikel yang
 * memang publik, dan teks itu tidak disimpan di database.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API key DeepSeek.
 */
function kh_ai_key() {
	return trim( (string) get_option( 'kh_ai_key', '' ) );
}

/**
 * Nama model (default: deepseek-flash).
 */
function kh_ai_model() {
	$m = trim( (string) get_option( 'kh_ai_model', '' ) );
	return $m ? $m : 'deepseek-flash';
}

/**
 * AI siap dipakai?
 */
function kh_ai_enabled() {
	return kh_ai_key() && '1' === (string) get_option( 'kh_ai_enabled', '1' );
}

/**
 * Panggil endpoint chat/completions DeepSeek (JSON mode).
 *
 * @param string $system Prompt sistem.
 * @param string $user   Prompt pengguna.
 * @return array|WP_Error Array hasil JSON yang sudah di-decode.
 */
function kh_ai_chat( $system, $user ) {
	$key = kh_ai_key();
	if ( ! $key ) {
		return new WP_Error( 'kh_ai', 'API key AI belum diisi.' );
	}

	$body = array(
		'model'           => kh_ai_model(),
		'messages'        => array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $user ),
		),
		'response_format' => array( 'type' => 'json_object' ),
		'temperature'     => 0.1,
		'max_tokens'      => 2000,
		'stream'          => false,
	);

	$resp = wp_remote_post(
		'https://api.deepseek.com/chat/completions',
		array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $key,
			),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $resp ) ) {
		return $resp;
	}

	$code = (int) wp_remote_retrieve_response_code( $resp );
	$raw  = wp_remote_retrieve_body( $resp );
	$data = json_decode( $raw, true );

	if ( 200 !== $code ) {
		$pesan = 'HTTP ' . $code;
		if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
			$pesan .= ' — ' . $data['error']['message'];
		}
		return new WP_Error( 'kh_ai_http', $pesan );
	}

	$content = isset( $data['choices'][0]['message']['content'] ) ? trim( (string) $data['choices'][0]['message']['content'] ) : '';
	if ( '' === $content ) {
		return new WP_Error( 'kh_ai_empty', 'Jawaban AI kosong.' );
	}

	$json = json_decode( $content, true );
	if ( ! is_array( $json ) ) {
		/* kadang dibungkus ```json ... ``` */
		$bersih = preg_replace( '/^```(?:json)?|```$/m', '', $content );
		$json   = json_decode( trim( $bersih ), true );
	}
	if ( ! is_array( $json ) ) {
		return new WP_Error( 'kh_ai_json', 'Jawaban AI bukan JSON yang valid.' );
	}

	return $json;
}

/**
 * Daftar wilayah yang dipakai situs (untuk memvalidasi jawaban AI).
 */
function kh_ai_daftar_wilayah() {
	return array(
		'Surabaya', 'Sidoarjo', 'Gresik', 'Bangkalan', 'Mojokerto', 'Jombang',
		'Pasuruan', 'Probolinggo', 'Malang', 'Lamongan', 'Tuban', 'Bojonegoro',
		'Kediri', 'Madiun', 'Banyuwangi', 'Jember', 'Situbondo', 'Bondowoso',
		'Lumajang', 'Blitar', 'Trenggalek', 'Tulungagung', 'Nganjuk', 'Ngawi',
		'Magetan', 'Ponorogo', 'Pacitan', 'Sumenep', 'Pamekasan', 'Sampang',
	);
}

/**
 * Ekstrak daftar kendaraan dari satu artikel.
 *
 * @param string $judul  Judul artikel.
 * @param string $teks   Isi artikel (teks polos).
 * @param string $tanggal Tanggal publikasi (Y-m-d) sebagai cadangan.
 * @return array|WP_Error array( 'items' => [...], 'tanggal' => 'Y-m-d' )
 */
function kh_ai_extract_kendaraan( $judul, $teks, $tanggal = '' ) {
	$teks = trim( (string) $teks );
	if ( '' === $teks ) {
		return new WP_Error( 'kh_ai', 'Teks artikel kosong.' );
	}

	/* batasi ukuran kiriman (artikel panjang dipotong, hemat biaya) */
	$teks = mb_substr( $teks, 0, 7000 );

	$cache_key = 'kh_ai_x_' . md5( $judul . '|' . $teks );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$system = 'Kamu asisten ekstraksi data untuk peta kendaraan hilang di Jawa Timur. '
		. 'Balas HANYA dengan objek JSON valid, tanpa penjelasan, tanpa markdown. '
		. 'Ambil data hanya dari teks yang diberikan. Jangan mengarang: kalau tidak ada di teks, kosongkan (""). '
		. 'Format JSON yang wajib dipakai: '
		. '{"tanggal":"YYYY-MM-DD","kendaraan":[{"plat":"","jenis":"Motor|Mobil","merk":"","model":"","tahun":"","warna":"",'
		. '"lokasi":"","wilayah":"","tanggal":"YYYY-MM-DD","jam":"HH:MM","unit":"Polsek/Polres/Polresta/Polrestabes/Polda + nama","ciri":"","ringkasan":""}]}'
		. ' Aturan: plat nomor Indonesia ditulis seperti "L 3183 DAC" (huruf-angka-huruf). '
		. 'jenis "Motor" untuk sepeda motor, "Mobil" untuk mobil. tahun 4 digit. '
		. 'lokasi = nama tempat/patokan (maksimal 1 kalimat singkat, tanpa awalan "di"). '
		. 'wilayah = salah satu: ' . implode( ', ', kh_ai_daftar_wilayah() ) . ' (kosongkan kalau tidak jelas). '
		. 'tanggal/jam = waktu kejadian kalau disebut; kalau hanya ada tanggal publikasi, pakai itu. '
		. 'unit = instansi kepolisian yang menerima laporan, sebutkan jenis + nama, mis. "Polrestabes Surabaya". '
		. 'ciri = ciri khusus kendaraan (stiker, striping, kondisi). '
		. 'ringkasan = satu kalimat bahasa Indonesia, fakta saja, tanpa menyalin kalimat artikel apa adanya.';

	$user = 'Judul: ' . $judul . "\n"
		. ( $tanggal ? 'Tanggal publikasi: ' . $tanggal . "\n" : '' )
		. "\nIsi artikel:\n" . $teks;

	$json = kh_ai_chat( $system, $user );
	if ( is_wp_error( $json ) ) {
		return $json;
	}

	$daftar = isset( $json['kendaraan'] ) && is_array( $json['kendaraan'] ) ? $json['kendaraan'] : array();
	if ( ! $daftar ) {
		return new WP_Error( 'kh_ai_none', 'AI tidak menemukan kendaraan pada artikel ini.' );
	}

	$items   = array();
	$wilayah = kh_ai_daftar_wilayah();

	foreach ( $daftar as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$plat = kh_normalize_plat( $row['plat'] ?? '' );
		if ( '' === $plat || ! preg_match( '/^[A-Z]{1,2}\s\d{1,4}\s[A-Z]{1,3}$/', $plat ) ) {
			continue;
		}

		$unit_info = kh_deteksi_unit( (string) ( $row['unit'] ?? '' ) );

		$wil = '';
		foreach ( $wilayah as $w ) {
			if ( false !== stripos( (string) ( $row['wilayah'] ?? '' ), $w ) ) {
				$wil = $w;
				break;
			}
		}

		$tgl = kh_parse_tanggal( (string) ( $row['tanggal'] ?? '' ) );
		if ( ! $tgl ) {
			$tgl = $tanggal;
		}

		$jam = trim( (string) ( $row['jam'] ?? '' ) );
		if ( $jam && ! preg_match( '/^\d{1,2}:\d{2}$/', $jam ) ) {
			$jam = '';
		}

		$jenis = ( isset( $row['jenis'] ) && false !== stripos( (string) $row['jenis'], 'mobil' ) ) ? 'Mobil' : 'Motor';

		$items[] = array(
			'plat'      => $plat,
			'merk'      => sanitize_text_field( (string) ( $row['merk'] ?? '' ) ),
			'model'     => sanitize_text_field( (string) ( $row['model'] ?? '' ) ),
			'tahun'     => preg_replace( '/\D/', '', (string) ( $row['tahun'] ?? '' ) ),
			'warna'     => sanitize_text_field( (string) ( $row['warna'] ?? '' ) ),
			'ciri'      => sanitize_text_field( (string) ( $row['ciri'] ?? '' ) ),
			'lokasi'    => sanitize_text_field( (string) ( $row['lokasi'] ?? '' ) ),
			'wilayah'   => $wil,
			'polsek'    => $unit_info['nama'],
			'unit'      => $unit_info['level'],
			'jam'       => $jam,
			'jenis'     => $jenis,
			'tanggal'   => $tgl,
			'ringkasan' => sanitize_text_field( (string) ( $row['ringkasan'] ?? '' ) ),
		);
	}

	if ( ! $items ) {
		return new WP_Error( 'kh_ai_none', 'AI tidak menemukan nomor polisi yang valid.' );
	}

	$hasil = array(
		'items'   => $items,
		'tanggal' => kh_parse_tanggal( (string) ( $json['tanggal'] ?? '' ) ),
	);

	set_transient( $cache_key, $hasil, 7 * DAY_IN_SECONDS );

	return $hasil;
}

/* ------------------------------------------------------------------
 * Alat bantu di halaman Pengaturan
 * ------------------------------------------------------------------ */

/**
 * Tombol: uji ekstraksi AI memakai contoh paragraf.
 */
add_action( 'admin_post_kh_ai_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Akses ditolak.' );
	}
	check_admin_referer( 'kh_ai_test' );

	$contoh = 'Seorang warga melaporkan kehilangan sepeda motor Honda Vario 2021 bernomor polisi L 5974 KW '
		. 'berwarna merah. Motor tersebut terakhir terlihat di kos kawasan Menur Pumpungan, Surabaya, '
		. 'pada Sabtu malam sekitar pukul 19.30 WIB. Korban sudah melapor ke Polsek Sukolilo.';

	$res = kh_ai_extract_kendaraan( 'Uji ekstraksi AI', $contoh, wp_date( 'Y-m-d' ) );

	if ( is_wp_error( $res ) ) {
		$pesan = 'Gagal: ' . $res->get_error_message();
	} else {
		$baris = array();
		foreach ( $res['items'] as $it ) {
			$baris[] = $it['plat'] . ' — ' . trim( $it['merk'] . ' ' . $it['model'] ) . ' ' . $it['tahun']
				. ' · ' . $it['warna'] . ' · ' . $it['lokasi'] . ( $it['wilayah'] ? ', ' . $it['wilayah'] : '' )
				. ' · ' . ( $it['tanggal'] ? $it['tanggal'] : '-' ) . ( $it['jam'] ? ' ' . $it['jam'] : '' )
				. ' · ' . ( $it['polsek'] ? $it['polsek'] : 'tanpa unit' );
		}
		$pesan = 'Berhasil (' . kh_ai_model() . '): ' . implode( ' | ', $baris );
	}

	wp_safe_redirect( add_query_arg( 'kh_ai_msg', rawurlencode( $pesan ), admin_url( 'edit.php?post_type=kendaraan&page=kh-settings' ) ) );
	exit;
} );
