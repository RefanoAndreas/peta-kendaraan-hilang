<?php
/**
 * Template halaman Analitik (infografis full-frame, tanpa container).
 *
 * @package kendaraan-hilang-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kh_publik = '1' === (string) get_option( 'kh_analitik_publik', '0' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#04080d">
	<?php if ( ! $kh_publik ) : ?>
	<meta name="robots" content="noindex, nofollow">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'kh-an-body' ); ?>>

<main id="an">

	<header class="an-hero">
		<div class="an-hero-bar">
			<a class="an-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Kembali ke peta</a>
			<span class="an-live"><i></i> diolah langsung dari laporan</span>
			<?php if ( ! $kh_publik ) : ?>
			<span class="an-draft" title="Halaman ini hanya terlihat oleh admin">mode admin &middot; belum publik</span>
			<?php endif; ?>
		</div>

		<h1 class="an-title">STATISTIK<span>KENDARAAN HILANG</span></h1>

		<p class="an-lead">
			<b id="an-n">&mdash;</b> laporan terverifikasi media, dihimpun sejak
			<b id="an-dari">&mdash;</b> hingga <b id="an-sampai">&mdash;</b>.
			Semua angka di bawah dihitung ulang di browser setiap halaman dibuka.
		</p>

		<div class="an-kpis" id="an-kpis">
			<div class="an-kpi" data-kpi="total">
				<span class="an-kpi-l">Total laporan</span>
				<span class="an-kpi-v" data-c="0">&mdash;</span>
				<span class="an-kpi-s">semua laporan terverifikasi</span>
			</div>
			<div class="an-kpi" data-kpi="tampil">
				<span class="an-kpi-l">Tampil di peta</span>
				<span class="an-kpi-v" data-c="0">&mdash;</span>
				<span class="an-kpi-s">180 hari terakhir</span>
			</div>
			<div class="an-kpi" data-kpi="model">
				<span class="an-kpi-l">Model tersering</span>
				<span class="an-kpi-v" data-c="">&mdash;</span>
				<span class="an-kpi-s">&mdash;</span>
			</div>
			<div class="an-kpi" data-kpi="jam">
				<span class="an-kpi-l">Jam rawan</span>
				<span class="an-kpi-v" data-c="">&mdash;</span>
				<span class="an-kpi-s">&mdash;</span>
			</div>
			<div class="an-kpi" data-kpi="wilayah">
				<span class="an-kpi-l">Wilayah terbanyak</span>
				<span class="an-kpi-v" data-c="">&mdash;</span>
				<span class="an-kpi-s">&mdash;</span>
			</div>
			<div class="an-kpi" data-kpi="laju">
				<span class="an-kpi-l">Rata-rata</span>
				<span class="an-kpi-v" data-c="0">&mdash;</span>
				<span class="an-kpi-s">laporan per bulan</span>
			</div>
		</div>
	</header>

	<section class="an-grid" id="an-grid">

		<article class="an-card an-s7" data-anim>
			<header class="an-card-h">
				<h2>Motor yang paling sering hilang</h2>
				<p>Dikelompokkan per keluarga model (Beat Street &amp; Beat Deluxe masuk &ldquo;Beat&rdquo;).</p>
			</header>
			<div class="an-fig" id="fig-model"></div>
		</article>

		<article class="an-card an-s5" data-anim>
			<header class="an-card-h">
				<h2>Surabaya: wilayah rawan</h2>
				<p>Ditaksir dari koordinat &rarr; kecamatan terdekat (31 kecamatan).</p>
			</header>
			<div class="an-fig an-fig-row">
				<div id="fig-region" class="an-donut"></div>
				<ul class="an-legend" id="leg-region"></ul>
			</div>
		</article>

		<article class="an-card an-s4" data-anim>
			<header class="an-card-h">
				<h2>Kabupaten / kota</h2>
				<p>Sesuai taksonomi wilayah laporan.</p>
			</header>
			<div class="an-fig" id="fig-wilayah"></div>
			<div class="an-note" id="note-wilayah"></div>
		</article>

		<article class="an-card an-s8" data-anim>
			<header class="an-card-h">
				<h2>Jam rawan</h2>
				<p>Sebaran 24 jam waktu kejadian. Jarum panjang = jam dengan laporan terbanyak.</p>
			</header>
			<div class="an-fig" id="fig-jam"></div>
			<div class="an-note" id="note-jam"></div>
		</article>

		<article class="an-card an-s7" data-anim>
			<header class="an-card-h">
				<h2>Tren per bulan</h2>
				<p>Jumlah laporan berdasarkan tanggal kejadian.</p>
			</header>
			<div class="an-fig" id="fig-tren"></div>
		</article>

		<article class="an-card an-s5" data-anim>
			<header class="an-card-h">
				<h2>Tahun kendaraan</h2>
				<p>Usia motor yang jadi sasaran.</p>
			</header>
			<div class="an-fig" id="fig-tahun"></div>
			<div class="an-note" id="note-tahun"></div>
		</article>

		<article class="an-card an-s4" data-anim>
			<header class="an-card-h">
				<h2>Kode plat nomor</h2>
				<p>Awalan nopol &mdash; petunjuk asal kendaraan.</p>
			</header>
			<div class="an-fig" id="fig-plat"></div>
		</article>

		<article class="an-card an-s4" data-anim>
			<header class="an-card-h">
				<h2>Hari kejadian</h2>
				<p>Senin sampai Minggu.</p>
			</header>
			<div class="an-fig" id="fig-hari"></div>
		</article>

		<article class="an-card an-s4" data-anim>
			<header class="an-card-h">
				<h2>Kecamatan teratas</h2>
				<p>Perkiraan dari titik koordinat, Surabaya saja.</p>
			</header>
			<div class="an-fig" id="fig-kec"></div>
		</article>

		<article class="an-card an-s7" data-anim>
			<header class="an-card-h">
				<h2>Sebaran titik laporan</h2>
				<p>Satu titik = satu laporan. Klik titik untuk lihat plat &amp; lokasi.</p>
			</header>
			<div class="an-map" id="fig-peta"></div>
		</article>

		<article class="an-card an-s5" data-anim>
			<header class="an-card-h">
				<h2>Lokasi yang paling sering muncul</h2>
				<p>Dari teks &ldquo;Lokasi kejadian&rdquo; yang diisi kurator.</p>
			</header>
			<div class="an-fig" id="fig-lokasi"></div>
		</article>

	</section>

	<footer class="an-foot">
		<p>
			Sumber data: laporan warga &amp; pemberitaan yang dikurasi, tersimpan di basis data situs ini.
			Angka bersifat perkiraan: lokasi kejadian berasal dari berita, bukan titik pasti &mdash;
			karena itu setiap laporan ditampilkan dengan radius 1&nbsp;km.
		</p>
		<p class="an-foot-links">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Peta</a>
			<a href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>">Laporkan kehilangan</a>
			<a href="<?php echo esc_url( home_url( '/tentang/' ) ); ?>">Tentang</a>
			<a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>">Disclaimer</a>
		</p>
	</footer>

</main>

<?php wp_footer(); ?>
</body>
</html>
