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
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
	<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', '<?php echo esc_js( $id ); ?>', { anonymize_ip: true });
	</script>
	<?php
}, 99 );
