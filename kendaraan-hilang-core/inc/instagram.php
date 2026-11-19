<?php
/**
 * Ambil gambar dari postingan Instagram untuk "Input dari Infografis".
 *
 * Kenapa lewat jalur ini: admin cukup menempel link postingan (mis. laporan
 * kendaraan hilang dari akun media), sistem mengambil gambarnya, lalu AI
 * membaca gambarnya. Tidak ada API key Meta/Facebook dan tidak ada scraping
 * massal — satu halaman per klik admin, sama seperti admin membuka postingan
 * itu sendiri di browser.
 *
 * Instagram memblokir fetch polos dari IP datacenter (yang keluar halaman
 * "challenge"), jadi urutan percobaannya:
 *   1. https://www.instagram.com/p/KODE/embed/captioned/   (versi embed publik)
 *   2. https://www.instagram.com/p/KODE/                   (og:image)
 *   3. https://r.jina.ai/<url embed>                       (pembaca pihak ketiga)
 * Yang dipakai hanya URL gambar sementara (bertanda tangan, kedaluwarsa
 * beberapa jam) + caption sebagai konteks. Gambar TIDAK disimpan di server:
 * dikirim sekali ke DeepSeek untuk dibaca, lalu dilupakan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fitur "ambil gambar dari link Instagram" menyala?
 *
 * Dimatikan secara default: robots.txt & Ketentuan Meta menyatakan pengambilan
 * data dari Instagram secara otomatis hanya boleh dengan izin tertulis. Nyalakan
 * hanya kalau kamu punya izin dari pemilik konten (mis. akun media mitra).
 */
function kh_ig_aktif() {
	return '1' === (string) get_option( 'kh_ig_aktif', '0' );
}

/**
 * Kode postingan dari URL Instagram (atau kode polos).
 *
 * @param string $url URL postingan / kode.
 * @return string Kode postingan, atau '' kalau tidak dikenali.
 */
function kh_ig_kode( $url ) {
	$url = trim( (string) $url );

	if ( preg_match( '#instagram\.com/(?:p|reel|reels|tv)/([A-Za-z0-9_-]{5,})#i', $url, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#^([A-Za-z0-9_-]{5,})$#', $url, $m ) ) {
		return $m[1];
	}

	return '';
}

/**
 * User-Agent browser biasa (Instagram menolak UA aneh/kosong).
 */
function kh_ig_ua() {
	return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) '
		. 'Chrome/131.0.0.0 Safari/537.36';
}

/**
 * Ambil satu URL (HTML) sebagai teks.
 *
 * @param string $url  URL tujuan.
 * @param int    $timeout Detik.
 * @return string|WP_Error
 */
function kh_ig_fetch( $url, $timeout = 25 ) {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'     => $timeout,
			'redirection' => 3,
			'headers'     => array(
				'User-Agent'      => kh_ig_ua(),
				'Accept'          => 'text/html,application/xhtml+xml',
				'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
			),
		)
	);

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$kode = (int) wp_remote_retrieve_response_code( $res );
	if ( $kode < 200 || $kode >= 300 ) {
		return new WP_Error( 'kh_ig_http', 'Instagram membalas HTTP ' . $kode . '.' );
	}

	return (string) wp_remote_retrieve_body( $res );
}

/**
 * Kumpulkan URL gambar postingan dari HTML/markdown.
 *
 * Menyaring: ikon aset `static.cdninstagram.com/rsrc.php`, foto profil
 * (`s150x150`), dan thumbnail rekomendasi (`p240x240`/`p150x150`).
 *
 * @param string $isi HTML atau markdown.
 * @return string[] URL gambar (urutan kemunculan, sudah unik).
 */
