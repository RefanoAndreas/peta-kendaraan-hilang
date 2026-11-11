<?php
/**
 * Plugin Name: Kendaraan Hilang Core
 * Description: Data model (CPT + ACF fields), REST API, peta vektor, timeline, dan importer untuk situs laporan kendaraan hilang.
 * Version: 0.16.5
 * Author: Refano Andreas
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: kendaraan-hilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KH_CORE_VERSION', '0.16.5' );
define( 'KH_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'KH_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once KH_CORE_DIR . 'inc/cpt.php';
require_once KH_CORE_DIR . 'inc/fields.php';
require_once KH_CORE_DIR . 'inc/rest.php';
require_once KH_CORE_DIR . 'inc/map.php';
require_once KH_CORE_DIR . 'inc/ui.php';
require_once KH_CORE_DIR . 'inc/importer.php';
require_once KH_CORE_DIR . 'inc/bulk-import.php';
require_once KH_CORE_DIR . 'inc/acf.php';
require_once KH_CORE_DIR . 'inc/pwa.php';
require_once KH_CORE_DIR . 'inc/photos.php';
require_once KH_CORE_DIR . 'inc/submit.php';
require_once KH_CORE_DIR . 'inc/seo.php';
require_once KH_CORE_DIR . 'inc/schema.php';
require_once KH_CORE_DIR . 'inc/security.php';
require_once KH_CORE_DIR . 'inc/analytics.php';
require_once KH_CORE_DIR . 'inc/story.php';
require_once KH_CORE_DIR . 'inc/pages.php';
require_once KH_CORE_DIR . 'inc/admin-mod.php';
require_once KH_CORE_DIR . 'inc/recheck.php';
require_once KH_CORE_DIR . 'inc/admin-tools.php';

register_activation_hook( __FILE__, 'kh_core_activate' );

/**
 * Aktivasi: daftarkan CPT/taxonomy, isi term default, buat halaman peta depan.
 */
function kh_core_activate() {
	kh_register_cpt();
	kh_register_taxonomies();
	kh_seed_terms();
	kh_seed_front_page();
	kh_seed_demo_items();
	flush_rewrite_rules();
}
