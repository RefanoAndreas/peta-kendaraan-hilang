<?php
/**
 * Google Analytics 4 (opsional).
 *
 * Aktif hanya kalau opsi `kh_ga_id` berisi Measurement ID (G-XXXXXXX).
 * Tanpa ID, tidak ada script yang dimuat — tidak ada cookie pelacakan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kh_ga_id() {
	$id = trim( (string) get_option( 'kh_ga_id', '' ) );
	return preg_match( '/^G-[A-Z0-9]{4,}$/i', $id ) ? $id : '';
}

add_action( 'wp_head', function () {
	$id = kh_ga_id();
	if ( ! $id ) {
		return;
	}
	?>
	<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', '<?php echo esc_js( $id ); ?>', { anonymize_ip: true });

	/* gtag.js (178 KB) dimuat setelah halaman selesai — tidak menahan render/TBT.
	   Antrean dataLayer tetap dikirim begitu script masuk.
	   Fallback idle sengaja 8 detik: dengan 2,5 detik, eksekusi gtag jatuh di
	   dalam jendela TBT Lighthouse di perangkat lambat (long task ~150-315 ms).
	   Pengunjung yang benar-benar pasif tetap terukur; yang berinteraksi langsung dimuat. */
	(function () {
		var src = 'https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js( $id ); ?>';
		function muat() {
			var s = document.createElement('script');
			s.async = true;
			s.src = src;
			document.head.appendChild(s);
		}
		var sudah = false;
		function sekali() {
			if (sudah) { return; }
			sudah = true;
			muat();
		}
		['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) {
			window.addEventListener(ev, sekali, { once: true, passive: true });
		});
		if ('complete' === document.readyState) {
			setTimeout(sekali, 8000);
		} else {
			window.addEventListener('load', function () { setTimeout(sekali, 8000); });
		}
	})();
	</script>
	<?php
}, 99 );
