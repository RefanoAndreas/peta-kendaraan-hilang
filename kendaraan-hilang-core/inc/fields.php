<?php
/**
 * Custom fields (meta box) + helper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'kh_register_cpt' );
add_action( 'init', 'kh_register_taxonomies' );

/**
 * Definisi field.
 */
function kh_meta_fields() {
	return array(
		'plat'         => array( 'label' => 'Nomor Polisi (lengkap)', 'type' => 'text', 'hint' => 'Contoh: L 3183 DAC — ditampilkan penuh di situs agar warga bisa mengenali dan melapor' ),
		'merk'         => array( 'label' => 'Merk', 'type' => 'text', 'hint' => 'Honda, Yamaha, Suzuki, ...' ),
		'model'        => array( 'label' => 'Model', 'type' => 'text', 'hint' => 'Beat, Vario, Scoopy, ...' ),
		'tahun'        => array( 'label' => 'Tahun', 'type' => 'text' ),
		'warna'        => array( 'label' => 'Warna / ciri', 'type' => 'text' ),
		'ciri'         => array( 'label' => 'Ciri khusus', 'type' => 'textarea' ),
		'tanggal'      => array( 'label' => 'Tanggal kejadian', 'type' => 'date' ),
		'jam'          => array( 'label' => 'Jam (WIB)', 'type' => 'time' ),
		'lokasi'       => array( 'label' => 'Lokasi kejadian', 'type' => 'text', 'hint' => 'Tulis level area/kelurahan, bukan alamat rumah lengkap' ),
		'lat'          => array( 'label' => 'Latitude', 'type' => 'text' ),
		'lng'          => array( 'label' => 'Longitude', 'type' => 'text' ),
		'polsek'       => array( 'label' => 'Lapor ke', 'type' => 'text', 'hint' => 'Contoh: Polsek Sedati' ),
		'sumber_nama'  => array( 'label' => 'Sumber', 'type' => 'text', 'hint' => 'Contoh: Suara Surabaya FM 100' ),
		'sumber_url'   => array( 'label' => 'URL sumber', 'type' => 'url' ),
	);
}

/**
 * Meta box.
 */
add_action( 'add_meta_boxes', 'kh_add_meta_box' );
function kh_add_meta_box() {
	add_meta_box( 'kh_detail', 'Detail Kendaraan', 'kh_render_meta_box', 'kendaraan', 'normal', 'high' );
}

