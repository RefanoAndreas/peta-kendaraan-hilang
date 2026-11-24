<?php
/**
 * Hub SEO: tautan internal yang bisa dibaca mesin pencari.
 *
 * Latar belakang (audit 24 Nov 2026): beranda peta hanya memuat ±725 karakter
 * teks dan NOL tautan ke halaman laporan, sehingga 432 halaman detail hanya
 * ditemukan lewat sitemap. File ini menambahkan:
 *
 * 1. `kh_hub_block()`      — blok teks + daftar tautan di bawah peta beranda.
 * 2. Halaman `/wilayah/`   — indeks semua wilayah (dibuat otomatis).
 * 3. `kh_related_block()`  — modul "laporan lain" di halaman detail.
 *
 * Semua keluaran server-side (tanpa JS) supaya terbaca crawler.
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Query bersama                                                       */
/* ------------------------------------------------------------------ */

/**
 * Query laporan yang masih tampil (belum masuk arsip >KH_ARSIP_HARI).
 *
 * @param array $args Argumen tambahan WP_Query.
 * @return WP_Query
 */
function kh_hub_query( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'post_type'           => 'kendaraan',
			'post_status'         => 'publish',
			'posts_per_page'      => 30,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_key'            => 'kh_tgl', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'             => 'meta_value_num',
			'order'               => 'DESC',
		)
	);

	if ( empty( $args['meta_query'] ) && empty( $args['tax_query'] ) && empty( $args['s'] ) ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => 'kh_tgl',
				'value'   => kh_hub_batas_arsip(),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			),
		);
	}

	return new WP_Query( $args );
}

/**
 * Batas arsip sebagai angka Ymd (dibandingkan dengan meta `kh_tgl`).
 *
 * `current_time( 'timestamp' )` sudah deprecated sejak WP 5.3, jadi pakai
 * wp_date() yang menghormati timezone situs.
 *
 * @return int
 */
function kh_hub_batas_arsip() {
	return (int) wp_date( 'Ymd', strtotime( '-' . (int) KH_ARSIP_HARI . ' days' ) );
}

/**
 * Wilayah (taksonomi kendaraan_wilayah) urut jumlah laporan terbanyak.
 *
 * @return WP_Term[]
 */
function kh_hub_wilayah_terms() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'kendaraan_wilayah',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Label ringkas satu laporan: "L 3051 BBE — Honda Beat 2025, Sumur Welut".
 *
 * @param int $id Post ID.
 * @return string
 */
function kh_hub_label( $id ) {
	$plat  = get_post_meta( $id, 'kh_plat_masked', true );
	$merk  = get_post_meta( $id, 'kh_merk', true );
	$model = get_post_meta( $id, 'kh_model', true );
	$tahun = get_post_meta( $id, 'kh_tahun', true );
	$lok   = get_post_meta( $id, 'kh_lokasi', true );

	$kendaraan = trim( $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) );
	$out       = $plat ? $plat : $kendaraan;
	if ( $plat && $kendaraan ) {
		$out .= ' — ' . $kendaraan;
	}
	if ( $lok ) {
		$out .= ', ' . $lok;
	}
	return $out;
}

/**
 * Tanggal kejadian yang sudah diformat ("23 November 2026").
 *
 * @param int $id Post ID.
 * @return string
 */
function kh_hub_tanggal( $id ) {
	$t = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
	return $t ? date_i18n( 'j F Y', strtotime( $t ) ) : '';
}

/* ------------------------------------------------------------------ */
/* 1. Blok di bawah peta beranda                                       */
/* ------------------------------------------------------------------ */

/**
 * Cetak blok teks + daftar tautan di bawah peta (beranda).
 *
 * ⚠️ STATUS: TIDAK DIPANGGIL. 24 Nov 2026 user minta beranda tetap single page
 * (satu layar, tanpa scroll), jadi `templates/front-peta.php` tidak lagi memanggil
 * fungsi ini dan CSS mengunci viewport lagi. Isi & tautan yang dulu di sini hidup
 * di `/wilayah/` (template wilayah-hub.php) + tautan "Daftar laporan & wilayah" di
 * footer panel peta, sehingga halaman laporan tetap tidak yatim.
 * Fungsi dipertahankan supaya mudah diaktifkan lagi (tambah satu baris pemanggilan
 * di front-peta.php + longgarkan scroll-lock di ui.css).
 *
 * Sengaja diletakkan SETELAH #app, bukan sebagai overlay: semua pengukuran
 * posisi kontrol peta di HANDOFF diukur di dalam #app dan tidak boleh bergeser.
 */
