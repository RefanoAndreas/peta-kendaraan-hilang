<?php
/**
 * Template detail laporan (tema gelap).
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
	<span class="kh-top-title">Peta Kendaraan Hilang &middot; Jawa Timur</span>
</header>

<?php
while ( have_posts() ) :
	the_post();
	$id      = get_the_ID();
	$plat    = get_post_meta( $id, 'kh_plat_masked', true );
	$merk    = get_post_meta( $id, 'kh_merk', true );
	$model   = get_post_meta( $id, 'kh_model', true );
	$tahun   = get_post_meta( $id, 'kh_tahun', true );
	$warna   = get_post_meta( $id, 'kh_warna', true );
	$ciri    = get_post_meta( $id, 'kh_ciri', true );
	$tanggal = get_post_meta( $id, 'kh_tanggal', true );
	$jam     = get_post_meta( $id, 'kh_jam', true );
	$lokasi  = get_post_meta( $id, 'kh_lokasi', true );
	$lat     = get_post_meta( $id, 'kh_lat', true );
	$lng     = get_post_meta( $id, 'kh_lng', true );
	$polsek  = get_post_meta( $id, 'kh_polsek', true );
	$snama   = get_post_meta( $id, 'kh_sumber_nama', true );
	$surl    = get_post_meta( $id, 'kh_sumber_url', true );

	$status  = wp_get_post_terms( $id, 'kendaraan_status', array( 'fields' => 'names' ) );
	$status  = $status ? $status[0] : 'Hilang';
	$is_got  = ( 'ditemukan' === strtolower( $status ) );
	$badge   = $is_got ? 'is-got' : ( $polsek ? 'is-rep' : '' );

	$rows = array(
		'Kendaraan'   => trim( $merk . ' ' . $model ),
		'Tahun'       => $tahun,
		'Warna'       => $warna,
		'Ciri khusus' => $ciri,
		'Waktu'       => trim( $tanggal . ( $jam ? ' · pukul ' . $jam . ' WIB' : '' ) ),
		'Lokasi'      => $lokasi,
		'Status lapor' => $is_got ? 'Sudah ditemukan' : ( $polsek ? 'Sudah lapor ke ' . $polsek : 'Belum ada laporan polisi' ),
	);
	?>
	<main class="kh-wrap">
		<span class="kh-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( strtoupper( $status ) ); ?></span>
		<h1><?php the_title(); ?></h1>

		<?php if ( $plat ) : ?>
			<div class="kh-plat"><?php echo esc_html( $plat ); ?></div>
		<?php endif; ?>

		<ul class="kh-rows">
			<?php foreach ( $rows as $label => $value ) : ?>
				<?php if ( '' === trim( (string) $value ) ) { continue; } ?>
				<li><span class="k"><?php echo esc_html( $label ); ?></span><span><?php echo esc_html( $value ); ?></span></li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $lat && $lng ) : ?>
			<div id="kh-mini"></div>
			<script>
			document.addEventListener('DOMContentLoaded', function () {
				if (typeof maplibregl === 'undefined' || !window.KH_MAP_STYLE) { return; }
				try {
					var m = new maplibregl.Map({
						container: 'kh-mini',
						style: window.KH_MAP_STYLE,
						center: [<?php echo (float) $lng; ?>, <?php echo (float) $lat; ?>],
						zoom: 15,
						interactive: false,
						attributionControl: false
					});
					var el = document.createElement('div');
					el.className = 'mk-hex <?php echo $is_got ? 'mk-got' : ( $polsek ? 'mk-rep' : 'mk-open' ); ?>';
					var P = '0,-15 13,-7.5 13,7.5 0,15 -13,7.5 -13,-7.5';
					el.innerHTML = '<svg viewBox="-20 -20 40 40" width="34" height="34">' +
						'<polygon points="' + P + '" fill="currentColor" fill-opacity=".26" stroke="currentColor" stroke-width="2"/>' +
						'<polygon points="0,-7 6,-3.5 6,3.5 0,7 -6,3.5 -6,-3.5" fill="none" stroke="currentColor" stroke-width="1.2"/>' +
						'<circle r="2.4" fill="#fff"/></svg>';
					el.style.color = <?php echo $is_got ? "'#35f0c8'" : ( $polsek ? "'#2fb4ff'" : "'#ff5a3c'" ); ?>;
					el.style.filter = 'drop-shadow(0 0 7px currentColor)';
					new maplibregl.Marker({ element: el, anchor: 'center' })
						.setLngLat([<?php echo (float) $lng; ?>, <?php echo (float) $lat; ?>])
						.addTo(m);
				} catch (e) { /* peta opsional */ }
			});
			</script>
		<?php endif; ?>

		<div class="kh-alert">
			<span class="ic">&#9888;</span>
			<div>
				<strong>Jangan pernah mentransfer uang.</strong>
				Jika ada yang mengaku menemukan kendaraan ini dan meminta biaya di muka, itu modus penipuan.
				Verifikasi hanya lewat kepolisian (<?php echo esc_html( $polsek ? $polsek : 'Polsek/Polres setempat' ); ?>).
			</div>
		</div>

		<?php if ( get_the_content() ) : ?>
			<div class="kh-content"><?php the_content(); ?></div>
		<?php endif; ?>

		<div class="kh-source">
			<strong>Sumber:</strong>
			<?php if ( $surl ) : ?>
				<a href="<?php echo esc_url( $surl ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $snama ? $snama : $surl ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $snama ? $snama : 'Laporan warga' ); ?>
			<?php endif; ?>
			<br><span style="opacity:.75">Nomor polisi ditampilkan lengkap agar warga mudah mengenali kendaraan dan bisa melapor.</span>
		</div>
	</main>
	<?php
endwhile;
?>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; data dikutip dari media dan laporan warga, disertai tautan sumber.
</footer>

<?php wp_footer(); ?>
</body>
</html>
