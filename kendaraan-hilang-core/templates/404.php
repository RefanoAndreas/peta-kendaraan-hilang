<?php
/**
 * Template 404 bertema gelap (menggantikan 404 bawaan tema aktif).
 *
 * Dipasang lewat filter `template_include` di inc/ui.php, jadi tema WordPress
 * yang sedang aktif tidak dipakai sama sekali — konsisten dengan halaman detail
 * dan halaman legal. Status HTTP tetap 404 (dikirim WordPress sendiri).
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kh_terbaru = get_posts(
	array(
		'post_type'      => 'kendaraan',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'no_found_rows'  => true,
	)
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#04080d">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'kh-page-body kh-404-body' ); ?>>

<header class="kh-top">
	<a class="kh-home" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Peta</a>
	<span class="kh-top-title">Halaman tidak ditemukan</span>
</header>

<main class="kh-wrap kh-404">
	<span class="kh-badge">ERROR 404</span>

	<p class="kh-404-code" aria-hidden="true">404</p>

	<h1>Halaman ini tidak ada</h1>

	<p class="kh-404-lead">
		Tautannya mungkin salah ketik, atau laporannya sudah dihapus atau dipindahkan.
		Kalau kamu datang dari tautan WhatsApp, minta pengirimnya mengirim ulang tautan terbaru —
		alamat laporan bisa berubah setelah datanya dikoreksi.
	</p>

	<div class="kh-404-aksi">
		<a class="kh-404-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Buka peta</a>
		<a class="kh-404-btn ghost" href="<?php echo esc_url( get_post_type_archive_link( 'kendaraan' ) ); ?>">Semua laporan</a>
		<a class="kh-404-btn ghost" href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">Laporkan kendaraan</a>
	</div>

	<?php if ( $kh_terbaru ) : ?>
		<h2 class="kh-404-sub">Laporan terbaru</h2>
		<ul class="kh-404-list">
			<?php
			foreach ( $kh_terbaru as $kh_p ) {
				$kh_plat   = get_post_meta( $kh_p->ID, 'kh_plat', true );
				$kh_lokasi = get_post_meta( $kh_p->ID, 'kh_lokasi', true );
				?>
				<li>
					<a href="<?php echo esc_url( get_permalink( $kh_p ) ); ?>">
						<span class="plat"><?php echo esc_html( $kh_plat ? $kh_plat : get_the_title( $kh_p ) ); ?></span>
						<span class="lok"><?php echo esc_html( $kh_lokasi ); ?></span>
					</a>
				</li>
			<?php } ?>
		</ul>
	<?php endif; ?>

	<p class="kh-404-kaki">
		Butuh bantuan atau menemukan tautan yang rusak?
		<a href="<?php echo esc_url( home_url( '/kontak/' ) ); ?>">Hubungi kami</a>.
	</p>
</main>

<?php wp_footer(); ?>
</body>
</html>
