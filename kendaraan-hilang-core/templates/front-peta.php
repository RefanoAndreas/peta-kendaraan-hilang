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

		<div class="chips-wrap" id="chips-wrap">
			<div class="chips" id="chips">
				<button type="button" class="chip on" data-f="all">Semua</button>
			</div>
		</div>

		<div class="kh-selects">
			<select id="kh-verif" aria-label="Filter verifikasi">
				<option value="">Semua verifikasi</option>
				<option value="warga">Belum diverifikasi</option>
				<option value="media">Terverifikasi media</option>
				<option value="polisi">Sudah lapor polisi</option>
			</select>
			<select id="kh-status" aria-label="Filter status">
				<option value="">Semua status</option>
				<option value="hilang">Hilang</option>
				<option value="ditemukan">Ditemukan</option>
			</select>
		</div>

		<label class="kh-arsip-toggle">
			<input type="checkbox" id="kh-arsip"> Tampilkan arsip (&gt;<?php echo (int) KH_ARSIP_HARI; ?> hari)
		</label>

		<div class="views">
			<button type="button" class="view-btn on" data-view="list">Daftar</button>
			<button type="button" class="view-btn" data-view="timeline">Timeline</button>
		</div>

		<div class="tl-filter" id="tl-filter" hidden>
			<label for="kh-periode">Periode</label>
			<select id="kh-periode">
				<option value="all">Semua</option>
				<option value="7">7 hari terakhir</option>
				<option value="30">30 hari terakhir</option>
				<option value="90">90 hari terakhir</option>
			</select>
		</div>

		<div id="list"><?php kh_list_awal(); ?></div>
		<div id="timeline" hidden></div>

		<div class="foot">
			Data dikutip dari <a href="https://www.suarasurabaya.net/" target="_blank" rel="noopener nofollow">Suara Surabaya FM 100</a> dan laporan warga.
			Nomor polisi ditampilkan <strong>lengkap</strong> agar warga mudah mengenali kendaraan dan segera melapor.
			<a class="kh-foot-hub" href="<?php echo esc_url( home_url( '/wilayah/' ) ); ?>">Daftar laporan &amp; wilayah &rsaquo;</a>
			<?php kh_legal_nav(); ?>
		</div>
	</aside>

	<main>
		<div id="realmap"></div>
		<div id="boot">MEMUAT PETA&hellip;</div>

		<div class="legend">
			<div><i style="background:#ffb02e"></i>Belum diverifikasi</div>
			<div><i style="background:#2fb4ff"></i>Terverifikasi media</div>
			<div><i style="background:#35f0c8"></i>Sudah lapor polisi</div>
			<div><i style="background:#b06bff"></i>Ditemukan</div>
		</div>

		<!-- legenda radius: hanya tampil saat lingkaran 1 km aktif -->
		<div id="kh-radius-key" class="kh-radius-key" role="note" hidden>
			<span class="ring" aria-hidden="true"></span>
			<span>Lingkaran 1 km &mdash; area perkiraan dari lokasi di berita</span>
		</div>

		<div class="hud">
			<div class="stat"><b id="n0">0</b><span>OBJEK</span></div>
			<div class="stat b"><b id="n1">0</b><span>BELUM VERIF</span></div>
			<div class="stat r"><b id="n2">0</b><span>TERVERIFIKASI</span></div>
		</div>

		<a class="kh-add" id="kh-add" href="<?php echo esc_url( home_url( '/lapor/' ) ); ?>"
			aria-label="Laporkan kendaraan hilang" title="Laporkan kendaraan hilang">
			<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
				<!-- heksagon + plus: beda jelas dari tombol zoom '+' di kanan bawah -->
				<path d="M12 2.6 20.2 7.3v9.4L12 21.4 3.8 16.7V7.3z" fill="none" stroke="currentColor" stroke-width="1.5"/>
				<path d="M12 8.4v7.2M8.4 12h7.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			</svg>
		</a>

		<div class="ctl">
			<button id="zg" title="Lokasi saya" aria-label="Lokasi saya">
				<svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">
					<circle cx="12" cy="12" r="3" fill="currentColor"/>
					<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/>
				</svg>
			</button>
			<button id="zf" title="Tampilkan semua" aria-label="Tampilkan semua laporan">&odot;</button>
			<button id="zc" title="Sembunyikan pengelompokan marker" aria-label="Sembunyikan pengelompokan marker" aria-pressed="true">
				<svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">
					<circle cx="8.2" cy="9" r="3.1" fill="none" stroke="currentColor" stroke-width="1.6"/>
					<circle cx="15.8" cy="9" r="3.1" fill="none" stroke="currentColor" stroke-width="1.6"/>
					<circle cx="12" cy="15.4" r="3.1" fill="none" stroke="currentColor" stroke-width="1.6"/>
					<line class="slash" x1="4.5" y1="19.5" x2="19.5" y2="4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
				</svg>
			</button>
			<button id="zi" title="Perbesar" aria-label="Perbesar peta">+</button>
			<button id="zo" title="Perkecil" aria-label="Perkecil peta">&minus;</button>
		</div>

		<div id="toast" class="kh-toast" hidden></div>

		<div id="card">
			<button class="x" id="cx" aria-label="Tutup">&times;</button>
			<h2 id="c-p"></h2>
			<dl id="c-d"></dl>
			<div class="kh-card-actions">
				<a class="more" id="c-more" href="#">Lihat laporan lengkap &rsaquo;</a>
				<button type="button" class="copy" id="c-copy">Salin tautan</button>
			</div>
		</div>

		<button type="button" class="kh-fab" id="kh-fab" aria-label="Daftar laporan">
			<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
				<path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
			</svg>
			<span>Daftar</span>
			<span class="kh-fab-count" id="kh-fab-count" aria-hidden="true">0</span>
		</button>
	</main>

	<div class="kh-backdrop" id="kh-backdrop" hidden></div>
</div>

<?php wp_footer(); ?>
</body>
</html>
