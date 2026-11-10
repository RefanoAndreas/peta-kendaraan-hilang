<?php
/**
 * Halaman konfirmasi status laporan (dari tautan email cek ulang).
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid   = isset( $_GET['k'] ) ? absint( $_GET['k'] ) : 0;
$tok   = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
$hasil = isset( $_GET['h'] ) ? sanitize_key( wp_unslash( $_GET['h'] ) ) : '';

$state = 'invalid';
$judul = '';
$plat  = '';

if ( $pid && 'kendaraan' === get_post_type( $pid ) ) {
	$judul = get_the_title( $pid );
	$plat  = get_post_meta( $pid, 'kh_plat', true );

	if ( $tok && hash_equals( kh_cek_token( $pid ), $tok ) ) {
		$state = 'valid';

		if ( 'ditemukan' === $hasil ) {
			wp_set_object_terms( $pid, 'Ditemukan', 'kendaraan_status' );
			update_post_meta( $pid, 'kh_diff', current_time( 'mysql' ) );
			update_post_meta( $pid, 'kh_konfirmasi', 'ditemukan' );
			update_post_meta( $pid, 'kh_konfirmasi_waktu', current_time( 'mysql' ) );
			$state = 'found';

			wp_mail(
				kh_notif_email(),
				'[Peta Kendaraan] Pelapor menandai DITEMUKAN — ' . $plat,
				'Pelapor menandai laporan ini sudah ditemukan lewat tautan konfirmasi.' . "\n\n"
				. 'Judul: ' . $judul . "\nPlat : " . $plat . "\nEdit : " . admin_url( 'post.php?post=' . $pid . '&action=edit' )
			);
		} elseif ( 'masih' === $hasil ) {
			update_post_meta( $pid, 'kh_konfirmasi', 'masih' );
			update_post_meta( $pid, 'kh_konfirmasi_waktu', current_time( 'mysql' ) );
			update_post_meta( $pid, 'kh_recheck_sent', current_time( 'mysql' ) );
			$state = 'still';
		}
	}
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#04080d">
	<meta name="robots" content="noindex,nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'kh-page-body' ); ?>>

<header class="kh-top">
	<a class="kh-home" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Peta</a>
	<span class="kh-top-title">Konfirmasi laporan</span>
</header>

<main class="kh-wrap kh-legal">
	<span class="kh-badge">KONFIRMASI</span>

	<?php if ( 'found' === $state ) : ?>
		<h1>Terima kasih — laporan ditandai ditemukan</h1>
		<p class="kh-legal-lead">
			Laporan <strong><?php echo esc_html( $plat ); ?></strong> sudah kami tandai selesai dan tidak lagi tampil sebagai kendaraan hilang.
			Senang mendengarnya! Semoga urusannya lancar.
		</p>
		<div class="kh-legal-cta">
			<a class="kh-legal-btn" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">Lihat halaman laporan</a>
			<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Kembali ke peta</a>
		</div>

	<?php elseif ( 'still' === $state ) : ?>
		<h1>Baik — laporan tetap aktif</h1>
		<p class="kh-legal-lead">
			Laporan <strong><?php echo esc_html( $plat ); ?></strong> kami pertahankan di peta. Kalau nanti sudah ditemukan,
			kamu bisa kembali ke tautan ini atau tekan tombol “Laporkan data ini” di halaman laporan.
		</p>
		<div class="kh-legal-cta">
			<a class="kh-legal-btn" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">Lihat halaman laporan</a>
			<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Kembali ke peta</a>
		</div>

	<?php elseif ( 'valid' === $state ) : ?>
		<h1>Konfirmasi status</h1>
		<p class="kh-legal-lead">Laporan <strong><?php echo esc_html( $plat ); ?></strong> — pilih kondisinya sekarang:</p>
		<div class="kh-legal-cta">
			<a class="kh-legal-btn" href="<?php echo esc_url( kh_cek_url( $pid, 'ditemukan' ) ); ?>">Sudah ditemukan</a>
			<a class="kh-legal-btn ghost" href="<?php echo esc_url( kh_cek_url( $pid, 'masih' ) ); ?>">Masih hilang</a>
		</div>

	<?php else : ?>
		<h1>Tautan tidak valid</h1>
		<p class="kh-legal-lead">
			Tautan konfirmasi ini sudah tidak berlaku atau salah ketik. Kalau kamu ingin mengubah status laporan,
			buka halaman laporannya lalu tekan <strong>“Laporkan data ini”</strong>, atau hubungi pengelola situs.
		</p>
		<div class="kh-legal-cta">
			<a class="kh-legal-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Kembali ke peta</a>
			<a class="kh-legal-btn ghost" href="<?php echo esc_url( home_url( '/kontak/' ) ); ?>">Hubungi kami</a>
		</div>
	<?php endif; ?>
</main>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; data dikutip dari media dan laporan warga.
	<?php kh_legal_nav(); ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