function kh_hub_block() {
	if ( ! is_front_page() ) {
		return;
	}

	$q        = kh_hub_query( array( 'posts_per_page' => 30 ) );
	$wilayah  = kh_hub_wilayah_terms();
	$total    = (int) wp_count_posts( 'kendaraan' )->publish;
	?>
	<section class="kh-hub" id="kh-hub" aria-labelledby="kh-hub-title">
		<div class="kh-hub-inner">
			<a class="kh-hub-back" href="#app">&uarr; Kembali ke peta</a>

			<h2 id="kh-hub-title">Daftar laporan kendaraan hilang di Jawa Timur</h2>

			<p class="kh-hub-lead">
				Peta di atas menampilkan <?php echo (int) $total; ?> laporan kehilangan motor dan mobil yang
				dikutip dari pemberitaan Radio Suara Surabaya FM 100 serta laporan warga, mencakup Surabaya,
				Sidoarjo, Gresik, Jombang, Pasuruan, Probolinggo, Mojokerto, Malang, Bangkalan, dan Lamongan.
				Halaman ini adalah versi teks dari peta: setiap laporan punya halaman sendiri berisi
				nomor polisi lengkap, merek dan model, warna, waktu kejadian, lokasi terakhir, dan status
				verifikasinya (belum diverifikasi, terverifikasi media, sudah lapor polisi, atau ditemukan).
			</p>

			<div class="kh-hub-cols">
				<div class="kh-hub-col">
					<h3>Laporan terbaru</h3>
					<ul class="kh-hub-list">
						<?php
						if ( $q->have_posts() ) {
							while ( $q->have_posts() ) {
								$q->the_post();
								$lid  = get_the_ID();
								$tgl  = kh_hub_tanggal( $lid );
								printf(
									'<li><a href="%s">%s</a>%s</li>',
									esc_url( get_permalink( $lid ) ),
									esc_html( kh_hub_label( $lid ) ),
									$tgl ? '<time datetime="' . esc_attr( gmdate( 'Y-m-d', strtotime( kh_normalize_tanggal( get_post_meta( $lid, 'kh_tanggal', true ) ) ) ) ) . '">' . esc_html( $tgl ) . '</time>' : ''
								);
							}
							wp_reset_postdata();
						} else {
							echo '<li>Belum ada laporan aktif.</li>';
						}
						?>
					</ul>
					<p class="kh-hub-more">
						<a href="<?php echo esc_url( get_post_type_archive_link( 'kendaraan' ) ); ?>">Lihat semua laporan &rsaquo;</a>
					</p>
				</div>

				<div class="kh-hub-col">
					<h3>Kendaraan hilang per wilayah</h3>
					<ul class="kh-hub-wil">
						<?php foreach ( $wilayah as $t ) : ?>
							<li>
								<a href="<?php echo esc_url( get_term_link( $t ) ); ?>">
									Kendaraan hilang di <?php echo esc_html( $t->name ); ?>
									<span><?php echo (int) $t->count; ?> laporan</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="kh-hub-more">
						<a href="<?php echo esc_url( home_url( '/wilayah/' ) ); ?>">Semua wilayah &rsaquo;</a>
					</p>
				</div>
			</div>

			<h3>Cara memakai halaman ini</h3>
			<p>
				Kalau kamu melihat kendaraan yang mirip dengan salah satu laporan, buka halaman laporannya lalu
				gunakan tombol <em>Saya menemukan kendaraan ini</em>. Informasimu masuk ke admin lebih dulu dan
				admin yang menghubungi pelapor &mdash; bukan kanal kepolisian. Untuk keperluan resmi (misalnya
				mengurus asuransi atau memblokir STNK), laporan tetap harus dibuat di Polsek/Polres setempat
				dengan membawa STNK dan BPKB. Nomor polisi sengaja ditampilkan lengkap supaya warga mudah
				mengenali kendaraan di jalan, pasar, bengkel, atau tempat parkir; kalau ragu, cukup laporkan
				lewat situs, jangan mengejar atau mengadang pengendara.
			</p>
			<p>
				Titik di peta adalah <strong>perkiraan</strong>: lokasi diambil dari berita, jadi halaman laporan
				menampilkan lingkaran radius sekitar 1 km dari titik tersebut. Data yang sudah berumur lebih dari
				<?php echo (int) KH_ARSIP_HARI; ?> hari dipindahkan ke arsip dan hanya muncul di peta kalau filter
				arsip dinyalakan, tetapi halamannya tetap bisa dibuka dan dibagikan. Punya laporan sendiri?
				Gunakan <a href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">formulir lapor</a> (gratis,
				cukup masuk dengan akun Google).
			</p>
			<p class="kh-hub-note">
				Situs ini bukan media dan tidak berafiliasi dengan kepolisian maupun Radio Suara Surabaya.
				Yang dikutip dari sumber hanya datanya (plat, model, waktu, lokasi); hak atas desain dan foto
				tetap milik pemiliknya. Ada data yang perlu dikoreksi atau dihapus?
				<a href="<?php echo esc_url( home_url( '/kontak/' ) ); ?>">Hubungi kami</a> &mdash; dilayani 1&times;24 jam.
			</p>
		</div>
	</section>
	<?php
}