function kh_ig_gambar_dari_isi( $isi ) {
	/* URL harus utuh TERMASUK query string: di dalamnya ada tanda tangan CDN
	   (oh/oe), penanda ukuran (stp), dan ig_cache_key untuk dedupe. */
	if ( ! preg_match_all( '#https://[a-z0-9.-]*cdninstagram\.com/[^\s"\'\\)\\\\<>]+#i', (string) $isi, $m ) ) {
		return array();
	}

	$out   = array();
	$sudah = array();

	foreach ( $m[0] as $url ) {
		$url = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );
		$url = rtrim( $url, '.,;\'"' );

		/* hanya berkas gambar (ekstensi sebelum query string) */
		if ( ! preg_match( '/\.(?:jpg|jpeg|webp|png)(?:\?|$)/i', $url ) ) {
			continue;
		}
		if ( false !== strpos( $url, '/rsrc.php' ) ) {
			continue;
		}
		if ( preg_match( '/_(?:s|p)\d{3}x\d{3}_/', $url ) ) {
			continue; /* foto profil / thumbnail rekomendasi */
		}
		if ( ! preg_match( '#/v/t#', $url ) ) {
			continue;
		}

		/* kunci unik: ig_cache_key kalau ada, kalau tidak path berkasnya */
		$kunci = '';
		if ( preg_match( '/ig_cache_key=([^&]+)/', $url, $k ) ) {
			$kunci = $k[1];
		} else {
			$kunci = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		}
		if ( '' === $kunci || isset( $sudah[ $kunci ] ) ) {
			continue;
		}
		$sudah[ $kunci ] = true;
		$out[]           = $url;
	}

	return $out;
}

/**
 * Caption postingan dari HTML embed (atau markdown pembaca) sebagai konteks AI.
 *
 * @param string $isi HTML/markdown.
 * @return string
 */
function kh_ig_caption_dari_isi( $isi ) {
	$isi = (string) $isi;
	$teks = '';

	if ( preg_match( '#<div[^>]+class="[^"]*Caption[^"]*"[^>]*>(.*?)</div>#is', $isi, $m ) ) {
		$teks = $m[1];
	} else {
		/* jalur r.jina.ai (markdown): buang header pembaca, gambar, tautan, URL telanjang */
		$teks = preg_replace( '/^(?:Title|URL Source|Published Time|Markdown Content|Warning)\s*:.*$/mi', ' ', $isi );
		$teks = preg_replace( '#!\[[^\]]*\]\([^)]*\)#', ' ', $teks );
		$teks = preg_replace( '#\[([^\]]*)\]\([^)]*\)#', '$1', $teks );
		$teks = preg_replace( '#https?://\S+#i', ' ', $teks );
	}

	$teks = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $teks );
	$teks = wp_strip_all_tags( $teks );
	$teks = html_entity_decode( $teks, ENT_QUOTES, 'UTF-8' );

	/* buang "chrome" UI halaman embed yang ikut terbaca (followers, like, dsb.) */
	$teks = preg_replace(
		array(
			'/\b_?Verified_?\b/iu',
			'/\b\d[\d.,]*\s*[KM]?\s*followers?\b/iu',
			'/\bView profile\b/iu',
			'/\b[\d.,]+\s*posts?\b/iu',
			'/\bView more on\b/iu',
			'/\bLike_Comment__Share__Sav\w*/iu',
			'/\b\d[\d.,]*\s*likes?\b/iu',
			'/\bView all \d+ comments?\b/iu',
			'/\bAdd a comment[.\s]*/iu',
			'/\b_?Instagram_?\b/iu',
			'/\*\s*\*/',
		),
		' ',
		$teks
	);

	$teks = preg_replace( '/\s+/u', ' ', $teks );
	$teks = trim( (string) $teks );

	/* buang ajakan khas halaman embed (jangan pakai .* — bisa memangkas caption) */
	$teks = preg_replace(
		'/\b(?:Lihat (?:postingan )?(?:ini )?di Instagram|View (?:this )?post on Instagram|Instagram)\b/iu',
		' ',
		$teks
	);

	$teks = trim( preg_replace( '/\s+/u', ' ', $teks ) );

	/* mbstring tidak selalu aktif di shared hosting */
	return function_exists( 'mb_substr' ) ? mb_substr( $teks, 0, 1500 ) : substr( $teks, 0, 1500 );
}

/**
 * Ambil gambar + caption dari satu URL postingan Instagram.
 *
 * @param string $url URL postingan (atau kode).
 * @return array|WP_Error array( 'kode', 'gambar' => string[], 'caption', 'sumber' )
 */
