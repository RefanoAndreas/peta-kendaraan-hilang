<?php
/**
 * Titik pusat 31 kecamatan Surabaya (hasil geocode OSM, 20 Nov 2026).
 * Dipakai halaman analitik untuk menaksir wilayah Surabaya (Pusat/Utara/Timur/
 * Selatan/Barat) dari koordinat laporan — teks lokasi di laporan warga hampir
 * tidak pernah menyebut nama kecamatan (coverage <20%).
 *
 * Format: 'Nama Kecamatan' => array( region, lat, lng )
 * Regenerate: tools/geokec.py (butuh koneksi; hasilnya deterministik).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'Asemrowo' => array( 'Barat', -7.25203, 112.71526 ),
	'Benowo' => array( 'Barat', -7.24878, 112.63541 ),
	'Lakarsantri' => array( 'Barat', -7.30429, 112.63299 ),
	'Pakal' => array( 'Barat', -7.23084, 112.61525 ),
	'Sambikerep' => array( 'Barat', -7.26589, 112.6553 ),
	'Sukomanunggal' => array( 'Barat', -7.26087, 112.71229 ),
	'Tandes' => array( 'Barat', -7.25905, 112.67804 ),
	'Bubutan' => array( 'Pusat', -7.25168, 112.73421 ),
	'Genteng' => array( 'Pusat', -7.26683, 112.74247 ),
	'Simokerto' => array( 'Pusat', -7.24373, 112.75792 ),
	'Tegalsari' => array( 'Pusat', -7.28794, 112.74053 ),
	'Dukuh Pakis' => array( 'Selatan', -7.28214, 112.7081 ),
	'Gayungan' => array( 'Selatan', -7.33802, 112.71676 ),
	'Jambangan' => array( 'Selatan', -7.3219, 112.71388 ),
	'Karang Pilang' => array( 'Selatan', -7.3334, 112.69938 ),
	'Sawahan' => array( 'Selatan', -7.28407, 112.71616 ),
	'Wiyung' => array( 'Selatan', -7.31373, 112.69527 ),
	'Wonocolo' => array( 'Selatan', -7.32007, 112.74125 ),
	'Wonokromo' => array( 'Selatan', -7.29158, 112.73204 ),
	'Gubeng' => array( 'Timur', -7.29797, 112.76132 ),
	'Gunung Anyar' => array( 'Timur', -7.33954, 112.79548 ),
	'Mulyorejo' => array( 'Timur', -7.26124, 112.78482 ),
	'Rungkut' => array( 'Timur', -7.32316, 112.77104 ),
	'Sukolilo' => array( 'Timur', -7.29958, 112.77034 ),
	'Tambaksari' => array( 'Timur', -7.25749, 112.75522 ),
	'Tenggilis Mejoyo' => array( 'Timur', -7.31394, 112.7573 ),
	'Bulak' => array( 'Utara', -7.23164, 112.78549 ),
	'Kenjeran' => array( 'Utara', -7.22668, 112.77559 ),
	'Krembangan' => array( 'Utara', -7.23291, 112.7226 ),
	'Pabean Cantian' => array( 'Utara', -7.21677, 112.72934 ),
	'Semampir' => array( 'Utara', -7.23045, 112.75267 ),
);
