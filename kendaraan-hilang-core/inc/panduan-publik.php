<?php
/**
 * Pendaftaran 5 halaman pilar `/panduan/…`, penyaji blok konten, dan data live.
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once KH_CORE_DIR . 'inc/panduan-publik-data.php';

/* ------------------------------------------------------------------ */
/* Halaman WordPress (induk + 5 anak)                                  */
/* ------------------------------------------------------------------ */

/**
 * Apakah request ini bagian dari /panduan/?
 */
function kh_pp_is_panduan() {
	return is_page( 'panduan' ) || false !== strpos( (string) get_page_uri( get_queried_object_id() ), 'panduan/' );
}

/**
 * Slug halaman panduan yang sedang dibuka ('' kalau bukan halaman panduan).
 *
 * @return string
 */
function kh_pp_slug_aktif() {
	if ( ! is_page() ) {
		return '';
	}
	$uri  = (string) get_page_uri( get_queried_object_id() );
	$slug = basename( $uri );
	if ( 'panduan' === $slug ) {
		return 'panduan';
	}
	foreach ( kh_panduan_slugs() as $s ) {
		if ( $s === $slug ) {
			return $s;
		}
	}
	return '';
}

/**
 * Buat halaman induk + anak sekali saja (pola sama seperti /wilayah/).
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_pp_pages' ) === KH_CORE_VERSION ) {
		return;
	}

	$induk = get_page_by_path( 'panduan', OBJECT, 'page' );
	if ( ! $induk ) {
		$id = wp_insert_post(
			array(
				'post_title'   => 'Panduan Kendaraan Hilang',
				'post_name'    => 'panduan',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			)
		);
		$induk = $id && ! is_wp_error( $id ) ? get_post( $id ) : null;
	}
	if ( ! $induk ) {
		return;
	}

	foreach ( kh_panduan_slugs() as $slug ) {
		$item = kh_panduan_item( $slug );
		if ( ! $item ) {
			continue;
		}
		$ada = get_page_by_path( 'panduan/' . $slug, OBJECT, 'page' );
		if ( $ada ) {
			continue;
		}
		$anak = wp_insert_post(
			array(
				'post_title'   => $item['judul'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => $induk->ID,
				'post_content' => '',
			)
		);
		if ( $anak && ! is_wp_error( $anak ) ) {
			update_post_meta( $anak, '_kh_panduan', $slug );
		}
	}

	update_option( 'kh_pp_pages', KH_CORE_VERSION, false );
} );

/**
 * Template panduan (induk & anak). Prioritas 23 = sesudah handler lain.
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_admin() || ! kh_pp_is_panduan() ) {
		return $template;
	}
	$file = KH_CORE_DIR . 'templates/panduan.php';
	return file_exists( $file ) ? $file : $template;
}, 23 );

/* ------------------------------------------------------------------ */
/* Format teks ringan: **tebal** dan [label](/tautan/)                 */
/* ------------------------------------------------------------------ */

/**
 * Ubah penanda sederhana di data panduan menjadi HTML aman.
 *
 * @param string $teks Teks sumber.
 * @return string
 */
function kh_pp_fmt( $teks ) {
	$teks = esc_html( $teks );
	/* [label](/path/) → tautan internal */
	$teks = preg_replace_callback(
		'/\[([^\]]+)\]\((\/[^)\s]*)\)/',
		function ( $m ) {
			return '<a href="' . esc_url( home_url( $m[2] ) ) . '">' . $m[1] . '</a>';
		},
		$teks
	);
	/* **tebal** */
	$teks = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $teks );
	return $teks;
}

/* ------------------------------------------------------------------ */
/* Data live untuk blok 'data'                                         */
/* ------------------------------------------------------------------ */

/**
 * Query laporan wilayah Surabaya.
 *
 * @param array $args Argumen tambahan.
 * @return WP_Query
 */
function kh_pp_q_surabaya( $args = array() ) {
	return new WP_Query(
		wp_parse_args(
			$args,
			array(
				'post_type'      => 'kendaraan',
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => 'kendaraan_wilayah',
						'field'    => 'slug',
						'terms'    => 'surabaya',
					),
				),
			)
		)
	);
}

