<?php
/**
 * Template halaman panduan publik: indeks `/panduan/` dan 5 halaman pilar.
 *
 * Konten berasal dari inc/panduan-publik-data.php (dalam kode, bukan wp-admin) dan
 * blok `data` mengambil angka live dari database supaya tidak pernah basi.
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kh_slug = kh_pp_slug_aktif();
$kh_item = ( $kh_slug && 'panduan' !== $kh_slug ) ? kh_panduan_item( $kh_slug ) : null;
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
	<span class="kh-top-title">Panduan</span>
</header>

<main class="kh-wrap">
	<nav class="kh-crumbs" aria-label="Breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Peta</a>
		<span aria-hidden="true">&rsaquo;</span>
		<?php if ( $kh_item ) : ?>
			<a href="<?php echo esc_url( home_url( '/panduan/' ) ); ?>">Panduan</a>
			<span aria-hidden="true">&rsaquo;</span>
			<span class="kh-crumbs-now"><?php echo esc_html( $kh_item['judul'] ); ?></span>
		<?php else : ?>
			<span class="kh-crumbs-now">Panduan</span>
		<?php endif; ?>
	</nav>

	<?php if ( ! $kh_item ) : ?>

		<span class="kh-badge">PANDUAN</span>
		<h1>Panduan Kendaraan Hilang</h1>
		<p class="kh-legal-lead">
			Lima panduan praktis untuk kejadian yang paling sering dialami warga Jawa Timur: melapor ke polisi,
			memeriksa nomor polisi, membaca data kehilangan di Surabaya, mengenali kawasan rawan, dan mengurus
			kendaraan yang ditemukan lagi. Semua ditulis dengan rujukan ke prosedur resmi Polri dan Samsat.
		</p>

		<div class="kh-pp-grid">
			<?php foreach ( kh_panduan_slugs() as $kh_s ) : ?>
				<?php $kh_i = kh_panduan_item( $kh_s ); ?>
				<?php if ( ! $kh_i ) : continue; endif; ?>
				<a class="kh-pp-card" href="<?php echo esc_url( home_url( '/panduan/' . $kh_s . '/' ) ); ?>">
					<strong><?php echo esc_html( $kh_i['judul'] ); ?></strong>
					<span><?php echo esc_html( $kh_i['seo_desc'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<h2 class="kh-sub-h">Data yang dipakai</h2>
		<p>
			Angka di panduan diambil langsung dari arsip laporan situs ini, yang bersumber dari pemberitaan
			(antara lain Suara Surabaya) dan laporan warga. Untuk melihat seluruh data:
			<a href="<?php echo esc_url( home_url( '/wilayah/' ) ); ?>">daftar wilayah</a> ·
			<a href="<?php echo esc_url( home_url( '/analitik/' ) ); ?>">statistik</a> ·
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">peta</a>.
		</p>

	<?php else : ?>

		<span class="kh-badge">PANDUAN</span>
		<h1><?php echo esc_html( $kh_item['judul'] ); ?></h1>
		<p class="kh-pp-meta">
			Ditinjau terakhir: <?php echo esc_html( $kh_item['tinjau'] ); ?> &middot;
			<span>Situs ini bukan kanal resmi kepolisian.</span>
		</p>

		<?php
		foreach ( $kh_item['blok'] as $kh_b ) :
			switch ( $kh_b['t'] ) :
				case 'h2':
					echo '<h2>' . esc_html( $kh_b['x'] ) . '</h2>';
					break;
				case 'h3':
					echo '<h3>' . esc_html( $kh_b['x'] ) . '</h3>';
					break;
				case 'p':
					echo '<p>' . kh_pp_fmt( $kh_b['x'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sudah di-escape di kh_pp_fmt()
					break;
				case 'ul':
					echo '<ul class="kh-pp-list">';
					foreach ( $kh_b['x'] as $kh_li ) {
						echo '<li>' . kh_pp_fmt( $kh_li ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '</ul>';
					break;
				case 'ol':
					echo '<ol class="kh-pp-list">';
					foreach ( $kh_b['x'] as $kh_li ) {
						echo '<li>' . kh_pp_fmt( $kh_li ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '</ol>';
					break;
				case 'data':
					kh_pp_data_block( $kh_b['x'] );
					break;
				case 'faq':
					echo '<div class="kh-pp-faq">';
					foreach ( $kh_b['x'] as $kh_pair ) {
						echo '<details class="kh-pp-faq-item"><summary>' . esc_html( $kh_pair[0] ) . '</summary><p>' . esc_html( $kh_pair[1] ) . '</p></details>';
					}
					echo '</div>';
					break;
				case 'links':
					echo '<ul class="kh-pp-links">';
					foreach ( $kh_b['x'] as $kh_pair ) {
						printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( $kh_pair[1] ) ), esc_html( $kh_pair[0] ) );
					}
					echo '</ul>';
					break;
			endswitch;
		endforeach;
		?>

		<div class="kh-legal-cta" style="margin:30px 0 0">
			<a class="kh-legal-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Buka peta kendaraan hilang</a>
			<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">Laporkan kendaraan hilang</a>
		</div>

	<?php endif; ?>
</main>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; data dikutip dari media dan laporan warga, disertai tautan sumber.
	<?php kh_legal_nav(); ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
