<?php
/**
 * Landing page per wilayah: /wilayah/<slug>/ — pintu masuk dari mesin pencari.
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$term = get_queried_object();
$slug = $term instanceof WP_Term ? $term->slug : '';
$nama = $term instanceof WP_Term ? $term->name : 'Jawa Timur';

$laporan = new WP_Query(
	array(
		'post_type'      => 'kendaraan',
		'post_status'    => 'publish',
		'posts_per_page' => 30,
		'tax_query'      => array(
			array(
				'taxonomy' => 'kendaraan_wilayah',
				'field'    => 'slug',
				'terms'    => $slug,
			),
		),
	)
);

$other = get_terms(
	array(
		'taxonomy'   => 'kendaraan_wilayah',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'exclude'    => array( $term instanceof WP_Term ? $term->term_id : 0 ),
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
<body <?php body_class( 'kh-page-body' ); ?>>

<header class="kh-top">
	<a class="kh-home" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Peta</a>
	<span class="kh-top-title">Wilayah &middot; <?php echo esc_html( $nama ); ?></span>
</header>

<main class="kh-wrap">
	<span class="kh-badge">WILAYAH</span>
	<h1>Kendaraan Hilang di <?php echo esc_html( $nama ); ?></h1>

	<p class="kh-legal-lead">
		<?php echo (int) $laporan->found_posts; ?> laporan kehilangan motor dan mobil di <?php echo esc_html( $nama ); ?>.
		Setiap laporan mencantumkan nomor polisi, lokasi terakhir, dan status verifikasinya.
	</p>

	<div class="kh-legal-cta" style="margin:0 0 26px">
		<a class="kh-legal-btn" href="<?php echo esc_url( add_query_arg( 'wil', $slug, home_url( '/' ) ) ); ?>">Lihat di peta</a>
		<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">Laporkan kendaraan hilang</a>
	</div>

	<?php if ( $laporan->have_posts() ) : ?>
		<div class="kh-cards">
			<?php
			while ( $laporan->have_posts() ) :
				$laporan->the_post();
				$id      = get_the_ID();
				$plat    = get_post_meta( $id, 'kh_plat_masked', true );
				$merk    = get_post_meta( $id, 'kh_merk', true );
				$model   = get_post_meta( $id, 'kh_model', true );
				$tahun   = get_post_meta( $id, 'kh_tahun', true );
				$warna   = get_post_meta( $id, 'kh_warna', true );
				$lokasi  = get_post_meta( $id, 'kh_lokasi', true );
				$tanggal = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
				$verif   = kh_verifikasi_of( $id );
				$status  = wp_get_post_terms( $id, 'kendaraan_status', array( 'fields' => 'names' ) );
				$is_got  = $status && 'ditemukan' === strtolower( $status[0] );
				$labels  = kh_verifikasi_levels();
				$tag_cls = $is_got ? 'got' : $verif;
				$tag_txt = $is_got ? 'DITEMUKAN' : strtoupper( $labels[ $verif ] );
				?>
				<a class="kh-card" href="<?php the_permalink(); ?>">
					<span class="kh-card-tag <?php echo esc_attr( $tag_cls ); ?>"><?php echo esc_html( $tag_txt ); ?></span>
					<strong class="kh-card-plat"><?php echo esc_html( $plat ); ?></strong>
					<span class="kh-card-veh"><?php echo esc_html( trim( $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) ) ); ?><?php echo $warna ? ' · ' . esc_html( $warna ) : ''; ?></span>
					<span class="kh-card-loc"><?php echo esc_html( $lokasi ); ?></span>
					<span class="kh-card-date"><?php echo esc_html( $tanggal ? date_i18n( 'j F Y', strtotime( $tanggal ) ) : '' ); ?></span>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	<?php else : ?>
		<p class="kh-legal-lead">Belum ada laporan aktif di wilayah ini. Kalau kamu kehilangan kendaraan di <?php echo esc_html( $nama ); ?>, kamu yang pertama bisa melaporkannya.</p>
	<?php endif; ?>

	<h2 class="kh-sub-h">Wilayah lain</h2>
	<div class="kh-wilayah-links">
		<?php
		if ( ! is_wp_error( $other ) ) {
			foreach ( $other as $t ) {
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

	<?php
	/*
	 * Halaman kecamatan hanya ada untuk Surabaya (31 kecamatan). Menautkannya dari
	 * sini memberi halaman wilayah -> kecamatan -> laporan tanpa lompatan.
	 */
	$kh_kec = ( 'surabaya' === $slug ) ? get_terms(
		array(
			'taxonomy'   => 'kendaraan_kecamatan',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	) : array();
	if ( ! is_wp_error( $kh_kec ) && $kh_kec ) :
		?>
		<h2 class="kh-sub-h">Kecamatan di Surabaya</h2>
		<div class="kh-wilayah-links">
			<?php foreach ( $kh_kec as $t ) : ?>
				<a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?> <span><?php echo (int) $t->count; ?></span></a>
			<?php endforeach; ?>
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