/**
 * Tabel laporan terbaru untuk halaman panduan.
 *
 * Halaman panduan yang isinya penjelasan panjang nyaris tidak punya tautan ke
 * halaman laporan; padahal 434 halaman laporan itulah yang butuh aliran
 * otoritas dan anchor text nomor polisi. Tabel ini menutup celah itu dengan
 * data live (bukan tautan statis yang cepat basi).
 *
 * @param string $scope '' (semua) | 'surabaya' | 'media' (terverifikasi media).
 * @param int    $jumlah Jumlah baris.
 */
function kh_pp_blok_laporan_terbaru( $scope = '', $jumlah = 8 ) {
	$args = array( 'posts_per_page' => (int) $jumlah );

	if ( 'surabaya' === $scope ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'kendaraan_wilayah',
				'field'    => 'slug',
				'terms'    => 'surabaya',
			),
		);
	} elseif ( 'media' === $scope ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'   => 'kh_verifikasi',
				'value' => 'media',
			),
		);
	}

	$q = kh_hub_query( $args );

	/* Kalau belum ada laporan terverifikasi media, jangan tampilkan tabel kosong. */
	if ( ! $q->have_posts() && 'media' === $scope ) {
		$q = kh_hub_query( array( 'posts_per_page' => (int) $jumlah ) );
	}
	if ( ! $q->have_posts() ) {
		echo '<p>Belum ada laporan di arsip untuk bagian ini.</p>';
		return;
	}

	echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr>'
		. '<th>Nomor polisi</th><th>Kendaraan</th><th>Lokasi</th><th>Tanggal</th>'
		. '</tr></thead><tbody>';

	while ( $q->have_posts() ) {
		$q->the_post();
		$id    = get_the_ID();
		$plat  = get_post_meta( $id, 'kh_plat_masked', true );
		$kend  = preg_replace( '/\s+/', ' ', trim( get_post_meta( $id, 'kh_merk', true ) . ' ' . get_post_meta( $id, 'kh_model', true ) . ' ' . get_post_meta( $id, 'kh_tahun', true ) ) );
		$lok   = (string) get_post_meta( $id, 'kh_lokasi', true );
		$seg   = explode( ',', $lok );
		$lok   = trim( $seg[0] );
		if ( function_exists( 'kh_seo_panjang' ) && kh_seo_panjang( $lok ) > 38 ) {
			$lok = wp_trim_words( $lok, 4, '…' );
		}

		printf(
			'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_url( get_permalink() ),
			esc_html( $plat ? $plat : kh_hub_label( $id ) ),
			esc_html( $kend ),
			esc_html( $lok ),
			esc_html( kh_hub_tanggal( $id ) )
		);
	}

	wp_reset_postdata();
	echo '</tbody></table></div>';
}

/**
 * Cetak blok data sesuai kuncinya.
 *
 * @param string $kunci Nama blok.
 */
