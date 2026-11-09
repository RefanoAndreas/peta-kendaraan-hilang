<?php
/**
 * Shortcode peta + tabel, aset front-end, dan template single.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kh_register_assets() {
	wp_register_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_register_style( 'leaflet-markercluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', array( 'leaflet' ), '1.5.3' );
	wp_register_style( 'leaflet-markercluster-default', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', array( 'leaflet-markercluster' ), '1.5.3' );
	wp_register_style( 'kh-map', KH_CORE_URL . 'assets/map.css', array( 'leaflet-markercluster-default' ), KH_CORE_VERSION );

	wp_register_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	wp_register_script( 'leaflet-markercluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', array( 'leaflet' ), '1.5.3', true );
	wp_register_script( 'kh-map', KH_CORE_URL . 'assets/map.js', array( 'leaflet', 'leaflet-markercluster' ), KH_CORE_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'kh_register_assets' );

/**
 * [peta_kendaraan height="640" jenis="" wilayah="" status=""]
 */
function kh_shortcode_peta( $atts ) {
	$atts = shortcode_atts(
		array(
			'height'  => '600',
			'jenis'   => '',
			'wilayah' => '',
			'status'  => '',
		),
		$atts,
		'peta_kendaraan'
	);

	wp_enqueue_style( 'kh-map' );
	wp_enqueue_script( 'kh-map' );

	wp_localize_script(
		'kh-map',
		'KH_MAP_CFG',
		array(
			'endpoint' => esc_url_raw( rest_url( 'kh/v1/peta' ) ),
			'ringkasan' => esc_url_raw( rest_url( 'kh/v1/ringkasan' ) ),
			'jenis'    => $atts['jenis'],
			'wilayah'  => $atts['wilayah'],
			'status'   => $atts['status'],
		)
	);

	ob_start();
	?>
	<div class="kh-wrap">
		<div class="kh-toolbar">
			<label>Jenis
				<select id="kh-filter-jenis">
					<option value="">Semua</option>
					<option value="motor">Motor</option>
					<option value="mobil">Mobil</option>
					<option value="truk">Truk</option>
					<option value="lain">Lain</option>
				</select>
			</label>
			<label>Status
				<select id="kh-filter-status">
					<option value="">Semua</option>
					<option value="hilang">Hilang</option>
					<option value="ditemukan">Ditemukan</option>
				</select>
			</label>
			<label>Wilayah
				<select id="kh-filter-wilayah">
					<option value="">Semua</option>
				</select>
			</label>
			<span class="kh-count" id="kh-count"></span>
		</div>
		<div id="kh-map" class="kh-map" style="height:<?php echo (int) $atts['height']; ?>px"
			data-endpoint="<?php echo esc_attr( rest_url( 'kh/v1/peta' ) ); ?>"
			data-ringkasan="<?php echo esc_attr( rest_url( 'kh/v1/ringkasan' ) ); ?>"
			data-jenis="<?php echo esc_attr( $atts['jenis'] ); ?>"
			data-wilayah="<?php echo esc_attr( $atts['wilayah'] ); ?>"
			data-status="<?php echo esc_attr( $atts['status'] ); ?>"></div>
		<p class="kh-note">
			Nomor polisi ditampilkan sebagian (<code>L 3183 **</code>) untuk melindungi pemilik.
			Data lengkap hanya diberikan kepada pihak kepolisian.
		</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'peta_kendaraan', 'kh_shortcode_peta' );

/**
 * [tabel_kendaraan limit="20"]
 */
function kh_shortcode_tabel( $atts ) {
	$atts  = shortcode_atts( array( 'limit' => '20' ), $atts, 'tabel_kendaraan' );
	$query = new WP_Query(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['limit'],
		)
	);

	ob_start();
	echo '<div class="kh-table-wrap"><table class="kh-table"><thead><tr><th>Tanggal</th><th>Kendaraan</th><th>Nomor polisi</th><th>Lokasi</th><th></th></tr></thead><tbody>';

	if ( ! $query->have_posts() ) {
		echo '<tr><td colspan="5">Belum ada laporan.</td></tr>';
	}

	while ( $query->have_posts() ) {
		$query->the_post();
		$id      = get_the_ID();
		$merk    = get_post_meta( $id, 'kh_merk', true );
		$model   = get_post_meta( $id, 'kh_model', true );
		$tahun   = get_post_meta( $id, 'kh_tahun', true );
		$tanggal = get_post_meta( $id, 'kh_tanggal', true );
		$jam     = get_post_meta( $id, 'kh_jam', true );
		$lokasi  = get_post_meta( $id, 'kh_lokasi', true );
		$plat    = get_post_meta( $id, 'kh_plat_masked', true );

		printf(
			'<tr><td>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td><a href="%s">Detail</a></td></tr>',
			esc_html( trim( $tanggal . ' ' . $jam ) ),
			esc_html( trim( $merk . ' ' . $model . ' ' . $tahun ) ),
			esc_html( $plat ),
			esc_html( $lokasi ),
			esc_url( get_permalink() )
		);
	}
	wp_reset_postdata();

	echo '</tbody></table></div>';
	return ob_get_clean();
}
add_shortcode( 'tabel_kendaraan', 'kh_shortcode_tabel' );

/**
 * Template single dari plugin.
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_singular( 'kendaraan' ) ) {
		$custom = KH_CORE_DIR . 'templates/single-kendaraan.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	if ( is_post_type_archive( 'kendaraan' ) ) {
		$custom = KH_CORE_DIR . 'templates/archive-kendaraan.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	if ( is_page( 'lapor' ) ) {
		$custom = KH_CORE_DIR . 'templates/form-lapor.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
} );
