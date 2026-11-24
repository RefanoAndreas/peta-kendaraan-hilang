<?php
/**
 * Hub /wilayah/ — indeks semua wilayah + laporan terbaru (tautan internal).
 *
 * Dipasang lewat `template_include` di inc/hub.php (prioritas 22).
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kh_terms = kh_hub_wilayah_terms();
$kh_total = 0;
foreach ( $kh_terms as $kh_t ) {
	$kh_total += (int) $kh_t->count;
}

$kh_q = kh_hub_query( array( 'posts_per_page' => 12 ) );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#04080d">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'kh-page-body' ); ?>>

<header class="kh-top">
	<a class="kh-home" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Peta</a>
	<span class="kh-top-title">Wilayah &middot; Jawa Timur</span>
</header>

<main class="kh-wrap">
	<span class="kh-badge">WILAYAH</span>
	<h1>Kendaraan Hilang per Wilayah di Jawa Timur</h1>

	<p class="kh-legal-lead">
		<?php echo (int) $kh_total; ?> laporan kehilangan motor dan mobil yang tersebar di
		<?php echo count( $kh_terms ); ?> kabupaten/kota. Pilih wilayah untuk melihat daftar laporan lengkap
		dengan nomor polisi, lokasi terakhir, dan status verifikasinya &mdash; atau buka
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">peta interaktif</a> untuk melihat sebarannya.
	</p>

	<div class="kh-wilayah-grid">
		<?php foreach ( $kh_terms as $kh_t ) : ?>
			<a class="kh-wilayah-card" href="<?php echo esc_url( get_term_link( $kh_t ) ); ?>">
				<strong><?php echo esc_html( $kh_t->name ); ?></strong>
				<span><?php echo (int) $kh_t->count; ?> laporan</span>
			</a>
		<?php endforeach; ?>
	</div>

	<h2 class="kh-sub-h">Kecamatan di Surabaya</h2>
	<?php
	$kh_kec = get_terms(
		array(
			'taxonomy'   => 'kendaraan_kecamatan',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	?>
	<?php if ( ! is_wp_error( $kh_kec ) && $kh_kec ) : ?>
		<div class="kh-wilayah-links">
			<?php foreach ( $kh_kec as $kh_t2 ) : ?>
				<a href="<?php echo esc_url( get_term_link( $kh_t2 ) ); ?>"><?php echo esc_html( $kh_t2->name ); ?> <span><?php echo (int) $kh_t2->count; ?></span></a>
			<?php endforeach; ?>
		</div>
		<p class="kh-hub-more">Halaman kecamatan membantu warga mencari laporan berdasarkan kawasan yang mereka kenal, misalnya &ldquo;motor hilang Rungkut&rdquo; atau &ldquo;motor hilang Kenjeran&rdquo;.</p>
	<?php else : ?>
		<p class="kh-legal-lead">Halaman kecamatan sedang disiapkan.</p>
	<?php endif; ?>

	<h2 class="kh-sub-h">Laporan terbaru</h2>
	<?php if ( $kh_q->have_posts() ) : ?>
		<div class="kh-cards">
			<?php
			while ( $kh_q->have_posts() ) :
				$kh_q->the_post();
				kh_rel_card( get_the_ID() );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<p class="kh-hub-more">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'kendaraan' ) ); ?>">Lihat semua laporan &rsaquo;</a>
		</p>
	<?php else : ?>
		<p class="kh-legal-lead">Belum ada laporan aktif.</p>
	<?php endif; ?>

	<h2 class="kh-sub-h">Tentang data ini</h2>
	<p>
		Laporan dikutip dari pemberitaan Radio Suara Surabaya FM 100 dan laporan warga yang masuk lewat
		<a href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">formulir lapor</a>. Setiap kabupaten/kota
		punya halaman sendiri supaya warga bisa memeriksa apakah kendaraan yang mereka lihat sudah pernah
		dilaporkan hilang. Data yang berumur lebih dari <?php echo (int) KH_ARSIP_HARI; ?> hari masuk arsip:
		halamannya tetap bisa dibuka dan dibagikan, tetapi tidak lagi tampil di peta utama.
	</p>
	<p class="kh-hub-note">
		Situs ini bukan kanal resmi kepolisian. Untuk laporan resmi (asuransi, blokir STNK), datang ke
		Polsek/Polres setempat dengan membawa STNK dan BPKB.
	</p>

	<div class="kh-legal-cta" style="margin:26px 0 0">
		<a class="kh-legal-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Buka peta</a>
		<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">Laporkan kendaraan hilang</a>
	</div>
</main>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; data dikutip dari media dan laporan warga, disertai tautan sumber.
	<?php kh_legal_nav(); ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
