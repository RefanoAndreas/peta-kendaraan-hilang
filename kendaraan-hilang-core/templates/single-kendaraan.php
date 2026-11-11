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
	$tanggal = kh_normalize_tanggal( get_post_meta( $id, 'kh_tanggal', true ) );
	$jam     = get_post_meta( $id, 'kh_jam', true );
	$lokasi  = get_post_meta( $id, 'kh_lokasi', true );
	$lat     = get_post_meta( $id, 'kh_lat', true );
	$lng     = get_post_meta( $id, 'kh_lng', true );
	$polsek  = get_post_meta( $id, 'kh_polsek', true );
	$sttlp   = get_post_meta( $id, 'kh_sttlp', true );
	$verif   = kh_verifikasi_of( $id );
	$snama   = get_post_meta( $id, 'kh_sumber_nama', true );
	$surl    = get_post_meta( $id, 'kh_sumber_url', true );

	$status  = wp_get_post_terms( $id, 'kendaraan_status', array( 'fields' => 'names' ) );
	$status  = $status ? $status[0] : 'Hilang';
	$is_got  = ( 'ditemukan' === strtolower( $status ) );
	$badge   = $is_got ? 'is-got' : ( $polsek ? 'is-rep' : '' );
	$vlabel  = kh_verifikasi_levels();
	$vtext   = $is_got ? 'Sudah ditemukan' : $vlabel[ $verif ];

	$rows = array(
		'Kendaraan'    => trim( $merk . ' ' . $model ),
		'Tahun'        => $tahun,
		'Warna'        => $warna,
		'Ciri khusus'  => $ciri,
		'Waktu'        => trim( kh_tanggal_teks( $tanggal ) . ( $jam ? ' · pukul ' . $jam . ' WIB' : '' ) ),
		'Lokasi'       => $lokasi,
		'Status'       => $is_got ? 'Sudah ditemukan' : ( $polsek ? 'Dilaporkan ke ' . $polsek : 'Belum ada laporan polisi' ),
		'Nomor STTLP'  => $sttlp,
	);
	?>
	<main class="kh-wrap">
		<span class="kh-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( strtoupper( $status ) ); ?></span>
		<div class="kh-verif kh-verif-<?php echo esc_attr( $is_got ? 'got' : $verif ); ?>">
			<?php echo esc_html( $vtext ); ?>
		</div>
		<h1><?php the_title(); ?></h1>

		<?php if ( $plat ) : ?>
			<div class="kh-plat"><?php echo esc_html( $plat ); ?></div>
		<?php endif; ?>

		<?php if ( has_post_thumbnail( $id ) ) : ?>
			<figure class="kh-photo-wrap">
				<?php echo get_the_post_thumbnail( $id, 'large', array( 'class' => 'kh-photo', 'alt' => 'Foto kendaraan ' . $plat ) ); ?>
				<figcaption>Foto dari pelapor/sumber. Kalau ini kendaraanmu dan fotonya keliru, gunakan tombol &ldquo;Laporkan data ini&rdquo; di bawah.</figcaption>
			</figure>
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
					el.className = 'mk-hex <?php echo $is_got ? 'mk-got' : esc_attr( 'mk-ver-' . $verif ); ?>';
					el.setAttribute('role', 'img');
					el.setAttribute('aria-label', <?php echo wp_json_encode( 'Lokasi terakhir ' . ( $plat ? 'plat ' . $plat . ', ' : '' ) . $lokasi ); ?>);
					var P = '0,-15 13,-7.5 13,7.5 0,15 -13,7.5 -13,-7.5';
					el.innerHTML = '<svg viewBox="-20 -20 40 40" width="34" height="34" aria-hidden="true">' +
						'<polygon points="' + P + '" fill="currentColor" fill-opacity=".26" stroke="currentColor" stroke-width="2"/>' +
						'<polygon points="0,-7 6,-3.5 6,3.5 0,7 -6,3.5 -6,-3.5" fill="none" stroke="currentColor" stroke-width="1.2"/>' +
						'<circle r="2.4" fill="#fff"/></svg>';
					el.style.color = <?php echo $is_got ? "'#b06bff'" : ( 'polisi' === $verif ? "'#35f0c8'" : ( 'media' === $verif ? "'#2fb4ff'" : "'#ffb02e'" ) ); ?>;
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
				<?php
				$slabel = $snama ? $snama : $surl;
				if ( false !== stripos( $surl, 'instagram.com' ) ) {
					$slabel .= ' · Instagram';
				}
				?>
				<a href="<?php echo esc_url( $surl ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $slabel ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $snama ? $snama : 'Laporan warga' ); ?>
			<?php endif; ?>
			<br><span style="opacity:.75">Nomor polisi ditampilkan lengkap agar warga mudah mengenali kendaraan dan bisa melapor.</span>
			<?php if ( $surl || ( $snama && false === stripos( $snama, 'laporan warga' ) ) ) : ?>
				<br><span class="kh-copyright">
					Yang dikutip dari sumber di atas hanya <strong>datanya</strong> (plat, model, waktu, lokasi). Desain dan foto tetap milik pemiliknya &mdash;
					situs ini tidak berafiliasi dengan media tersebut. Ada data yang perlu dihapus? Gunakan tombol &ldquo;Laporkan data ini&rdquo; atau
					<a href="<?php echo esc_url( home_url( '/kontak/' ) ); ?>">hubungi kami</a> (dilayani 1&times;24 jam).
				</span>
			<?php endif; ?>
		</div>
		<div class="kh-share">
			<span class="kh-share-lbl">Bantu sebarkan:</span>
			<a class="kh-share-btn wa" target="_blank" rel="noopener"
				href="https://wa.me/?text=<?php echo rawurlencode( 'Hilang ' . trim( $merk . ' ' . $model . ( $tahun ? ' ' . $tahun : '' ) ) . ' — ' . $plat . ' di ' . $lokasi . '. Bantu pantau & laporkan kalau melihatnya: ' . get_permalink() ); ?>">WhatsApp</a>
			<a class="kh-share-btn tg" target="_blank" rel="noopener"
				href="https://t.me/share/url?url=<?php echo rawurlencode( get_permalink() ); ?>&text=<?php echo rawurlencode( 'Hilang ' . $plat . ' di ' . $lokasi ); ?>">Telegram</a>
			<a class="kh-share-btn fb" target="_blank" rel="noopener"
				href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( get_permalink() ); ?>">Facebook</a>
			<button type="button" class="kh-share-btn copy" id="kh-copy-link" data-url="<?php echo esc_url( get_permalink() ); ?>">Salin tautan</button>
			<button type="button" class="kh-share-btn story" id="kh-story-btn">Story IG/FB</button>
			<span class="kh-share-msg" id="kh-copy-msg" role="status"></span>
		</div>

		<script type="application/json" id="kh-story-data"><?php echo wp_json_encode( kh_story_data( $id ) ); ?></script>

		<div class="kh-found">
			<button type="button" class="kh-found-btn" id="kh-found-btn">Saya menemukan kendaraan ini</button>
			<form class="kh-found-form" id="kh-found-form" hidden>
				<strong class="kh-found-title">Info penemuan</strong>
				<label for="kh-found-lokasi">Di mana kendaraan terlihat? <span class="req">*</span></label>
				<input id="kh-found-lokasi" maxlength="120" placeholder="Contoh: parkiran minimarket Rungkut, Surabaya">
				<label for="kh-found-catatan">Keterangan tambahan</label>
				<textarea id="kh-found-catatan" rows="3" maxlength="500" placeholder="Waktu terlihat, kondisi, ciri yang cocok…"></textarea>
				<label for="kh-found-kontak">Kontak kamu (opsional, tidak ditampilkan publik)</label>
				<input id="kh-found-kontak" maxlength="80" placeholder="Nomor WA atau email">
				<div class="kh-found-actions">
					<button type="submit">Kirim info</button>
					<button type="button" class="ghost" id="kh-found-cancel">Batal</button>
				</div>
				<p class="kh-found-msg" id="kh-found-msg" role="status"></p>
				<p class="kh-found-note">Info ini hanya masuk ke admin dan pelapor — tidak tampil di halaman publik. Jangan minta atau memberi uang; serah terima di kantor polisi.</p>
			</form>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var url = <?php echo wp_json_encode( get_permalink() ); ?>;

			var copyBtn = document.getElementById('kh-copy-link');
			var copyMsg = document.getElementById('kh-copy-msg');
			if (copyBtn) {
				copyBtn.addEventListener('click', function () {
					var done = function () { if (copyMsg) { copyMsg.textContent = 'Tautan disalin.'; } };
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(url).then(done, function () {
							if (copyMsg) { copyMsg.textContent = url; }
						});
					} else if (copyMsg) {
						copyMsg.textContent = url;
					}
				});
			}

			var fBtn = document.getElementById('kh-found-btn');
			var fForm = document.getElementById('kh-found-form');
			var fCancel = document.getElementById('kh-found-cancel');
			var fMsg = document.getElementById('kh-found-msg');
			var fLok = document.getElementById('kh-found-lokasi');
			if (!fBtn || !fForm) { return; }

			fBtn.addEventListener('click', function () {
				fForm.hidden = false;
				fBtn.hidden = true;
				fLok.focus();
			});
			fCancel.addEventListener('click', function () {
				fForm.hidden = true;
				fBtn.hidden = false;
				fMsg.textContent = '';
			});
			fForm.addEventListener('submit', function (ev) {
				ev.preventDefault();
				var lok = (fLok.value || '').trim();
				if (lok.length < 3) {
					fMsg.textContent = 'Tulis dulu di mana kendaraan terlihat.';
					return;
				}
				fMsg.textContent = 'Mengirim…';
				fetch(<?php echo wp_json_encode( esc_url_raw( rest_url( 'kh/v1/temuan' ) ) ); ?>, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
					body: JSON.stringify({
						id: <?php echo (int) $id; ?>,
						lokasi: lok,
						catatan: (document.getElementById('kh-found-catatan').value || '').trim(),
						kontak: (document.getElementById('kh-found-kontak').value || '').trim()
					})
				}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
					.then(function (res) {
						fMsg.textContent = res.ok ? (res.j.message || 'Terima kasih.') : (res.j.message || 'Gagal mengirim.');
						if (res.ok) {
							fLok.value = '';
							document.getElementById('kh-found-catatan').value = '';
							document.getElementById('kh-found-kontak').value = '';
						}
					})
					.catch(function () { fMsg.textContent = 'Gagal mengirim, coba lagi.'; });
			});
		});
		</script>

		<div class="kh-report" id="kh-report">
			<button type="button" class="kh-report-btn" id="kh-report-btn">Laporkan data ini</button>
			<form class="kh-report-form" id="kh-report-form" hidden>
				<label for="kh-report-text">Apa yang salah dengan data ini?</label>
				<textarea id="kh-report-text" rows="3" maxlength="500" required
					placeholder="Contoh: kendaraan ini bukan hilang, atau plat ini milik kendaraan saya."></textarea>
				<div class="kh-report-actions">
					<button type="submit">Kirim keberatan</button>
					<button type="button" class="ghost" id="kh-report-cancel">Batal</button>
				</div>
				<p class="kh-report-msg" id="kh-report-msg" role="status"></p>
			</form>
			<p class="kh-report-note">Keberatan diperiksa admin. Kalau datanya keliru, kami sembunyikan dalam 1&times;24 jam.</p>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var btn = document.getElementById('kh-report-btn');
			var form = document.getElementById('kh-report-form');
			var cancel = document.getElementById('kh-report-cancel');
			var msg = document.getElementById('kh-report-msg');
			var ta = document.getElementById('kh-report-text');
			if (!btn || !form) { return; }

			btn.addEventListener('click', function () {
				form.hidden = false;
				btn.hidden = true;
				ta.focus();
			});
			cancel.addEventListener('click', function () {
				form.hidden = true;
				btn.hidden = false;
				msg.textContent = '';
			});
			form.addEventListener('submit', function (ev) {
				ev.preventDefault();
				var alasan = (ta.value || '').trim();
				if (alasan.length < 5) {
					msg.textContent = 'Tulis alasan minimal 5 karakter.';
					return;
				}
				msg.textContent = 'Mengirim…';
				fetch(<?php echo wp_json_encode( esc_url_raw( rest_url( 'kh/v1/lapor-masalah' ) ) ); ?>, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
					body: JSON.stringify({ id: <?php echo (int) $id; ?>, alasan: alasan })
				}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
					.then(function (res) {
						msg.textContent = res.ok ? (res.j.message || 'Terima kasih.') : (res.j.message || 'Gagal mengirim.');
						if (res.ok) { ta.value = ''; }
					})
					.catch(function () { msg.textContent = 'Gagal mengirim, coba lagi.'; });
			});
		});
		</script>

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
