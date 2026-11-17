<?php
/**
 * "Edit Cepat" kendaraan: pemilih titik peta + pencarian nama jalan,
 * dan penyederhanaan form (taksonomi Jenis/Wilayah/Status disembunyikan).
 *
 * Kenapa taksonomi disembunyikan: ketiganya memakan 3 kolom checklist di tengah
 * form. Nilainya tidak hilang saat disimpan karena kotak centangnya tetap ada
 * (hanya tidak tampil) dan WordPress tetap mengirim nilai lamanya.
 *
 * Catatan penting: plugin Admin Columns (Codepress) memanggil TableId dari
 * $_POST['post_type'] saat inline-save; form edit cepat inti tidak mengirimnya.
 * Field hidden post_type di bawah yang membuat penyimpanan tidak fatal error.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blok peta & pencarian, menempel pada kolom "Titik peta".
 */
add_action( 'quick_edit_custom_box', function ( $column, $post_type ) {
	if ( 'kendaraan' !== $post_type || 'kh_titik' !== $column ) {
		return;
	}
	?>
	<input type="hidden" name="post_type" value="kendaraan" />
	<div class="kh-qe">
		<p class="kh-qe-row">
			<label>Latitude
				<input type="text" name="khqe_lat" class="small-text kh-qe-lat" inputmode="decimal" autocomplete="off" />
			</label>
			<label>Longitude
				<input type="text" name="khqe_lng" class="small-text kh-qe-lng" inputmode="decimal" autocomplete="off" />
			</label>
			<label class="kh-qe-lebar">Lokasi kejadian
				<input type="text" name="khqe_lokasi" class="regular-text kh-qe-lokasi" autocomplete="off" />
			</label>
		</p>
		<p class="kh-qe-row">
			<input type="search" class="regular-text kh-qe-q" autocomplete="off"
				placeholder="Cari nama jalan / lokasi… (mis. Rungkut Asri)" />
			<button type="button" class="button kh-qe-go">Cari</button>
			<button type="button" class="button-link kh-qe-pakai" hidden>Pakai nama jalan ini</button>
		</p>
		<ul class="kh-qe-list" hidden></ul>
		<div class="kh-qe-map"></div>
		<p class="description kh-qe-info">
			Cari nama jalan atau klik peta untuk menaruh pin. Latitude &amp; longitude terisi otomatis.
		</p>
	</div>
	<?php
}, 10, 2 );

/**
 * Data baris (lat/lng/lokasi) supaya nilai lama terisi saat form dibuka.
 */
add_action( 'manage_kendaraan_posts_custom_column', function ( $col, $post_id ) {
	if ( 'kh_titik' !== $col ) {
		return;
	}
	printf(
		'<span class="kh-qe-data" hidden data-lat="%1$s" data-lng="%2$s" data-lokasi="%3$s"></span>',
		esc_attr( (string) get_post_meta( $post_id, 'kh_lat', true ) ),
		esc_attr( (string) get_post_meta( $post_id, 'kh_lng', true ) ),
		esc_attr( (string) get_post_meta( $post_id, 'kh_lokasi', true ) )
	);
}, 10, 2 );

/**
 * Aset hanya di halaman daftar kendaraan.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'edit.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'kendaraan' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	wp_enqueue_style( 'kh-admin-quick', KH_CORE_URL . 'assets/admin-quick.css', array(), KH_CORE_VERSION );
	wp_enqueue_script( 'kh-admin-quick', KH_CORE_URL . 'assets/admin-quick.js', array( 'jquery', 'leaflet' ), KH_CORE_VERSION, true );
	wp_localize_script(
		'kh-admin-quick',
		'KH_QE',
		array(
			'cari' => esc_url_raw( rest_url( 'kh/v1/cari' ) ),
		)
	);
} );

/**
 * Simpan koordinat & lokasi dari form edit cepat.
 */
add_action( 'save_post_kendaraan', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['khqe_lat'], $_POST['khqe_lng'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['_inline_edit'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' ) ) {
		return;
	}

	$lat_mentah = trim( (string) wp_unslash( $_POST['khqe_lat'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$lng_mentah = trim( (string) wp_unslash( $_POST['khqe_lng'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$lat        = (float) str_replace( ',', '.', $lat_mentah );
	$lng        = (float) str_replace( ',', '.', $lng_mentah );

	if ( '' === $lat_mentah && '' === $lng_mentah ) {
		delete_post_meta( $post_id, 'kh_lat' );
		delete_post_meta( $post_id, 'kh_lng' );
	} elseif ( $lat && $lng && abs( $lat ) <= 90 && abs( $lng ) <= 180 ) {
		update_post_meta( $post_id, 'kh_lat', $lat );
		update_post_meta( $post_id, 'kh_lng', $lng );
	}

	if ( isset( $_POST['khqe_lokasi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$lokasi = sanitize_text_field( wp_unslash( $_POST['khqe_lokasi'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $lokasi ) {
			update_post_meta( $post_id, 'kh_lokasi', $lokasi );
		}
	}
}, 20 );