function kh_ig_ambil( $url ) {
	$kode = kh_ig_kode( $url );
	if ( '' === $kode ) {
		return new WP_Error( 'kh_ig_url', 'Itu bukan link postingan Instagram. Contoh: https://www.instagram.com/p/AbCdEf123/ atau https://www.instagram.com/reel/AbCdEf123/' );
	}

	$percobaan = array(
		'embed' => 'https://www.instagram.com/p/' . $kode . '/embed/captioned/',
		'posta' => 'https://www.instagram.com/p/' . $kode . '/',
		'jina'  => 'https://r.jina.ai/https://www.instagram.com/p/' . $kode . '/embed/captioned/',
	);

	$catatan = array();

	foreach ( $percobaan as $nama => $target ) {
		$isi = kh_ig_fetch( $target );
		if ( is_wp_error( $isi ) ) {
			$catatan[] = $nama . ': ' . $isi->get_error_message();
			continue;
		}

		$gambar = kh_ig_gambar_dari_isi( $isi );
		if ( ! $gambar && 'posta' === $nama && preg_match( '#property=["\']og:image["\'][^>]*content=["\']([^"\']+)#i', $isi, $og ) ) {
			$gambar = array( html_entity_decode( $og[1], ENT_QUOTES, 'UTF-8' ) );
		}

		if ( $gambar ) {
			return array(
				'kode'    => $kode,
				'gambar'  => array_values( $gambar ),
				'caption' => kh_ig_caption_dari_isi( $isi ),
				'sumber'  => $nama,
			);
		}

		$catatan[] = $nama . ': tidak ada gambar di halaman';
	}

	return new WP_Error(
		'kh_ig_gagal',
		'Gambar postingan tidak bisa diambil. '
		. 'Postingan mungkin privat, dihapus, atau sedang dibatasi Instagram. '
		. 'Coba buka postingannya di browser lalu pakai screenshot sebagai gambar. ('
		. implode( '; ', $catatan ) . ')'
	);
}

/**
 * Unduh satu gambar Instagram menjadi base64 + mime.
 *
 * @param string $url URL gambar (bertanda tangan, sementara).
 * @return array|WP_Error array( 'b64', 'mime', 'kb' )
 */
function kh_ig_gambar_ke_b64( $url ) {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'     => 30,
			'redirection' => 2,
			'headers'     => array(
				'User-Agent' => kh_ig_ua(),
				'Referer'    => 'https://www.instagram.com/',
			),
		)
	);

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$kode = (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $kode ) {
		return new WP_Error( 'kh_ig_cdn', 'Gambar tidak bisa diunduh (HTTP ' . $kode . ').' );
	}

	$isi = (string) wp_remote_retrieve_body( $res );
	if ( strlen( $isi ) < 1024 ) {
		return new WP_Error( 'kh_ig_cdn', 'Gambar terlalu kecil / bukan gambar.' );
	}
	if ( strlen( $isi ) > 8 * MB_IN_BYTES ) {
		return new WP_Error( 'kh_ig_cdn', 'Gambar lebih dari 8 MB.' );
	}

	$info = @getimagesizefromstring( $isi );
	$mime = ( $info && ! empty( $info['mime'] ) ) ? $info['mime'] : '';
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
		return new WP_Error( 'kh_ig_cdn', 'Berkas yang diunduh bukan gambar yang didukung.' );
	}

	return array(
		'b64'  => base64_encode( $isi ),
		'mime' => $mime,
		'kb'   => (int) round( strlen( $isi ) / 1024 ),
	);
}

/**
 * Gabungkan hasil beberapa gambar: plat sama digabung, kolom kosong dilengkapi.
 *
 * @param array $items Daftar item hasil AI.
 * @return array
 */
function kh_ig_gabung_item( $items ) {
	$out   = array();
	$posisi = array();
	$field  = array( 'merk', 'model', 'tahun', 'warna', 'ciri', 'lokasi', 'wilayah', 'polsek', 'jam', 'ringkasan' );

	foreach ( (array) $items as $it ) {
		$kunci = preg_replace( '/\s+/', '', (string) $it['plat'] );

		if ( isset( $posisi[ $kunci ] ) ) {
			$i = $posisi[ $kunci ];
			foreach ( $field as $f ) {
				if ( '' === (string) $out[ $i ][ $f ] && '' !== (string) $it[ $f ] ) {
					$out[ $i ][ $f ] = $it[ $f ];
				}
			}
			continue;
		}

		$posisi[ $kunci ] = count( $out );
		$out[]            = $it;
	}

	return $out;
}