function kh_pp_data_block( $kunci ) {
	/* `laporan-terbaru`, `laporan-terbaru:surabaya`, `laporan-terbaru:media` */
	if ( 0 === strpos( $kunci, 'laporan-terbaru' ) ) {
		$bagian = explode( ':', $kunci );
		kh_pp_blok_laporan_terbaru( isset( $bagian[1] ) ? $bagian[1] : '' );
		return;
	}

	switch ( $kunci ) {

		case 'checklist':
			echo '<div class="kh-pp-box"><strong>Dokumen yang dibawa ke kantor polisi</strong><ul class="kh-pp-list">';
			foreach ( array( 'KTP pemilik kendaraan + KTP pelapor', 'STNK asli', 'BPKB asli (atau surat keterangan leasing)', 'Faktur/kwitansi pembelian (kalau ada)', 'Kronologi tertulis: tanggal, jam, lokasi, ciri kendaraan, nomor polisi', 'Foto kendaraan & dokumen' ) as $li ) {
				echo '<li>' . esc_html( $li ) . '</li>';
			}
			echo '</ul></div>';
			break;

		case 'cek-plat':
			echo '<div class="kh-pp-box"><strong>Cari nomor polisi di arsip kami</strong><p>Tempel plat di kotak pencarian peta (contoh: <code>L 3051 BBE</code> atau <code>3051</code>), atau buka laporan terbaru berikut:</p><ul class="kh-pp-list">';
			$q = kh_hub_query( array( 'posts_per_page' => 8 ) );
			while ( $q->have_posts() ) {
				$q->the_post();
				printf(
					'<li><a href="%s">%s</a> — %s</li>',
					esc_url( get_permalink() ),
					esc_html( kh_hub_label( get_the_ID() ) ),
					esc_html( kh_hub_tanggal( get_the_ID() ) )
				);
			}
			wp_reset_postdata();
			echo '</ul><p><a class="kh-legal-btn" href="' . esc_url( home_url( '/' ) ) . '">Buka peta &amp; cari plat</a></p></div>';
			break;

		case 'ringkas-surabaya':
			$term  = get_term_by( 'slug', 'surabaya', 'kendaraan_wilayah' );
			$total = $term ? (int) $term->count : 0;

			/* Bulan berjalan (YYYY-MM) */
			$bulan_ini = (int) gmdate( 'Ym', strtotime( current_time( 'Y-m-d' ) ) );
			$q_bulan   = kh_pp_q_surabaya(
				array(
					'posts_per_page' => 300,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'key'     => 'kh_tgl',
							'value'   => $bulan_ini . '01',
							'compare' => '>=',
							'type'    => 'NUMERIC',
						),
					),
				)
			);

			$q_dit = kh_pp_q_surabaya(
				array(
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'taxonomy' => 'kendaraan_wilayah',
							'field'    => 'slug',
							'terms'    => 'surabaya',
						),
						array(
							'taxonomy' => 'kendaraan_status',
							'field'    => 'slug',
							'terms'    => 'ditemukan',
						),
					),
				)
			);

			echo '<div class="kh-pp-stats">';
			printf( '<div><b>%d</b><span>laporan Surabaya</span></div>', $total );
			printf( '<div><b>%d</b><span>bulan ini</span></div>', (int) $q_bulan->post_count );
			printf( '<div><b>%d</b><span>sudah ditemukan</span></div>', (int) $q_dit->post_count );
			echo '</div>';
			break;

		case 'tabel-kecamatan':
			$terms = get_terms(
				array(
					'taxonomy'   => 'kendaraan_kecamatan',
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 12,
				)
			);
			if ( is_wp_error( $terms ) || ! $terms ) {
				echo '<p>Data kecamatan belum tersedia.</p>';
				return;
			}
			echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr><th>Kecamatan</th><th>Laporan</th></tr></thead><tbody>';
			foreach ( $terms as $t ) {
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%d</td></tr>',
					esc_url( get_term_link( $t ) ),
					esc_html( $t->name ),
					(int) $t->count
				);
			}
			echo '</tbody></table></div><p class="kh-hub-more"><a href="' . esc_url( home_url( '/wilayah/surabaya/' ) ) . '">Semua kecamatan &rsaquo;</a></p>';
			break;

		case 'tabel-model':
			$q = kh_pp_q_surabaya( array( 'posts_per_page' => 300, 'fields' => 'ids' ) );
			$hitung = array();
			foreach ( $q->posts as $id ) {
				$m = trim( get_post_meta( $id, 'kh_merk', true ) . ' ' . get_post_meta( $id, 'kh_model', true ) );
				if ( '' !== $m ) {
					$hitung[ $m ] = isset( $hitung[ $m ] ) ? $hitung[ $m ] + 1 : 1;
				}
			}
			arsort( $hitung );
			$hitung = array_slice( $hitung, 0, 8, true );
			if ( ! $hitung ) {
				echo '<p>Belum ada data model.</p>';
				return;
			}
			echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr><th>Model</th><th>Laporan</th></tr></thead><tbody>';
			foreach ( $hitung as $m => $n ) {
				printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html( $m ), (int) $n );
			}
			echo '</tbody></table></div>';
			break;

		case 'tabel-jam':
			$q = kh_pp_q_surabaya( array( 'posts_per_page' => 300, 'fields' => 'ids' ) );
			$jam = array();
			foreach ( $q->posts as $id ) {
				$h = substr( (string) get_post_meta( $id, 'kh_jam', true ), 0, 2 );
				if ( ctype_digit( $h ) ) {
					$jam[ $h ] = isset( $jam[ $h ] ) ? $jam[ $h ] + 1 : 1;
				}
			}
			arsort( $jam );
			$jam = array_slice( $jam, 0, 6, true );
			if ( ! $jam ) {
				echo '<p>Belum cukup data jam kejadian.</p>';
				return;
			}
			echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr><th>Rentang jam</th><th>Laporan</th></tr></thead><tbody>';
			foreach ( $jam as $h => $n ) {
				printf( '<tr><td>%s.00 &ndash; %s.59</td><td>%d</td></tr>', esc_html( $h ), esc_html( $h ), (int) $n );
			}
			echo '</tbody></table></div>';
			break;

		case 'tabel-kecamatan-semua':
			$terms = get_terms(
				array(
					'taxonomy'   => 'kendaraan_kecamatan',
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			if ( is_wp_error( $terms ) || ! $terms ) {
				echo '<p>Data kecamatan belum tersedia.</p>';
				return;
			}
			$total = 0;
			foreach ( $terms as $t ) {
				$total += (int) $t->count;
			}
			echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr><th>Kecamatan</th><th>Laporan</th><th>Bagian</th></tr></thead><tbody>';
			$peta = kh_kecamatan_data();
			foreach ( $terms as $t ) {
				$region = isset( $peta[ $t->name ][0] ) ? $peta[ $t->name ][0] : '—';
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%d</td><td>%s</td></tr>',
					esc_url( get_term_link( $t ) ),
					esc_html( $t->name ),
					(int) $t->count,
					esc_html( $region )
				);
			}
			echo '</tbody></table></div>';
			printf(
				'<p class="kh-hub-more">%d laporan terpetakan ke kecamatan. Laporan tanpa koordinat, atau yang titiknya lebih dari %.1f km dari pusat kecamatan, tidak masuk tabel ini.</p>',
				(int) $total,
				(float) ( KH_KEC_RADIUS / 1000 )
			);
			break;

		case 'tabel-region':
			$peta  = kh_kecamatan_data();
			$terms = get_terms(
				array(
					'taxonomy'   => 'kendaraan_kecamatan',
					'hide_empty' => true,
				)
			);
			$reg  = array();
			$kecs = array();
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					if ( ! isset( $peta[ $t->name ][0] ) ) {
						continue;
					}
					$r = $peta[ $t->name ][0];
					$reg[ $r ]  = isset( $reg[ $r ] ) ? $reg[ $r ] + (int) $t->count : (int) $t->count;
					$kecs[ $r ] = isset( $kecs[ $r ] ) ? $kecs[ $r ] + 1 : 1;
				}
			}
			arsort( $reg );
			if ( ! $reg ) {
				echo '<p>Data bagian kota belum tersedia.</p>';
				return;
			}
			echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr><th>Bagian Surabaya</th><th>Kecamatan</th><th>Laporan</th></tr></thead><tbody>';
			foreach ( $reg as $r => $n ) {
				printf(
					'<tr><td>Surabaya %s</td><td>%d</td><td>%d</td></tr>',
					esc_html( $r ),
					(int) $kecs[ $r ],
					(int) $n
				);
			}
			echo '</tbody></table></div>';
			break;

		case 'tabel-bulan':
			$q   = kh_pp_q_surabaya( array( 'posts_per_page' => 300, 'fields' => 'ids' ) );
			$bln = array();
			foreach ( $q->posts as $id ) {
				$tgl = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
				if ( ! $tgl ) {
					continue;
				}
				$k = substr( $tgl, 0, 7 );
				$bln[ $k ] = isset( $bln[ $k ] ) ? $bln[ $k ] + 1 : 1;
			}
			krsort( $bln );
			$bln = array_slice( $bln, 0, 6, true );
			if ( ! $bln ) {
				echo '<p>Belum ada data bulanan.</p>';
				return;
			}
			echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr><th>Bulan</th><th>Laporan</th></tr></thead><tbody>';
			$nama_bln = array( 1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember' );
			foreach ( $bln as $k => $n ) {
				$y = substr( $k, 0, 4 );
				$m = (int) substr( $k, 5, 2 );
				printf( '<tr><td>%s %s</td><td>%d</td></tr>', esc_html( $nama_bln[ $m ] ), esc_html( $y ), (int) $n );
			}
			echo '</tbody></table></div>';
			break;

		case 'samsat-surabaya':
			echo '<div class="kh-pp-box"><strong>Kantor Samsat di Surabaya</strong><p>Ada empat kantor Samsat di Kota Surabaya: <strong>Surabaya Timur</strong>, <strong>Surabaya Selatan</strong>, <strong>Surabaya Barat</strong>, serta <strong>Surabaya Utara &amp; Pusat</strong>. Datang ke kantor yang sesuai wilayah kendaraanmu terdaftar.</p><p>Alamat dan jam layanan berubah dari waktu ke waktu, jadi cek dulu di <a href="https://samsatdigital.id/" target="_blank" rel="noopener nofollow">samsatdigital.id</a> (situs resmi Samsat Digital Nasional) sebelum berangkat. Untuk layanan online, aplikasi resminya bernama <strong>SIGNAL</strong>.</p></div>';
			break;

		case 'ditemukan-terbaru':
			$q = new WP_Query(
				array(
					'post_type'      => 'kendaraan',
					'post_status'    => 'publish',
					'posts_per_page' => 6,
					'no_found_rows'  => true,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'taxonomy' => 'kendaraan_status',
							'field'    => 'slug',
							'terms'    => 'ditemukan',
						),
					),
				)
			);
			if ( ! $q->have_posts() ) {
				echo '<p>Belum ada laporan berstatus “ditemukan”.</p>';
				return;
			}
			echo '<div class="kh-pp-tablewrap"><table class="kh-pp-table"><thead><tr><th>Kendaraan</th><th>Tanggal</th></tr></thead><tbody>';
			while ( $q->have_posts() ) {
				$q->the_post();
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%s</td></tr>',
					esc_url( get_permalink() ),
					esc_html( kh_hub_label( get_the_ID() ) ),
					esc_html( kh_hub_tanggal( get_the_ID() ) )
				);
			}
			wp_reset_postdata();
			echo '</tbody></table></div>';
			break;
	}
}

