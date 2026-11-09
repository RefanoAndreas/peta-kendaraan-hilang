<?php
/**
 * Integrasi Advanced Custom Fields (ACF).
 * Field memakai meta key yang sama dengan versi native (kh_*) supaya data lama tetap terbaca.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daftarkan field group via PHP (tidak perlu import JSON di UI).
 */
add_action( 'acf/init', 'kh_acf_field_group' );
function kh_acf_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_kh_kendaraan',
			'title'    => 'Detail Kendaraan (ACF)',
			'fields'   => array(
				array(
					'key'          => 'field_kh_plat',
					'label'        => 'Nomor Polisi',
					'name'         => 'kh_plat',
					'type'         => 'text',
					'required'     => 1,
					'placeholder'  => 'L 3183 DAC',
					'instructions' => 'Tulis lengkap. Ditampilkan penuh di situs agar warga bisa mengenali dan melapor.',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_kh_merk',
					'label'   => 'Merk',
					'name'    => 'kh_merk',
					'type'    => 'text',
					'placeholder' => 'Honda / Yamaha / Suzuki',
					'wrapper' => array( 'width' => '25' ),
				),
				array(
					'key'     => 'field_kh_model',
					'label'   => 'Model',
					'name'    => 'kh_model',
					'type'    => 'text',
					'placeholder' => 'Beat / Vario / Scoopy',
					'wrapper' => array( 'width' => '25' ),
				),
				array(
					'key'     => 'field_kh_tahun',
					'label'   => 'Tahun',
					'name'    => 'kh_tahun',
					'type'    => 'number',
					'min'     => 1970,
					'max'     => 2100,
					'wrapper' => array( 'width' => '25' ),
				),
				array(
					'key'     => 'field_kh_warna',
					'label'   => 'Warna / ciri',
					'name'    => 'kh_warna',
					'type'    => 'text',
					'placeholder' => 'Hitam, putih strip biru, ...',
					'wrapper' => array( 'width' => '35' ),
				),
				array(
					'key'     => 'field_kh_ciri',
					'label'   => 'Ciri khusus',
					'name'    => 'kh_ciri',
					'type'    => 'textarea',
					'rows'    => 2,
					'wrapper' => array( 'width' => '40' ),
				),
				array(
					'key'            => 'field_kh_tanggal',
					'label'          => 'Tanggal kejadian',
					'name'           => 'kh_tanggal',
					'type'           => 'date_picker',
					'display_format' => 'd/m/Y',
					'return_format'  => 'Y-m-d',
					'save_format'    => 'Y-m-d',
					'first_day'      => 1,
					'wrapper'        => array( 'width' => '25' ),
				),
				array(
					'key'           => 'field_kh_jam',
					'label'         => 'Jam (WIB)',
					'name'          => 'kh_jam',
					'type'          => 'time_picker',
					'display_format' => 'H:i',
					'return_format' => 'H:i',
					'wrapper'       => array( 'width' => '25' ),
				),
				array(
					'key'          => 'field_kh_lokasi',
					'label'        => 'Lokasi kejadian',
					'name'         => 'kh_lokasi',
					'type'         => 'text',
					'instructions' => 'Level area/kelurahan, bukan alamat rumah lengkap.',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_kh_polsek',
					'label'        => 'Lapor ke',
					'name'         => 'kh_polsek',
					'type'         => 'text',
					'placeholder'  => 'Polsek Sedati',
					'instructions' => 'Kosongkan kalau belum ada laporan polisi.',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_kh_lat',
					'label'        => 'Latitude',
					'name'         => 'kh_lat',
					'type'         => 'number',
					'step'         => 'any',
					'instructions' => 'Klik peta di bawah untuk mengisi otomatis. Contoh: -7.28773',
					'wrapper'      => array( 'width' => '25' ),
				),
				array(
					'key'     => 'field_kh_lng',
					'label'   => 'Longitude',
					'name'    => 'kh_lng',
					'type'    => 'number',
					'step'    => 'any',
					'wrapper' => array( 'width' => '25' ),
				),
				array(
					'key'          => 'field_kh_sumber_nama',
					'label'        => 'Sumber',
					'name'         => 'kh_sumber_nama',
					'type'         => 'text',
					'placeholder'  => 'Suara Surabaya FM 100',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_kh_sumber_url',
					'label'   => 'URL sumber',
					'name'    => 'kh_sumber_url',
					'type'    => 'url',
					'wrapper' => array( 'width' => '50' ),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'kendaraan',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Field detail laporan kendaraan hilang.',
		)
	);
}

/**
 * Hitung ulang plat yang ditampilkan setelah ACF menyimpan.
 */
add_action( 'acf/save_post', 'kh_acf_after_save', 20 );
function kh_acf_after_save( $post_id ) {
	if ( ! is_numeric( $post_id ) || 'kendaraan' !== get_post_type( $post_id ) ) {
		return;
	}
	$plat = get_post_meta( $post_id, 'kh_plat', true );
	update_post_meta( $post_id, 'kh_plat_masked', kh_normalize_plat( $plat ) );
}
