<?php
/**
 * SEO & Open Graph (WhatsApp/Facebook/X preview).
 *
 * Theme yang dipakai situs ini tidak punya modul SEO, jadi meta ditulis dari sini.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gambar OG bawaan (diunggah ke uploads/og/).
 */
function kh_og_image_default() {
	$url = get_option( 'kh_og_image' );
	if ( $url ) {
		return $url;
	}
	return content_url( 'uploads/og/peta-kendaraan-hilang.jpg' );
}

/**
 * Daftar nama daerah yang tidak ditampilkan di judul/deskripsi berbagi.
 */
function kh_daftar_daerah() {
	return array(
		'Jawa Timur', 'Jatim',
		'Surabaya', 'Sidoarjo', 'Gresik', 'Bangkalan', 'Mojokerto', 'Jombang',
		'Pasuruan', 'Probolinggo', 'Malang', 'Lamongan', 'Tuban', 'Bojonegoro',
		'Kediri', 'Madiun', 'Banyuwangi', 'Jember', 'Situbondo', 'Bondowoso',
		'Lumajang', 'Blitar', 'Trenggalek', 'Tulungagung', 'Nganjuk', 'Ngawi',
		'Magetan', 'Ponorogo', 'Pacitan', 'Sumenep', 'Pamekasan', 'Sampang',
	);
}

/**
 * Bersihkan nama daerah dari teks (judul/deskripsi OG).
 */
function kh_strip_daerah( $text ) {
	$t = (string) $text;

	foreach ( kh_daftar_daerah() as $d ) {
		$q = preg_quote( $d, '/' );
		$t = preg_replace( '/\s*[—–-]\s*' . $q . '\b/iu', '', $t );   // "— Jawa Timur"
		$t = preg_replace( '/\s+di\s+' . $q . '\b/iu', '', $t );       // " di Surabaya"
		$t = preg_replace( '/\s*,\s*' . $q . '\b/iu', '', $t );         // ", Surabaya"
		$t = preg_replace( '/\b' . $q . '\b/iu', '', $t );              // sisa
	}

	$t = preg_replace( '/\s*,\s*(?=,|\.|$)/', '', $t );
	$t = preg_replace( '/\s+([,.;])/u', '$1', $t );
	$t = preg_replace( '/,\s*\./u', '.', $t );
	$t = preg_replace( '/\s{2,}/', ' ', $t );

	return trim( $t );
}

/**
 * Susun judul + deskripsi sesuai konteks halaman.
 */