/**
 * Salinan HTML awal untuk panel daftar (`#list`) di beranda.
 *
 * Kenapa: daftar di sidebar dirender JS dari REST, jadi HTML mentah beranda kosong
 * dan crawler tidak melihat laporan apa pun. Fungsi ini mencetak 30 item pertama
 * dengan markup yang SAMA seperti `cardHtml()` di assets/ui.js, ditambah tautan
 * "Detail laporan" ke halaman laporan. JS tetap mengganti isi #list saat boot,
 * jadi tidak ada perilaku ganda: sebelum JS siap item ini bisa diklik lewat tautan,
 * sesudahnya klik baris kembali berfungsi "terbang ke pin" seperti biasa.
 */
function kh_list_awal( $n = 30 ) {
	$q = kh_hub_query( array( 'posts_per_page' => (int) $n ) );
	if ( ! $q->have_posts() ) {
		return;
	}

	$labels = kh_verifikasi_levels();

	while ( $q->have_posts() ) {
		$q->the_post();
		$id     = get_the_ID();
		$plat   = get_post_meta( $id, 'kh_plat_masked', true );
		$merk   = get_post_meta( $id, 'kh_merk', true );
		$model  = get_post_meta( $id, 'kh_model', true );
		$tahun  = get_post_meta( $id, 'kh_tahun', true );
		$warna  = get_post_meta( $id, 'kh_warna', true );
		$lokasi = get_post_meta( $id, 'kh_lokasi', true );
		$jam    = get_post_meta( $id, 'kh_jam', true );
		$polsek = get_post_meta( $id, 'kh_polsek', true );
		$tgl    = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
		$verif  = kh_verifikasi_of( $id );
		$status = wp_get_post_terms( $id, 'kendaraan_status', array( 'fields' => 'names' ) );
		$got    = ( $status && 'ditemukan' === strtolower( $status[0] ) );

		if ( $got ) {
			$cls  = ' got';
			$text = 'DITEMUKAN';
		} elseif ( $polsek ) {
			$cls  = ' rep';
			$text = 'LAPOR POLISI';
		} else {
			$cls  = '';
			$text = strtoupper( $labels[ $verif ] );
		}
		$tag_cls = $got ? ' got' : ( $polsek ? ' polisi' : ' ' . $verif );

		$kendaraan = trim( $merk . ' ' . $model );
		if ( $tahun ) {
			$kendaraan .= ' (' . $tahun . ')';
		}

		$waktu = '';
		if ( $tgl ) {
			$waktu = date_i18n( 'l, j/n/Y', strtotime( $tgl ) );
			if ( $jam ) {
				$waktu .= ' &middot; ' . $jam . ' WIB';
			}
		} elseif ( $jam ) {
			$waktu = $jam . ' WIB';
		}

		printf(
			'<div class="item%s" data-pre="1"><div class="row"><span class="plat">%s</span><span class="tag%s">%s</span></div><div class="m">%s%s</div><div class="s">%s%s</div><a class="it-detail" href="%s">Detail laporan &rsaquo;</a></div>',
			esc_attr( $cls ),
			esc_html( $plat ),
			esc_attr( $tag_cls ),
			esc_html( $text ),
			esc_html( $kendaraan ),
			$warna ? ' &middot; ' . esc_html( $warna ) : '',
			esc_html( $lokasi ),
			$waktu ? '<br>' . $waktu : '',
			esc_url( get_permalink( $id ) )
		);
	}
	wp_reset_postdata();
}

