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
function kh_ai_chat( $system, $user, $opts = array() ) {
	$key = kh_ai_key();
	if ( ! $key ) {
		return new WP_Error( 'kh_ai', 'API key AI belum diisi.' );
	}

	$max_tokens = isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 3000;
	$timeout    = isset( $opts['timeout'] ) ? (int) $opts['timeout'] : 45;

	/*
	 * Model DeepSeek V4 menyalakan "thinking" secara default. Token penalaran
	 * ikut memakan max_tokens; kalau jatahnya habis di tengah penalaran, JSON
	 * mode mengembalikan content kosong ("jawaban AI kosong"). Untuk ekstraksi
	 * terstruktur, thinking dimatikan: lebih murah, lebih cepat, dan hasilnya
	 * lebih lengkap (kolom merk ikut terisi).
	 */
	$pesan_user = $user;

	/*
	 * Jalur gambar (vision): deepseek-flash menerima content berupa array blok
	 * [{type:text},{type:image_url, image_url:{url:"data:<mime>;base64,..."}}].
	 * Hanya jalur "text" yang butuh content string biasa; format tetap sama.
	 */
	if ( ! empty( $opts['gambar']['b64'] ) ) {
		$mime       = isset( $opts['gambar']['mime'] ) ? $opts['gambar']['mime'] : 'image/png';
		$pesan_user = array(
			array( 'type' => 'text', 'text' => $user ),
			array(
				'type'      => 'image_url',
				'image_url' => array( 'url' => 'data:' . $mime . ';base64,' . $opts['gambar']['b64'] ),
			),
		);
	}

	$body = array(
		'model'           => kh_ai_model(),
		'messages'        => array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $pesan_user ),
		),
		'response_format' => array( 'type' => 'json_object' ),
		'temperature'     => 0.1,
		'max_tokens'      => $max_tokens,
		'thinking'        => array( 'type' => 'disabled' ),
		'stream'          => false,
	);

	$resp = wp_remote_post(
		'https://api.deepseek.com/chat/completions',
		array(
			'timeout' => $timeout,
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

	$pilihan = isset( $data['choices'][0] ) ? $data['choices'][0] : array();
	$content = isset( $pilihan['message']['content'] ) ? trim( (string) $pilihan['message']['content'] ) : '';

	if ( '' === $content ) {
		$finish = isset( $pilihan['finish_reason'] ) ? $pilihan['finish_reason'] : '?';
		$usage  = isset( $data['usage']['completion_tokens'] ) ? (int) $data['usage']['completion_tokens'] : 0;
		$reason = isset( $data['usage']['completion_tokens_details']['reasoning_tokens'] ) ? (int) $data['usage']['completion_tokens_details']['reasoning_tokens'] : 0;

		return new WP_Error(
			'kh_ai_empty',
			sprintf( 'Jawaban AI kosong (finish=%s, token keluar=%d, penalaran=%d, batas=%d).', $finish, $usage, $reason, $max_tokens )
		);
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
 * Prompt sistem untuk ekstraksi (dipakai jalur artikel & jalur gambar).
 */
function kh_ai_prompt_sistem() {
	return 'Kamu asisten ekstraksi data untuk peta kendaraan hilang di Jawa Timur. '
		. 'Balas HANYA dengan objek JSON valid, tanpa penjelasan, tanpa markdown. '
		. 'Ambil data hanya dari bahan yang diberikan (teks atau gambar). Jangan mengarang: kalau tidak ada, kosongkan (""). '
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
		. 'ringkasan = satu kalimat bahasa Indonesia, fakta saja, tanpa menyalin kalimat sumber apa adanya. '
		. 'Kalau satu gambar memuat beberapa kendaraan, kembalikan semuanya sebagai item terpisah. '
		. 'Abaikan teks yang bukan data kendaraan (judul akun, hashtag, nomor hotline media, ajakan follow).';
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

	$system = kh_ai_prompt_sistem();

	/* Percobaan bertahap: gagal pertama (jawaban kosong / JSON terpotong)
	   dicoba ulang dengan teks lebih pendek dan jatah token lebih besar. */
	$percobaan = array(
		array( 'panjang' => 6000, 'max_tokens' => 3000 ),
		array( 'panjang' => 3500, 'max_tokens' => 4000 ),
	);

	$json  = null;
	$error = null;

	foreach ( $percobaan as $coba ) {
		$potong = mb_substr( $teks, 0, $coba['panjang'] );
		$user   = 'Judul: ' . $judul . "\n"
			. ( $tanggal ? 'Tanggal publikasi: ' . $tanggal . "\n" : '' )
			. "\nIsi artikel:\n" . $potong;

		$hasil = kh_ai_chat( $system, $user, array( 'max_tokens' => $coba['max_tokens'] ) );
		if ( ! is_wp_error( $hasil ) ) {
			$json = $hasil;
			break;
		}
		$error = $hasil;
	}

	if ( ! $json ) {
		return $error ? $error : new WP_Error( 'kh_ai', 'AI tidak merespons.' );
	}

	$items = kh_ai_normalisasi( $json, $tanggal );
	if ( is_wp_error( $items ) ) {
		return $items;
	}

	$hasil = array(
		'items'   => $items,
		'tanggal' => kh_parse_tanggal( (string) ( $json['tanggal'] ?? '' ) ),
	);

	set_transient( $cache_key, $hasil, 7 * DAY_IN_SECONDS );

	return $hasil;
}

/**
 * Normalisasi + validasi jawaban AI (dipakai jalur artikel maupun gambar).
 *
 * @param array  $json    Jawaban AI yang sudah di-decode.
 * @param string $tanggal Tanggal cadangan (Y-m-d) kalau baris tidak menyebut.
 * @return array|WP_Error Daftar item siap dipakai UI.
 */
function kh_ai_normalisasi( $json, $tanggal = '' ) {
	$daftar = isset( $json['kendaraan'] ) && is_array( $json['kendaraan'] ) ? $json['kendaraan'] : array();
	if ( ! $daftar ) {
		return new WP_Error( 'kh_ai_none', 'AI tidak menemukan kendaraan pada bahan ini.' );
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

	return $items;
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

/**
 * Ekstrak daftar kendaraan dari GAMBAR (infografis / poster / screenshot).
 *
 * Gambar dikirim sekali sebagai data URL ke DeepSeek (vision) dan tidak disimpan
 * di server. Yang dipakai hanya datanya — desain/ekspresi sumber tidak disalin.
 *
 * @param string $b64     Isi gambar dalam base64 (tanpa prefiks "data:...").
 * @param string $mime    image/png | image/jpeg | image/webp | image/gif
 * @param string $catatan Teks pendamping opsional (caption/sumber) sebagai konteks.
 * @return array|WP_Error array( 'items' => [...], 'tanggal' => 'Y-m-d' )
 */
function kh_ai_extract_visi( $b64, $mime, $catatan = '' ) {
	if ( '' === trim( (string) $b64 ) ) {
		return new WP_Error( 'kh_ai', 'Gambar kosong.' );
	}

	$diizinkan = array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' );
	if ( ! in_array( (string) $mime, $diizinkan, true ) ) {
		return new WP_Error( 'kh_ai', 'Format gambar tidak didukung: ' . $mime );
	}

	$user = 'Baca gambar ini dan ekstrak semua kendaraan yang dilaporkan hilang.';
	if ( '' !== trim( (string) $catatan ) ) {
		$user .= "\n\nTeks pendamping dari admin (konteks tambahan, jangan dikarang):\n"
			. mb_substr( (string) $catatan, 0, 2000 );
	}

	$hasil = kh_ai_chat(
		kh_ai_prompt_sistem(),
		$user,
		array(
			'max_tokens' => 3000,
			'timeout'    => 60,
			'gambar'     => array( 'b64' => $b64, 'mime' => $mime ),
		)
	);

	if ( is_wp_error( $hasil ) ) {
		return $hasil;
	}

	$items = kh_ai_normalisasi( $hasil );
	if ( is_wp_error( $items ) ) {
		return $items;
	}

	return array(
		'items'   => $items,
		'tanggal' => kh_parse_tanggal( (string) ( $hasil['tanggal'] ?? '' ) ),
	);
}
