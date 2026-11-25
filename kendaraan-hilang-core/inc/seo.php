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
 * Panjang string dalam karakter (multibyte bila tersedia).
 */
function kh_seo_panjang( $s ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s );
}

/**
 * Bersihkan satu segmen lokasi agar layak jadi ekor judul.
 *
 * Lokasi laporan diisi kurator apa adanya, jadi sering berbentuk kalimat:
 * "saat diparkir di warung kopi", "Depan rumah kos di kawasan Kedungdoro".
 * Kata generik di depan dibuang berulang sampai tersisa nama tempat, dan
 * prefiks "Jalan"/"Jl." dipotong supaya hemat karakter di judul.
 */
function kh_seo_rapikan_lokasi( $s ) {
	$s = trim( (string) $s );
	if ( '' === $s ) {
		return '';
	}

	$pola = '/^(?:saat|diparkir|terparkir|parkiran|tempat\s+parkir|halaman|tempat|area|daerah|sekitar|depan|didepan|di|belakang|samping|warung\s+kopi|warung|toko|minimarket|mal|mall|pasar|pertokoan|gang|lorong|rumah\s+kos|kos|rumah|kawasan|perumahan|komplek|kompleks)\b[\s,]*/i';
	for ( $i = 0; $i < 6; $i++ ) {
		$baru = preg_replace( $pola, '', $s );
		if ( $baru === $s ) {
			break;
		}
		$s = trim( $baru );
	}

	$s = preg_replace( '/^(?:jln?\.?|jalan)\s+/i', '', $s );
	$s = trim( $s, " 	\n\r\0\x0B,." );

	/*
	 * Terlalu pendek, tinggal angka, atau masih huruf kecil di awal = sisa
	 * kalimat ("teman pelapor", "perlengkapan ulang tahun"), bukan nama tempat.
	 * Nama tempat selalu diawali huruf kapital di data ini; kalau ragu, lebih
	 * baik jatuh ke segmen berikutnya daripada memakai potongan kalimat.
	 */
	if ( kh_seo_panjang( $s ) < 4 || ! preg_match( '/[a-z]{3}/i', $s ) || preg_match( '/^[a-z]/', $s ) ) {
		return '';
	}
	return $s;
}

/**
 * Susun judul + deskripsi sesuai konteks halaman.
 */
