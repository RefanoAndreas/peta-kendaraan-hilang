<?php
/**
 * Halaman form pelapor (tema gelap).
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
	<span class="kh-top-title">Laporkan kendaraan hilang</span>
</header>

<main class="kh-wrap">
	<span class="kh-badge">FORM PELAPOR</span>
	<h1>Laporkan Kendaraan Hilang</h1>
	<p style="color:#6f8f98;margin:0 0 18px;max-width:60ch">
		Laporan akan diperiksa admin sebelum tampil di peta. Data nomor polisi ditampilkan lengkap
		agar warga bisa mengenali kendaraan Anda.
	</p>

	<div class="kh-auth" id="kh-auth">
		<div class="kh-auth-text">
			<strong>Masuk dengan Google</strong>
			<span id="auth-status">Diperlukan sebelum mengirim laporan.</span>
		</div>
		<div id="gsi-slot"></div>
	</div>

	<form id="kh-form" class="kh-form" autocomplete="off">
		<div class="kh-row">
			<label>Nomor polisi <span class="req">*</span>
				<input name="plat" required placeholder="L 3183 DAC" style="text-transform:uppercase">
			</label>
			<label>Jenis
				<select name="jenis">
					<option value="Motor">Motor</option>
					<option value="Mobil">Mobil</option>
					<option value="Truk">Truk</option>
					<option value="Lain">Lain</option>
				</select>
			</label>
		</div>

		<div class="kh-row">
			<label>Merk
				<input name="merk" placeholder="Honda">
			</label>
			<label>Model
				<input name="model" placeholder="Beat">
			</label>
			<label>Tahun
				<input name="tahun" inputmode="numeric" placeholder="2022">
			</label>
		</div>

		<div class="kh-row">
			<label>Warna / ciri
				<input name="warna" placeholder="Hitam, strip biru">
			</label>
			<label>Wilayah
				<select name="wilayah" id="kh-wilayah-form">
					<option value="">— pilih —</option>
					<?php
					$terms = get_terms( array( 'taxonomy' => 'kendaraan_wilayah', 'hide_empty' => false ) );
					if ( ! is_wp_error( $terms ) ) {
						foreach ( $terms as $t ) {
							echo '<option value="' . esc_attr( $t->name ) . '">' . esc_html( $t->name ) . '</option>';
						}
					}
					?>
				</select>
			</label>
		</div>

		<label>Lokasi kejadian <span class="req">*</span>
			<input name="lokasi" required placeholder="Kawasan Gunungsari, Surabaya">
			<span class="hint">Tulis level area/kelurahan, bukan alamat rumah lengkap.</span>
		</label>

		<div class="kh-row">
			<label>Tanggal kejadian <span class="req">*</span>
				<input type="date" name="tanggal" required>
			</label>
			<label>Jam (WIB)
				<input type="time" name="jam">
			</label>
			<label>Lapor ke
				<input name="polsek" placeholder="Polsek Sedati (opsional)">
			</label>
		</div>

		<label>Keterangan tambahan
			<textarea name="ciri" rows="3" placeholder="Ciri khusus, kondisi, kronologi singkat…"></textarea>
		</label>

		<div class="kh-pick">
			<div class="kh-pick-head">
				<strong>Titik lokasi di peta</strong>
				<span class="hint" id="pick-info">Klik peta untuk menandai lokasi kejadian (opsional tapi sangat membantu).</span>
			</div>
			<div id="kh-form-map" class="kh-form-map"></div>
			<input type="hidden" name="lat" id="f-lat">
			<input type="hidden" name="lng" id="f-lng">
		</div>

		<div class="kh-form-foot">
			<button type="submit" class="kh-submit" id="kh-submit" disabled>Kirim laporan</button>
			<span class="kh-form-msg" id="kh-msg"></span>
		</div>
	</form>
</main>

<footer class="kh-foot">
	Peta Kendaraan Hilang &middot; laporan warga diverifikasi admin sebelum tayang.
</footer>

<?php wp_footer(); ?>
</body>
</html>