/* ------------------------------------------------------------------ */
/* 2. Halaman /wilayah/                                                */
/* ------------------------------------------------------------------ */

/**
 * Buat halaman WordPress `/wilayah/` sekali saja (pola sama seperti /analitik/).
 *
 * Tidak memakai rewrite rule ke query var kosong: aturan `^wilayah/?$` semacam
 * itu membuat WP menganggapnya beranda lalu mengirim status 404 (lihat HANDOFF §2a).
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kh_wilayah_page' ) && get_post( (int) get_option( 'kh_wilayah_page' ) ) ) {
		return;
	}
	$ada = get_page_by_path( 'wilayah', OBJECT, 'page' );
	if ( $ada ) {
		update_option( 'kh_wilayah_page', $ada->ID );
		return;
	}
	$baru = wp_insert_post(
		array(
			'post_title'   => 'Kendaraan Hilang per Wilayah',
			'post_name'    => 'wilayah',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		)
	);
	if ( $baru && ! is_wp_error( $baru ) ) {
		update_option( 'kh_wilayah_page', $baru );
	}
} );

/**
 * Apakah request ini halaman hub wilayah?
 */
function kh_is_wilayah_hub() {
	return is_page( 'wilayah' );
}

/**
 * Template hub wilayah. Prioritas 22 = sesudah handler 404/front page di inc/ui.php (20)
 * dan sesudah /analitik/ (21).
 */
add_filter( 'template_include', function ( $template ) {
	if ( ! kh_is_wilayah_hub() || is_admin() ) {
		return $template;
	}
	$file = KH_CORE_DIR . 'templates/wilayah-hub.php';
	return file_exists( $file ) ? $file : $template;
}, 22 );

/*
 * Judul dokumen hub wilayah ditangani `kh_seo_context()` di inc/seo.php.
 * Jangan tambahkan filter `document_title_parts` di sini: filter di seo.php
 * (prioritas 20) jalan belakangan dan akan menambah nama situs lagi sehingga
 * judul jadi dobel ("… — Daftar & Peta — Peta Kendaraan Hilang").
 */

/* ------------------------------------------------------------------ */
/* 3. Modul tautan di halaman detail                                   */
/* ------------------------------------------------------------------ */

/**
 * Breadcrumb yang terlihat (JSON-LD BreadcrumbList sudah ada di inc/schema.php).
 *
 * @param int $id Post ID laporan yang sedang dibuka.
 */
function kh_crumbs_block( $id ) {
	$wilayah = wp_get_post_terms( $id, 'kendaraan_wilayah' );
	$term    = ( ! is_wp_error( $wilayah ) && $wilayah ) ? $wilayah[0] : null;

	$kecamatan = wp_get_post_terms( $id, 'kendaraan_kecamatan' );
	$kec       = ( ! is_wp_error( $kecamatan ) && $kecamatan ) ? $kecamatan[0] : null;

	$plat = get_post_meta( $id, 'kh_plat_masked', true );
	?>
	<nav class="kh-crumbs" aria-label="Breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Peta</a>
		<span aria-hidden="true">&rsaquo;</span>
		<?php if ( $term ) : ?>
			<a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
		<?php else : ?>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'kendaraan' ) ); ?>">Semua laporan</a>
			<span aria-hidden="true">&rsaquo;</span>
		<?php endif; ?>
		<?php if ( $kec ) : ?>
			<a href="<?php echo esc_url( get_term_link( $kec ) ); ?>"><?php echo esc_html( $kec->name ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
		<?php endif; ?>
		<span class="kh-crumbs-now"><?php echo esc_html( $plat ? $plat : get_the_title( $id ) ); ?></span>
	</nav>
	<?php
}

/**
 * Cetak daftar laporan lain (wilayah sama & model sama) di halaman detail.
 *
 * @param int $id Post ID laporan yang sedang dibuka.
 */
