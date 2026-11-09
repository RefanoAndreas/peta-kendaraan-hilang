<?php
/**
 * Template arsip laporan kendaraan (tema gelap).
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;
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
	<span class="kh-top-title">Semua laporan kendaraan hilang</span>
</header>

<main class="kh-wrap wide">
	<span class="kh-badge">ARSIP LAPORAN</span>
	<h1>Semua Laporan Kendaraan Hilang</h1>
	<p style="color:#6f8f98;margin:0 0 4px">
		<?php echo (int) $wp_query->found_posts; ?> laporan tersimpan &middot; terbaru di atas
	</p>

	<form class="kh-toolbar" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'kendaraan' ) ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Cari plat, model, atau lokasi&hellip;">
		<button type="submit" class="kh-home" style="cursor:pointer">Cari</button>
	</form>

	<?php if ( have_posts() ) : ?>
		<div class="kh-grid-cards">
			<?php
			while ( have_posts() ) :
				the_post();
				$id     = get_the_ID();
				$plat   = get_post_meta( $id, 'kh_plat_masked', true );
				$merk   = get_post_meta( $id, 'kh_merk', true );
				$model  = get_post_meta( $id, 'kh_model', true );
				$tahun  = get_post_meta( $id, 'kh_tahun', true );
				$warna  = get_post_meta( $id, 'kh_warna', true );
				$lokasi = get_post_meta( $id, 'kh_lokasi', true );
				$tgl    = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
				$jam    = get_post_meta( $id, 'kh_jam', true );
				$polsek = get_post_meta( $id, 'kh_polsek', true );

				$st = wp_get_post_terms( $id, 'kendaraan_status', array( 'fields' => 'names' ) );
				$st = $st ? strtolower( $st[0] ) : 'hilang';
				$cls = 'ditemukan' === $st ? 'is-got' : ( $polsek ? 'is-rep' : '' );
				?>
				<a class="kh-card-link <?php echo esc_attr( $cls ); ?>" href="<?php the_permalink(); ?>">
					<div class="p"><?php echo esc_html( $plat ); ?></div>
					<div class="m"><?php echo esc_html( trim( $merk . ' ' . $model . ( $tahun ? ' (' . $tahun . ')' : '' ) ) ); ?>
						<?php echo $warna ? '&middot; ' . esc_html( $warna ) : ''; ?></div>
					<div class="s"><?php echo esc_html( $lokasi ); ?></div>
					<div class="d"><?php echo esc_html( trim( $tgl . ( $jam ? ' · ' . $jam . ' WIB' : '' ) ) ); ?></div>
				</a>
			<?php endwhile; ?>
		</div>

		<div class="kh-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'total'     => max( 1, (int) $wp_query->max_num_pages ),
						'current'   => max( 1, (int) get_query_var( 'paged' ) ),
						'prev_text' => '&lsaquo; Sebelumnya',
						'next_text' => 'Berikutnya &rsaquo;',
						'type'      => 'plain',
					)
				)
			);
			?>
		</div>
	<?php else : ?>
		<div class="empty" style="padding:40px 0;color:#6f8f98">Belum ada laporan yang cocok.</div>
	<?php endif; ?>
</main>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; nomor polisi ditampilkan lengkap sesuai sumber aslinya.
</footer>

<?php wp_footer(); ?>
</body>
</html>
