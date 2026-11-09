<?php
/**
 * PWA: manifest, service worker, dan meta yang dibutuhkan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'kh_pwa_routes' );
function kh_pwa_routes() {
	add_rewrite_rule( '^manifest\.json$', 'index.php?kh_manifest=1', 'top' );
	add_rewrite_rule( '^sw\.js$', 'index.php?kh_sw=1', 'top' );

	if ( ! get_option( 'kh_pwa_flushed' ) ) {
		flush_rewrite_rules( false );
		update_option( 'kh_pwa_flushed', 1 );
	}
}

add_filter( 'query_vars', 'kh_pwa_vars' );
function kh_pwa_vars( $vars ) {
	$vars[] = 'kh_manifest';
	$vars[] = 'kh_sw';
	return $vars;
}

add_filter( 'redirect_canonical', 'kh_pwa_no_canonical', 10, 2 );
function kh_pwa_no_canonical( $redirect, $request ) {
	if ( get_query_var( 'kh_manifest' ) || get_query_var( 'kh_sw' ) ) {
		return false;
	}
	return $redirect;
}

add_action( 'template_redirect', 'kh_pwa_serve', 1 );
function kh_pwa_serve() {
	if ( get_query_var( 'kh_manifest' ) ) {
		$icons = array(
			array(
				'src'   => KH_CORE_URL . 'assets/icon-192.png',
				'sizes' => '192x192',
				'type'  => 'image/png',
			),
			array(
				'src'   => KH_CORE_URL . 'assets/icon-512.png',
				'sizes' => '512x512',
				'type'  => 'image/png',
			),
		);

		$manifest = array(
			'name'             => get_bloginfo( 'name' ),
			'short_name'       => 'Kendaraan Hilang',
			'description'      => 'Peta laporan kehilangan kendaraan di Jawa Timur',
			'start_url'        => home_url( '/' ),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'orientation'      => 'any',
			'background_color' => '#04080d',
			'theme_color'      => '#04080d',
			'lang'             => 'id',
			'icons'            => $icons,
		);

		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	if ( get_query_var( 'kh_sw' ) ) {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		header( 'Service-Worker-Allowed: /' );
		echo kh_pwa_service_worker();
		exit;
	}
}

/**
 * Isi service worker (generated agar versi ikut cache-busting).
 */
function kh_pwa_service_worker() {
	$ver  = KH_CORE_VERSION;
	$base = KH_CORE_URL . 'assets/';

	return <<<JS
/* Peta Kendaraan Hilang — service worker v{$ver} */
const CACHE = 'kh-' + '{$ver}';
const ASSETS = [
  '{$base}ui.css',
  '{$base}ui.js',
  '{$base}site.css',
  '{$base}map-style.js',
  '{$base}icon-192.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.indexOf('/wp-admin') === 0) return;

  /* aset statis: cache dulu */
  if (url.pathname.indexOf('/wp-content/') === 0 || url.pathname === '/manifest.json') {
    e.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
        return res;
      }).catch(() => hit))
    );
    return;
  }

  /* halaman: network dulu, fallback ke cache */
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
        return res;
      }).catch(() => caches.match(req).then((hit) => hit || caches.match('/')))
    );
  }
});
JS;
}

/**
 * Tag PWA di head.
 */
add_action( 'wp_head', 'kh_pwa_head', 5 );
function kh_pwa_head() {
	if ( ! is_front_page() && ! is_singular( 'kendaraan' ) ) {
		return;
	}
	$icon = KH_CORE_URL . 'assets/icon-192.png';
	?>
<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.json' ) ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $icon ); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Kendaraan Hilang">
<script>
if ('serviceWorker' in navigator) {
	window.addEventListener('load', function () {
		navigator.serviceWorker.register('<?php echo esc_url( home_url( '/sw.js' ) ); ?>', { scope: '/' }).catch(function () {});
	});
}
</script>
	<?php
}