function kh_render_meta_box( $post ) {
	wp_nonce_field( 'kh_save_detail', 'kh_nonce' );

	echo '<table class="form-table"><tbody>';
	foreach ( kh_meta_fields() as $key => $conf ) {
		$value = get_post_meta( $post->ID, 'kh_' . $key, true );
		echo '<tr><th style="width:180px"><label for="kh_' . esc_attr( $key ) . '">' . esc_html( $conf['label'] ) . '</label></th><td>';
		if ( 'textarea' === $conf['type'] ) {
			echo '<textarea id="kh_' . esc_attr( $key ) . '" name="kh_' . esc_attr( $key ) . '" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input type="' . esc_attr( $conf['type'] ) . '" id="kh_' . esc_attr( $key ) . '" name="kh_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
		}
		if ( ! empty( $conf['hint'] ) ) {
			echo '<p class="description">' . esc_html( $conf['hint'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '<tr><th>Pilih titik di peta</th><td><div id="kh-picker" style="height:320px;border:1px solid #ccd0d4;border-radius:4px"></div><p class="description">Klik peta untuk mengisi Latitude/Longitude. Kalau kosong, pilih perkiraan lokasi kejadian.</p></td></tr>';
	echo '</tbody></table>';
}

/**
 * Simpan meta.
 */
add_action( 'save_post_kendaraan', 'kh_save_meta_box', 10, 2 );
function kh_save_meta_box( $post_id, $post ) {
	if ( ! isset( $_POST['kh_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['kh_nonce'] ) ), 'kh_save_detail' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$data = array();
	foreach ( kh_meta_fields() as $key => $conf ) {
		$raw = isset( $_POST[ 'kh_' . $key ] ) ? wp_unslash( $_POST[ 'kh_' . $key ] ) : '';
		$data[ $key ] = ( 'url' === $conf['type'] ) ? esc_url_raw( $raw ) : sanitize_text_field( $raw );
	}
	kh_save_meta_values( $post_id, $data );
}

/**
 * Simpan seluruh nilai meta + hitung plat yang di-mask.
 */
function kh_save_meta_values( $post_id, $data ) {
	foreach ( $data as $key => $value ) {
		if ( in_array( $key, array( 'jenis', 'wilayah' ), true ) ) {
			continue;
		}
		update_post_meta( $post_id, 'kh_' . $key, $value );
	}
	$plat = isset( $data['plat'] ) ? kh_normalize_plat( $data['plat'] ) : '';
	update_post_meta( $post_id, 'kh_plat', $plat );
	update_post_meta( $post_id, 'kh_plat_masked', kh_mask_plat( $plat ) );
}

function kh_normalize_plat( $plat ) {
	$plat = strtoupper( trim( (string) $plat ) );
	$plat = preg_replace( '/[^A-Z0-9 ]/', '', $plat );
	return preg_replace( '/\s+/', ' ', $plat );
}

/**
 * Nomor polisi ditampilkan PENUH (mengikuti media: agar warga bisa mengenali
 * kendaraan dan melapor kalau melihatnya). Fungsi dipertahankan agar
 * pemanggil lama tetap jalan.
 */
function kh_mask_plat( $plat ) {
	return kh_normalize_plat( $plat );
}

/**
 * Kolom admin.
 */
add_filter( 'manage_kendaraan_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['kh_plat']     = 'Nomor Polisi';
			$new['kh_tanggal']  = 'Tanggal';
			$new['kh_lokasi']   = 'Lokasi';
			$new['kh_titik']    = 'Titik peta';
		}
	}
	return $new;
} );

add_action( 'manage_kendaraan_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'kh_plat':
			echo esc_html( get_post_meta( $post_id, 'kh_plat', true ) );
			break;
		case 'kh_tanggal':
			$t = get_post_meta( $post_id, 'kh_tanggal', true );
			$j = get_post_meta( $post_id, 'kh_jam', true );
			echo esc_html( trim( $t . ' ' . $j ) );
			break;
		case 'kh_lokasi':
			echo esc_html( get_post_meta( $post_id, 'kh_lokasi', true ) );
			break;
		case 'kh_titik':
			$lat = get_post_meta( $post_id, 'kh_lat', true );
			$lng = get_post_meta( $post_id, 'kh_lng', true );
			echo ( '' !== $lat && '' !== $lng ) ? '<span style="color:#2271b1">✓</span>' : '<span style="color:#d63638">—</span>';
			break;
	}
}, 10, 2 );

/**
 * Leaflet picker di layar edit.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'kendaraan' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	wp_add_inline_script( 'leaflet', kh_admin_picker_js() );
} );

function kh_admin_picker_js() {
	return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var box = document.getElementById('kh-picker');
	if (!box || typeof L === 'undefined') { return; }
	var latEl = document.getElementById('kh_lat'), lngEl = document.getElementById('kh_lng');
	var lat = parseFloat(latEl && latEl.value), lng = parseFloat(lngEl && lngEl.value);
	var has = !isNaN(lat) && !isNaN(lng);
	var center = has ? [lat, lng] : [-7.2575, 112.7521];
	var map = L.map(box).setView(center, has ? 16 : 11);
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19, attribution: '&copy; OpenStreetMap'
	}).addTo(map);
	var marker = has ? L.marker(center).addTo(map) : null;
	map.on('click', function (e) {
		if (marker) { marker.setLatLng(e.latlng); } else { marker = L.marker(e.latlng).addTo(map); }
		if (latEl) { latEl.value = e.latlng.lat.toFixed(6); }
		if (lngEl) { lngEl.value = e.latlng.lng.toFixed(6); }
	});
	setTimeout(function () { map.invalidateSize(); }, 400);
});
JS;
}