function kh_seo_context() {
	$out = array(
		'title' => 'Peta Kendaraan Hilang',
		'desc'  => 'Peta laporan kehilangan motor dan mobil, dikutip dari pemberitaan dan laporan warga.',
		'type'  => 'website',
		'img'   => kh_og_image_default(),
	);

	if ( is_front_page() ) {
		$count = wp_count_posts( 'kendaraan' );
		$n     = isset( $count->publish ) ? (int) $count->publish : 0;
		$out['title'] = 'Peta Kendaraan Hilang';
		$out['desc']  = sprintf(
			'%d laporan kehilangan motor dan mobil. Lihat lokasi, nomor polisi, dan status verifikasinya di peta.',
			$n
		);
		return $out;
	}

	if ( is_singular( 'kendaraan' ) ) {
		$id      = get_the_ID();
		$plat    = get_post_meta( $id, 'kh_plat_masked', true );
		$merk    = get_post_meta( $id, 'kh_merk', true );
		$model   = get_post_meta( $id, 'kh_model', true );
		$tahun   = get_post_meta( $id, 'kh_tahun', true );
		$warna   = get_post_meta( $id, 'kh_warna', true );
		$lokasi  = get_post_meta( $id, 'kh_lokasi', true );
		$tanggal = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
		$verif   = kh_verifikasi_of( $id );

		$kendaraan = trim( $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) );
		$tgl_txt   = $tanggal ? date_i18n( 'j F Y', strtotime( $tanggal ) ) : '';

		$nama = trim( ( $kendaraan ? $kendaraan : 'Kendaraan' ) . ( $plat ? ' (' . $plat . ')' : '' ) );

		$lokasi_bersih = kh_strip_daerah( $lokasi );

		$desc = $nama . ( $lokasi_bersih ? ' hilang di ' . $lokasi_bersih : ' dilaporkan hilang' )
			. ( $tgl_txt ? ', ' . $tgl_txt : '' ) . '.'
			. ( $warna ? ' Warna ' . strtolower( $warna ) . '.' : '' )
			. ' Bantu pantau dan laporkan kalau melihatnya.';

		$out['title'] = kh_strip_daerah( get_the_title( $id ) );
		$out['desc']  = $desc;
		$out['type']  = 'article';
		$out['verif'] = $verif;

		$thumb = get_the_post_thumbnail_url( $id, 'large' );
		if ( $thumb ) {
			$out['img'] = $thumb;
		}

		return $out;
	}

	if ( is_page( 'lapor' ) ) {
		$out['title'] = 'Laporkan Kendaraan Hilang';
		$out['desc']  = 'Laporkan motor atau mobil yang hilang. Cukup masuk dengan akun Google, tandai lokasi di peta, lalu kirim.';
		return $out;
	}

	if ( is_page() ) {
		$page = get_post( get_queried_object_id() );
		if ( $page ) {
			$out['url']   = get_permalink( $page );
			$out['title'] = get_the_title( $page ) . ' — ' . get_bloginfo( 'name' );
			$desc         = has_excerpt( $page ) ? $page->post_excerpt : wp_trim_words( wp_strip_all_tags( $page->post_content ), 28, '…' );
			$out['desc']  = $desc;
		}
		return $out;
	}

	if ( is_tax( 'kendaraan_wilayah' ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$n            = (int) $term->count;
			$out['url']   = get_term_link( $term );
			$out['title'] = 'Kendaraan Hilang di ' . $term->name . ' — Peta Kendaraan Hilang';
			$out['desc']  = $n
				? sprintf( '%d laporan kehilangan motor dan mobil di %s. Lihat lokasi, nomor polisi, dan status verifikasinya di peta.', $n, $term->name )
				: sprintf( 'Belum ada laporan aktif di %s. Laporkan kehilangan kendaraanmu lewat peta kendaraan hilang Jawa Timur.', $term->name );
		}
		return $out;
	}

	if ( is_post_type_archive( 'kendaraan' ) ) {
		$out['url']   = get_post_type_archive_link( 'kendaraan' );
		$out['title'] = 'Arsip Laporan Kendaraan Hilang';
		$out['desc']  = 'Seluruh laporan kehilangan kendaraan, lengkap dengan nomor polisi, lokasi, dan status verifikasi.';
		return $out;
	}

	return $out;
}

add_action( 'wp_head', 'kh_print_seo_meta', 5 );
function kh_print_seo_meta() {
	$c   = kh_seo_context();
	$url = ! empty( $c['url'] ) ? $c['url'] : ( is_singular() ? get_permalink() : home_url( '/' ) );

	$desc = wp_strip_all_tags( (string) $c['desc'] );
	$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );
	$desc = function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, 200 ) : substr( $desc, 0, 200 );

	echo "\n<!-- SEO / Open Graph -->\n";
	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	if ( ! is_singular() ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	}

	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $c['type'] ) );
	printf( '<meta property="og:locale" content="id_ID">' . "\n" );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $c['title'] ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $c['img'] ) );
	printf( '<meta property="og:image:width" content="1200">' . "\n" );
	printf( '<meta property="og:image:height" content="630">' . "\n" );

	printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $c['title'] ) );
	printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $c['img'] ) );
}

/**
 * Tambahkan noindex untuk halaman yang tidak perlu diindeks mesin pencari.
 */
add_action( 'wp_head', function () {
	$noindex = is_search() || is_404();

	if ( is_tax( 'kendaraan_wilayah' ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term && 0 === (int) $term->count ) {
			$noindex = true; // halaman wilayah kosong tidak perlu diindeks
		}
	}

	if ( $noindex ) {
		echo '<meta name="robots" content="noindex,follow">' . "\n";
	}
}, 6 );

/**
 * Pakai judul SEO juga untuk tag <title> (bukan hanya og:title).
 */
add_filter( 'document_title_parts', function ( $parts ) {
	$c = kh_seo_context();
	if ( empty( $c['title'] ) ) {
		return $parts;
	}
	$site = get_bloginfo( 'name' );
	$t    = $c['title'];
	if ( false === strpos( $t, $site ) ) {
		$t .= ' — ' . $site;
	}
	return array( 'title' => $t );
}, 20 );