/* ------------------------------------------------------------------ */
/* SEO + structured data                                               */
/* ------------------------------------------------------------------ */

/**
 * Tambah konteks SEO untuk halaman panduan.
 *
 * @param array $out Konteks dari kh_seo_context().
 * @return array
 */
function kh_pp_seo_context( $out ) {
	$slug = kh_pp_slug_aktif();
	if ( ! $slug ) {
		return $out;
	}
	if ( 'panduan' === $slug ) {
		$out['title'] = 'Panduan Kendaraan Hilang: Lapor, Cek Plat & Data Surabaya';
		$out['desc']  = 'Lima panduan praktis: cara melapor ke polisi, cek plat nomor, data motor hilang Surabaya, kawasan rawan, dan kendaraan yang ditemukan lagi.';
		return $out;
	}
	$item = kh_panduan_item( $slug );
	if ( ! $item ) {
		return $out;
	}
	$out['title'] = $item['seo_title'];
	$out['desc']  = $item['seo_desc'];
	$out['type']  = 'article';
	return $out;
}

/**
 * FAQ + breadcrumb JSON-LD halaman panduan (dipasang setelah schema inti).
 */
add_action( 'wp_head', function () {
	$slug = kh_pp_slug_aktif();
	if ( ! $slug || 'panduan' === $slug ) {
		return;
	}
	$item = kh_panduan_item( $slug );
	if ( ! $item ) {
		return;
	}

	$graph = array();

	$graph[] = array(
		'@type'       => 'Article',
		'@id'         => get_permalink() . '#article',
		'headline'    => $item['judul'],
		'description' => $item['seo_desc'],
		'url'         => get_permalink(),
		'inLanguage'  => 'id-ID',
		'dateModified' => gmdate( 'c', strtotime( $item['tinjau'] ) ),
		'isPartOf'    => array( '@id' => home_url( '/' ) . '#website' ),
		'author'      => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		),
	);

	$graph[] = array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => 'Peta Kendaraan Hilang',
				'item'     => home_url( '/' ),
			),
			array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => 'Panduan',
				'item'     => home_url( '/panduan/' ),
			),
			array(
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $item['judul'],
				'item'     => get_permalink(),
			),
		),
	);

	foreach ( $item['blok'] as $b ) {
		if ( 'faq' !== $b['t'] ) {
			continue;
		}
		$q = array();
		foreach ( $b['x'] as $pair ) {
			$q[] = array(
				'@type'          => 'Question',
				'name'           => $pair[0],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $pair[1],
				),
			);
		}
		$graph[] = array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink() . '#faq',
			'mainEntity' => $q,
		);
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
}, 21 );