function kh_related_block( $id ) {
	$wilayah = wp_get_post_terms( $id, 'kendaraan_wilayah' );
	$term    = ( ! is_wp_error( $wilayah ) && $wilayah ) ? $wilayah[0] : null;

	$merk  = get_post_meta( $id, 'kh_merk', true );
	$model = get_post_meta( $id, 'kh_model', true );

	/* Laporan lain di kecamatan yang sama — paling relevan secara lokal. */
	$kecamatan = wp_get_post_terms( $id, 'kendaraan_kecamatan' );
	$kec       = ( ! is_wp_error( $kecamatan ) && $kecamatan ) ? $kecamatan[0] : null;
	if ( $kec ) {
		$dekat = kh_hub_query(
			array(
				'posts_per_page' => 6,
				'post__not_in'   => array( $id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => 'kendaraan_kecamatan',
						'field'    => 'term_id',
						'terms'    => $kec->term_id,
					),
				),
			)
		);
		if ( $dekat->have_posts() ) {
			echo '<section class="kh-rel"><h2>Laporan lain di Kecamatan ' . esc_html( $kec->name ) . '</h2><div class="kh-cards kh-cards-mini">';
			while ( $dekat->have_posts() ) {
				$dekat->the_post();
				kh_rel_card( get_the_ID() );
			}
			wp_reset_postdata();
			printf(
				'</div><p class="kh-hub-more"><a href="%s">Semua laporan di Kecamatan %s &rsaquo;</a></p></section>',
				esc_url( get_term_link( $kec ) ),
				esc_html( $kec->name )
			);
		}
	}

	/* Laporan lain di wilayah yang sama. */
	if ( $term ) {
		$lain = kh_hub_query(
			array(
				'posts_per_page' => 6,
				'post__not_in'   => array( $id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => 'kendaraan_wilayah',
						'field'    => 'slug',
						'terms'    => $term->slug,
					),
				),
			)
		);
		if ( $lain->have_posts() ) {
			echo '<section class="kh-rel"><h2>Laporan lain di ' . esc_html( $term->name ) . '</h2><div class="kh-cards kh-cards-mini">';
			while ( $lain->have_posts() ) {
				$lain->the_post();
				kh_rel_card( get_the_ID() );
			}
			wp_reset_postdata();
			echo '</div>';
			printf(
				'<p class="kh-hub-more"><a href="%s">Semua laporan di %s &rsaquo;</a></p>',
				esc_url( get_term_link( $term ) ),
				esc_html( $term->name )
			);
			echo '</section>';
		}
	}

	/* Laporan lain dengan model yang sama (kata kunci pencarian paling spesifik). */
	if ( $model ) {
		$sama = kh_hub_query(
			array(
				'posts_per_page' => 4,
				'post__not_in'   => array( $id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => 'kh_tgl',
						'value'   => kh_hub_batas_arsip(),
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'   => 'kh_model',
						'value' => $model,
					),
				),
			)
		);
		if ( $sama->have_posts() ) {
			echo '<section class="kh-rel"><h2>Laporan lain ' . esc_html( trim( $merk . ' ' . $model ) ) . '</h2><div class="kh-cards kh-cards-mini">';
			while ( $sama->have_posts() ) {
				$sama->the_post();
				kh_rel_card( get_the_ID() );
			}
			wp_reset_postdata();
			echo '</div></section>';
		}
	}
}

/**
 * Satu kartu ringkas untuk modul "laporan lain".
 *
 * @param int $id Post ID.
 */
function kh_rel_card( $id ) {
	$plat  = get_post_meta( $id, 'kh_plat_masked', true );
	$merk  = get_post_meta( $id, 'kh_merk', true );
	$model = get_post_meta( $id, 'kh_model', true );
	$tahun = get_post_meta( $id, 'kh_tahun', true );
	$lok   = get_post_meta( $id, 'kh_lokasi', true );
	$tgl   = kh_hub_tanggal( $id );
	$verif = kh_verifikasi_of( $id );
	$label = kh_verifikasi_levels();

	printf(
		'<a class="kh-card" href="%s"><strong class="kh-card-plat">%s</strong><span class="kh-card-veh">%s</span><span class="kh-card-loc">%s</span><span class="kh-card-date">%s</span><span class="kh-card-tag %s">%s</span></a>',
		esc_url( get_permalink( $id ) ),
		esc_html( $plat ),
		esc_html( trim( $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) ) ),
		esc_html( $lok ),
		esc_html( $tgl ),
		esc_attr( $verif ),
		esc_html( strtoupper( $label[ $verif ] ) )
	);
}
