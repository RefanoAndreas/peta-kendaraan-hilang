<?php
/**
 * Halaman kecamatan: /kecamatan/<slug>/ — long-tail "motor hilang <kecamatan>".
 *
 * Dipasang lewat `template_include` di inc/kecamatan.php.
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$term = get_queried_object();
if ( ! $term instanceof WP_Term ) {
	return;
}

$data    = kh_kecamatan_data();
$region  = isset( $data[ $term->name ][0] ) ? $data[ $term->name ][0] : '';
$stat    = kh_kec_statistik( $term );
$wilayah = get_term_by( 'slug', 'surabaya', 'kendaraan_wilayah' );

$laporan = new WP_Query(
	array(
		'post_type'      => 'kendaraan',
		'post_status'    => 'publish',
		'posts_per_page' => 30,
		'no_found_rows'  => true,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'kendaraan_kecamatan',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			),
		),
	)
);

$lain = get_terms(
	array(
		'taxonomy'   => 'kendaraan_kecamatan',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'exclude'    => array( $term->term_id ),
	)
);

$jam_rawan = '';
if ( $stat['jam'] ) {
	$jam_keys  = array_keys( $stat['jam'] );
	$jam_rawan = sprintf( 'pukul %s.00–%s.59', $jam_keys[0], $jam_keys[0] );
}
$model_top = $stat['model'] ? array_key_first( $stat['model'] ) : '';
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
	<span class="kh-top-title">Kecamatan &middot; <?php echo esc_html( $term->name ); ?></span>
</header>

<main class="kh-wrap">
	<nav class="kh-crumbs" aria-label="Breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Peta</a>
		<span aria-hidden="true">&rsaquo;</span>
		<a href="<?php echo esc_url( home_url( '/wilayah/' ) ); ?>">Wilayah</a>
		<span aria-hidden="true">&rsaquo;</span>
		<?php if ( $wilayah && ! is_wp_error( $wilayah ) ) : ?>
			<a href="<?php echo esc_url( get_term_link( $wilayah ) ); ?>">Surabaya</a>
			<span aria-hidden="true">&rsaquo;</span>
		<?php endif; ?>
		<span class="kh-crumbs-now"><?php echo esc_html( $term->name ); ?></span>
	</nav>

	<span class="kh-badge">KECAMATAN</span>
	<h1>Motor &amp; Mobil Hilang di Kecamatan <?php echo esc_html( $term->name ) ?>, Surabaya</h1>

	<p class="kh-legal-lead">
		<?php echo (int) $stat['jumlah']; ?> laporan kehilangan kendaraan di Kecamatan
		<?php echo esc_html( $term->name ); ?><?php echo $region ? ' (Surabaya ' . esc_html( $region ) . ')' : ''; ?>.
		<?php if ( $jam_rawan ) : ?>
			Jam kejadian yang paling sering muncul: <strong><?php echo esc_html( $jam_rawan ); ?></strong>.
		<?php endif; ?>
		<?php if ( $model_top ) : ?>
			Model yang paling sering hilang: <strong><?php echo esc_html( $model_top ); ?></strong>.
		<?php endif; ?>
		Setiap laporan mencantumkan nomor polisi, lokasi terakhir, dan status verifikasinya.
	</p>

	<div class="kh-legal-cta" style="margin:0 0 26px">
		<a class="kh-legal-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Lihat di peta</a>
		<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">Laporkan kendaraan hilang</a>
	</div>

	<?php if ( $laporan->have_posts() ) : ?>
		<div class="kh-cards">
			<?php
			while ( $laporan->have_posts() ) :
				$laporan->the_post();
				kh_rel_card( get_the_ID() );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	<?php else : ?>
		<p class="kh-legal-lead">
			Belum ada laporan aktif di kecamatan ini. Kalau kamu kehilangan kendaraan di
			Kecamatan <?php echo esc_html( $term->name ); ?>, kamu yang pertama bisa melaporkannya.
		</p>
	<?php endif; ?>

	<h2 class="kh-sub-h">Kecamatan lain di Surabaya</h2>
	<div class="kh-wilayah-links">
		<?php
		if ( ! is_wp_error( $lain ) ) {
			foreach ( $lain as $t ) {
				printf(
					'<a href="%s">%s <span>%d</span></a>',
					esc_url( get_term_link( $t ) ),
					esc_html( $t->name ),
					(int) $t->count
				);
			}
		}
		?>
	</div>

	<h2 class="kh-sub-h">Bagaimana data ini disusun</h2>
	<p>
		Laporan dikutip dari pemberitaan dan laporan warga, lalu dipetakan ke kecamatan berdasarkan
		<strong>koordinat lokasi kejadian</strong> — bukan dari teks alamat, karena laporan warga hampir
		selalu menyebut nama jalan atau patokan, bukan nama kecamatan. Titik lokasinya perkiraan: halaman
		detail setiap laporan menampilkan lingkaran radius sekitar 1 km dari titik tersebut, jadi sebuah
		laporan bisa saja sebenarnya terjadi di kecamatan bersebelahan.
	</p>
	<p class="kh-hub-note">
		Data berumur lebih dari <?php echo (int) KH_ARSIP_HARI; ?> hari masuk arsip dan tidak lagi tampil di
		peta utama, tetapi halamannya tetap bisa dibuka. Situs ini bukan kanal resmi kepolisian &mdash;
		untuk laporan resmi datang ke Polsek/Polres setempat dengan membawa STNK dan BPKB.
	</p>
</main>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; data dikutip dari media dan laporan warga, disertai tautan sumber.
	<?php kh_legal_nav(); ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
