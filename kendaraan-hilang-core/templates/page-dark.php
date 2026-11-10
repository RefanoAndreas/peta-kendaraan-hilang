<?php
/**
 * Template halaman statis bertema gelap (Kebijakan Privasi, Tentang, Kontak, Disclaimer).
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
	<span class="kh-top-title">Informasi &amp; Ketentuan</span>
</header>

<?php
while ( have_posts() ) :
	the_post();
	?>
	<main class="kh-wrap kh-legal">
		<span class="kh-badge">INFORMASI</span>
		<h1><?php the_title(); ?></h1>

		<?php if ( has_excerpt() ) : ?>
			<p class="kh-legal-lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php endif; ?>

		<div class="kh-content kh-legal-body">
			<?php the_content(); ?>
		</div>

		<?php if ( 'kontak' === get_post_field( 'post_name', get_the_ID() ) ) : ?>
			<div class="kh-legal-cta">
				<a class="kh-legal-btn" href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">Laporkan kendaraan hilang</a>
				<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Lihat peta</a>
			</div>
		<?php endif; ?>
	</main>
	<?php
endwhile;
?>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; data dikutip dari media dan laporan warga, disertai tautan sumber.
	<?php kh_legal_nav(); ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
