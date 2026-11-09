<?php
/**
 * Template front page: peta kendaraan hilang (single page, peta asli penuh).
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
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#04080d">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'kh-app-body' ); ?>>

<div id="app">
	<aside>
		<div class="brand">
			<h1 class="ori">PETA KENDARAAN HILANG</h1>
			<p id="sub">Memuat laporan&hellip;</p>
			<button type="button" class="kh-panel-close" id="kh-panel-close" aria-label="Tutup panel">&times;</button>
		</div>

		<div class="search">
			<input id="q" placeholder="Cari plat, motor, atau lokasi&hellip;" autocomplete="off" aria-label="Cari laporan">
		</div>

		<div class="chips" id="chips">
			<button type="button" class="chip on" data-f="all">Semua</button>
		</div>

		<div class="views">
			<button type="button" class="view-btn on" data-view="list">Daftar</button>
			<button type="button" class="view-btn" data-view="timeline">Timeline</button>
		</div>

		<div id="list"></div>
		<div id="timeline" hidden></div>

		<div class="foot">
			Data dikutip dari <a href="https://www.suarasurabaya.net/" target="_blank" rel="noopener nofollow">Suara Surabaya FM 100</a> dan laporan warga.
			Nomor polisi ditampilkan <strong>lengkap</strong> agar warga mudah mengenali kendaraan dan segera melapor.
		</div>
	</aside>

	<main>
		<div id="realmap"></div>
		<div id="boot">MEMUAT PETA&hellip;</div>

		<div class="legend">
			<div><i style="background:var(--bad)"></i>Belum lapor polisi</div>
			<div><i style="background:var(--rs)"></i>Sudah lapor polisi</div>
			<div><i style="background:var(--xm)"></i>Ditemukan</div>
		</div>

		<div class="hud">
			<div class="stat"><b id="n0">0</b><span>OBJEK</span></div>
			<div class="stat b"><b id="n1">0</b><span>BELUM</span></div>
			<div class="stat r"><b id="n2">0</b><span>LAPOR</span></div>
		</div>

		<div class="ctl">
			<button id="zf" title="Tampilkan semua">&odot;</button>
			<button id="zi" title="Perbesar">+</button>
			<button id="zo" title="Perkecil">&minus;</button>
		</div>

		<div id="card">
			<button class="x" id="cx" aria-label="Tutup">&times;</button>
			<h2 id="c-p"></h2>
			<dl id="c-d"></dl>
			<a class="more" id="c-more" href="#">Lihat laporan lengkap &rsaquo;</a>
		</div>

		<button type="button" class="kh-fab" id="kh-fab" aria-label="Buka daftar laporan">
			<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
				<path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
			</svg>
			<span>Daftar</span>
			<span class="kh-fab-count" id="kh-fab-count">0</span>
		</button>
	</main>

	<div class="kh-backdrop" id="kh-backdrop" hidden></div>
</div>

<?php wp_footer(); ?>
</body>
</html>