function kh_seo_context() {
	$out = array(
		'title' => 'Peta Kendaraan Hilang — Jawa Timur',
		'desc'  => 'Peta laporan kehilangan motor dan mobil di Jawa Timur, dikutip dari pemberitaan dan laporan warga.',
		'type'  => 'website',
		'img'   => kh_og_image_default(),
	);

	if ( is_front_page() ) {
		$count = wp_count_posts( 'kendaraan' );
		$n     = isset( $count->publish ) ? (int) $count->publish : 0;
		$out['title'] = 'Peta Kendaraan Hilang — Motor & Mobil Hilang Jatim';
		$out['desc']  = sprintf(
			'%d laporan kehilangan motor dan mobil di Surabaya, Sidoarjo, dan sekitarnya. Lihat lokasi, nomor polisi, dan status verifikasinya di peta.',
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

		$kendaraan = trim( preg_replace( '/\s+/', ' ', $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) ) );
		$tgl_txt   = $tanggal ? date_i18n( 'j F Y', strtotime( $tanggal ) ) : '';

		$nama = trim( ( $kendaraan ? $kendaraan : 'Kendaraan' ) . ( $plat ? ' (' . $plat . ')' : '' ) );

		$desc = $nama . ' hilang' . ( $lokasi ? ' di ' . $lokasi : '' )
			. ( $tgl_txt ? ', ' . $tgl_txt : '' ) . '.'
			. ( $warna ? ' Warna ' . strtolower( $warna ) . '.' : '' )
			. ' Bantu pantau dan laporkan kalau melihatnya.';

		/*
		 * Judul memakai nomor polisi di depan: pencarian warga hampir selalu
		 * memakai plat ("L 3051 BBE"), dan itu kata kunci yang paling spesifik
		 * sekaligus paling sedikit pesaingnya. Tanpa plat, judul jatuh ke judul
		 * pos seperti sebelumnya.
		 *
		 * Ekor judul (lokasi) dibatasi ~60 karakter: di atas itu Google memotong
		 * dengan "…" dan yang hilang justru bagian lokasi. Lokasi mentah dari
		 * laporan sering berupa kalimat ("saat diparkir di warung kopi"), jadi
		 * diambil segmen pertama yang masih berupa nama tempat, lalu jatuh ke
		 * segmen terakhir (biasanya kota), kecamatan, dan terakhir wilayah.
		 */
		if ( $plat ) {
			$wil    = wp_get_post_terms( $id, 'kendaraan_wilayah', array( 'fields' => 'names' ) );
			$wil    = ( ! is_wp_error( $wil ) && $wil ) ? $wil[0] : '';
			$dasar  = $plat . ' Hilang — ' . ( $kendaraan ? $kendaraan : 'Kendaraan' );

			$kandidat = array();
			if ( $lokasi ) {
				$segmen = array_values( array_filter( array_map( 'trim', explode( ',', $lokasi ) ) ) );
				if ( $segmen ) {
					$awal = kh_seo_rapikan_lokasi( $segmen[0] );
					if ( '' !== $awal ) {
						$kandidat[] = $awal;
					}
					$akhir = kh_seo_rapikan_lokasi( end( $segmen ) );
					if ( '' !== $akhir && ! in_array( $akhir, $kandidat, true ) ) {
						$kandidat[] = $akhir;
					}
				}
			}
			if ( $wil ) {
				$kandidat[] = $wil;
			}

			$judul = $dasar;
			foreach ( $kandidat as $k ) {
				if ( kh_seo_panjang( $dasar . ', ' . $k ) <= 60 ) {
					$judul = $dasar . ', ' . $k;
					break;
				}
			}
		} else {
			$judul = get_the_title( $id );
		}

		$out['title'] = $judul;
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
		$out['title'] = 'Laporkan Motor Hilang — Jawa Timur';
		$out['desc']  = 'Laporkan motor atau mobil yang hilang di Jawa Timur. Cukup masuk dengan akun Google, tandai lokasi di peta, lalu kirim.';
		return $out;
	}

	if ( is_page( 'wilayah' ) ) {
		$terms = kh_hub_wilayah_terms();
		$total = 0;
		foreach ( $terms as $t ) {
			$total += (int) $t->count;
		}
		$out['url']   = home_url( '/wilayah/' );
		$out['title'] = 'Kendaraan Hilang per Wilayah di Jawa Timur — Daftar & Peta';
		$out['desc']  = sprintf(
			'%d laporan kehilangan motor & mobil di %d kabupaten/kota Jawa Timur. Pilih wilayah untuk melihat nomor polisi dan lokasi terakhir.',
			$total,
			count( $terms )
		);
		return $out;
	}

	if ( function_exists( 'kh_pp_slug_aktif' ) && kh_pp_slug_aktif() ) {
		return kh_pp_seo_context( $out );
	}

	if ( is_page( 'analitik' ) ) {
		$count        = wp_count_posts( 'kendaraan' );
		$n            = isset( $count->publish ) ? (int) $count->publish : 0;
		$out['title'] = 'Statistik Kendaraan Hilang Jawa Timur';
		/* Halaman ini dirender template, jadi `post_content` kosong dan tanpa baris ini
		   meta description-nya keluar kosong. */
		$out['desc']  = sprintf(
			'Statistik kehilangan motor dan mobil di Jawa Timur dari %d laporan: sebaran per wilayah, model tersering, jam rawan, dan tren bulanan.',
			$n
		);
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

	if ( is_tax( 'kendaraan_kecamatan' ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$stat         = kh_kec_statistik( $term );
			$jam          = $stat['jam'] ? array_keys( $stat['jam'] ) : array();
			$out['url']   = get_term_link( $term );
			$out['title'] = 'Motor & Mobil Hilang Kecamatan ' . $term->name . ', Surabaya';
			$out['desc']  = sprintf(
				'%d laporan kehilangan motor dan mobil di Kecamatan %s, Surabaya%s. Lengkap dengan nomor polisi, lokasi terakhir, dan status verifikasi.',
				(int) $stat['jumlah'],
				$term->name,
				$jam ? ', paling sering sekitar pukul ' . $jam[0] . '.00' : ''
			);
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
		$out['title'] = 'Arsip Laporan Kendaraan Hilang — Jawa Timur';
		$out['desc']  = 'Seluruh laporan kehilangan kendaraan di Jawa Timur, lengkap dengan nomor polisi, lokasi, dan status verifikasi.';
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

	/*
	 * Taksonomi `jenis` dan `status` masing-masing hanya punya SATU term
	 * ("motor", "hilang") sehingga halamannya duplikat dari arsip — tidak layak
	 * diindeks. Wilayah tetap diindeks (10 halaman, isinya berbeda).
	 */
	if ( is_tax( array( 'kendaraan_jenis', 'kendaraan_status' ) ) ) {
		$noindex = true;
	}

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
 * Buang taksonomi satu-term dari sitemap supaya Google tidak dibimbing ke
 * halaman noindex.
 */
add_filter( 'wp_sitemaps_taxonomies', function ( $taxonomies ) {
	unset( $taxonomies['kendaraan_jenis'], $taxonomies['kendaraan_status'] );
	return $taxonomies;
} );

/**
 * Verifikasi Google Search Console (per situs).
 *
 * Token dibaca dari opsi `kh_gsc_token` (Kendaraan Hilang → Pengaturan) dan TIDAK
 * di-hardcode: kalau plugin ini dipasang di situs lain, meta tag milik situs ini
 * tidak boleh ikut terkirim — token itu hanya sah untuk domain pemiliknya.
 *
 * Verifikasi lewat TXT DNS lebih disarankan (berlaku untuk properti Domain maupun
 * URL prefix), sehingga opsi ini boleh dikosongkan.
 */
add_action( 'wp_head', function () {
	$token = kh_gsc_token();
	if ( '' === $token ) {
		return;
	}
	echo '<meta name="google-site-verification" content="' . esc_attr( $token ) . '">' . "\n";
}, 3 );

/**
 * Token Search Console situs ini. Bisa ditimpa lewat filter `kh_gsc_token`.
 */
function kh_gsc_token() {
	return (string) apply_filters( 'kh_gsc_token', get_option( 'kh_gsc_token', '' ) );
}

/**
 * Pakai judul SEO juga untuk tag <title> (bukan hanya og:title).
 */
add_filter( 'document_title_parts', function ( $parts ) {
	$c = kh_seo_context();
	if ( empty( $c['title'] ) ) {
		return $parts;
	}
	/* Halaman laporan, hub wilayah, panduan & kecamatan: jangan tambah nama situs —
	   judul jadi terlalu panjang dan kata kunci utama terpotong di hasil pencarian. */
	if ( is_singular( 'kendaraan' ) || is_page( 'wilayah' ) || is_tax( 'kendaraan_kecamatan' ) || ( function_exists( 'kh_pp_slug_aktif' ) && kh_pp_slug_aktif() ) ) {
		return array( 'title' => $c['title'] );
	}
	$site = get_bloginfo( 'name' );
	$t    = $c['title'];
	if ( false === strpos( $t, $site ) ) {
		$t .= ' — ' . $site;
	}
	return array( 'title' => $t );
}, 20 );
