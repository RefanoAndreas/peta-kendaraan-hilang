<?php
/**
 * Alat bantu admin: jalankan cek ulang manual + tautan konfirmasi pelapor.
 * Di-load setelah admin-mod.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tombol "jalankan cek ulang" di atas daftar kendaraan.
 */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kendaraan' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=kh_recheck_now' ),
		'kh_recheck_now'
	);
	echo '<div class="notice notice-info"><p><strong>Cek ulang otomatis</strong> berjalan tiap hari (email ke pelapor laporan berumur 25–40 hari). '
		. 'Jalankan sekarang: <a href="' . esc_url( $url ) . '">kirim cek ulang hari ini</a>.</p></div>';

	if ( isset( $_GET['kh_recheck'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$n = absint( $_GET['kh_recheck'] ); // phpcs:ignore WordPress.Security.NonceVerification
		printf( '<div class="notice notice-success is-dismissible"><p>%d email cek ulang terkirim.</p></div>', (int) $n );
	}
} );

add_action( 'admin_post_kh_recheck_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak punya izin.' );
	}
	check_admin_referer( 'kh_recheck_now' );

	$n    = kh_run_recheck();
	$back = admin_url( 'edit.php?post_type=kendaraan' );
	wp_safe_redirect( add_query_arg( 'kh_recheck', (int) $n, $back ) );
	exit;
} );

/**
 * Meta box: eskalasi ke redaksi media.
 *
 * Hanya menampilkan tombol "tandai terkirim" kalau pelapor memberi izin (kh_izin_media).
 * Isi pesan sengaja dibatasi pada data kendaraan — tanpa nama/email/nomor pelapor —
 * dan menyebut bahwa situs tidak berafiliasi dengan media tersebut.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'kh_eskalasi',
		'Eskalasi ke media',
		function ( $post ) {
			$izin = (string) get_post_meta( $post->ID, 'kh_izin_media', true );
			$kirim = get_post_meta( $post->ID, 'kh_eskalasi', true );
			$kirim = is_array( $kirim ) ? $kirim : array();

			if ( '' === $izin ) {
				echo '<p style="margin:0 0 8px">Pelapor <strong style="color:#b32d2e">belum memberi izin</strong> meneruskan data ini ke media.</p>';
				echo '<p class="description">Jangan kirim data apa pun. Kalau perlu, tanyakan dulu lewat email pelapor di panel “Tautan konfirmasi pelapor”.</p>';
				return;
			}

			printf(
				'<p style="margin:0 0 8px">Izin diterima: <strong>%s</strong> — data kendaraan saja, tanpa identitas pelapor.</p>',
				esc_html( $izin )
			);

			$plat   = (string) get_post_meta( $post->ID, 'kh_plat', true );
			$merk   = trim( get_post_meta( $post->ID, 'kh_merk', true ) . ' ' . get_post_meta( $post->ID, 'kh_model', true ) );
			$warna  = (string) get_post_meta( $post->ID, 'kh_warna', true );
			$lokasi = (string) get_post_meta( $post->ID, 'kh_lokasi', true );
			$tgl    = function_exists( 'kh_normalize_tanggal' ) ? kh_normalize_tanggal( get_post_meta( $post->ID, 'kh_tanggal', true ) ) : '';
			$jam    = (string) get_post_meta( $post->ID, 'kh_jam', true );
			$ciri   = (string) get_post_meta( $post->ID, 'kh_ciri', true );

			$pesan = "Selamat siang, izin menyampaikan informasi laporan kehilangan kendaraan.\n\n"
				. 'Nomor polisi : ' . $plat . "\n"
				. 'Kendaraan    : ' . $merk . ( $warna ? ' · ' . $warna : '' ) . "\n"
				. 'Lokasi       : ' . $lokasi . "\n"
				. 'Waktu        : ' . trim( $tgl . ( $jam ? ' ' . $jam . ' WIB' : '' ) ) . "\n"
				. ( $ciri ? 'Ciri khusus  : ' . $ciri . "\n" : '' )
				. "\nSumber: laporan warga yang masuk ke Peta Kendaraan Hilang ("
				. home_url( '/' ) . ") dan sudah mendapat izin pelapor untuk diteruskan.\n"
				. "Catatan: kami bukan media dan tidak berafiliasi dengan rekanan mana pun — informasi ini silakan dicek dulu.\n";

			echo '<p style="margin:0 0 4px"><strong>Pesan siap kirim</strong> (data kendaraan saja):</p>';
			echo '<textarea readonly rows="9" style="width:100%;font-size:11px;line-height:1.5" onclick="this.select()">' . esc_textarea( $pesan ) . '</textarea>';

			if ( $kirim ) {
				printf(
					'<p style="margin:10px 0 0;color:#00794a"><strong>Sudah ditandai terkirim:</strong> %s</p>',
					esc_html( $kirim['waktu'] ?? '' )
				);
			} else {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=kh_eskalasi&post=' . $post->ID ), 'kh_eskalasi_' . $post->ID );
				echo '<p style="margin:10px 0 0"><a class="button" href="' . esc_url( $url ) . '">Tandai sudah dikirim ke redaksi</a></p>';
			}

			echo '<p class="description" style="margin-top:8px">Jangan menjanjikan laporan akan dimuat, dan jangan pernah meminta/menerima uang. Kalau media menerbitkan, simpan tautan artikelnya di kolom sumber laporan agar levelnya bisa naik menjadi “terverifikasi media”.</p>';
		},
		'kendaraan',
		'side',
		'default'
	);
} );

add_action( 'admin_post_kh_eskalasi', function () {
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( 'Tidak punya izin.' );
	}
	check_admin_referer( 'kh_eskalasi_' . $id );

	if ( '' === (string) get_post_meta( $id, 'kh_izin_media', true ) ) {
		wp_die( 'Pelapor belum memberi izin untuk meneruskan data ini ke media.' );
	}

	update_post_meta(
		$id,
		'kh_eskalasi',
		array(
			'waktu' => current_time( 'mysql' ),
			'oleh'  => get_current_user_id(),
		)
	);

	wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit&kh_eskalasi=1' ) );
	exit;
} );

/**
 * Meta box: riwayat info penemuan (dengan status identitas).
 *
 * Yang belum terverifikasi TIDAK diteruskan ke pelapor — admin perlu lihat
 * kontaknya di sini, bukan hanya di email/Telegram.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'kh_temuan_list',
		'Info penemuan masuk',
		function ( $post ) {
			$list = get_post_meta( $post->ID, 'kh_temuan', true );
			if ( ! is_array( $list ) || ! $list ) {
				echo '<p style="margin:0;color:#646970">Belum ada info penemuan.</p>';
				return;
			}

			echo '<table class="widefat striped" style="font-size:12px"><tbody>';
			foreach ( array_reverse( $list ) as $t ) {
				$verif = ! empty( $t['terverifikasi'] );
				$label = $verif
					? '<span style="color:#00794a;font-weight:600">terverifikasi</span>'
					: '<span style="color:#b32d2e;font-weight:600">belum terverifikasi</span>';

				printf(
					'<tr><td><strong>%s</strong><br>%s<br>Kontak: <code>%s</code><br>Identitas: %s%s<br>IP: <code>%s</code></td></tr>',
					esc_html( $t['waktu'] ?? '' ),
					esc_html( $t['lokasi'] ?? '' ),
					esc_html( ( $t['kontak'] ?? '' ) !== '' ? $t['kontak'] : '—' ),
					wp_kses_post( $label ),
					! empty( $t['email'] ) ? '<br>Google: <code>' . esc_html( $t['email'] ) . '</code>' : '',
					esc_html( $t['ip'] ?? '' )
				);
				if ( ! empty( $t['catatan'] ) ) {
					echo '<tr><td style="background:#fff"><em>' . esc_html( wp_trim_words( $t['catatan'], 40, '…' ) ) . '</em></td></tr>';
				}
			}
			echo '</tbody></table>';
			echo '<p class="description" style="margin-top:8px">Hubungi kontak di atas untuk memastikan sebelum meneruskan ke pelapor. Jangan pernah meminta atau menerima uang.</p>';
		},
		'kendaraan',
		'normal',
		'default'
	);
} );

/**
 * Tampilan "Ada info penemuan": daftar laporan yang perlu keputusan admin.
 *
 * Info penemuan dari pengunjung (meta `kh_temuan`) dan konfirmasi pelapor
 * (`kh_konfirmasi = ditemukan`) sebelumnya hanya terlihat kalau halaman laporan
 * dibuka satu per satu — padahal keduanya butuh tindakan: admin memutuskan
 * apakah laporan ditandai "Ditemukan". Tanpa tampilan ini, status "ditemukan"
 * tidak pernah terisi walau buktinya sudah masuk.
 */
add_filter( 'views_edit-kendaraan', function ( $views ) {
	global $wpdb;

	$n = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type = 'kendaraan' AND p.post_status = 'publish'
		   AND ( ( pm.meta_key = 'kh_temuan' AND pm.meta_value <> '' )
		      OR ( pm.meta_key = 'kh_konfirmasi' AND pm.meta_value = 'ditemukan' ) )"
	);

	$aktif = isset( $_GET['kh_view'] ) && 'penemuan' === $_GET['kh_view']; // phpcs:ignore WordPress.Security.NonceVerification
	$views['kh_penemuan'] = sprintf(
		'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
		esc_url( add_query_arg( array( 'post_type' => 'kendaraan', 'kh_view' => 'penemuan' ), admin_url( 'edit.php' ) ) ),
		$aktif ? 'current' : '',
		'Ada info penemuan',
		$n
	);

	return $views;
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'kendaraan' !== $q->get( 'post_type' ) ) {
		return;
	}
	if ( ! isset( $_GET['kh_view'] ) || 'penemuan' !== $_GET['kh_view'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$q->set(
		'meta_query',
		array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array(
				'key'     => 'kh_temuan',
				'compare' => 'EXISTS',
			),
			array(
				'key'   => 'kh_konfirmasi',
				'value' => 'ditemukan',
			),
		)
	);
}, 20 );

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kendaraan' !== $screen->id ) {
		return;
	}
	if ( ! isset( $_GET['kh_view'] ) || 'penemuan' !== $_GET['kh_view'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	echo '<div class="notice notice-info"><p><strong>Laporan dengan info penemuan.</strong> '
		. 'Buka laporan untuk membaca kontak penemu, hubungi pelapor lebih dulu, lalu pakai aksi <em>Tandai ditemukan</em> '
		. 'kalau kendaraannya memang sudah kembali. Jangan tandai sebelum kontaknya diverifikasi.</p></div>';
} );

/**
 * Meta box: tautan konfirmasi (untuk dikirim manual / diuji).
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'kh_cek_link',
		'Tautan konfirmasi pelapor',
		function ( $post ) {
			$email = get_post_meta( $post->ID, 'kh_pelapor_email', true );
			$sent  = get_post_meta( $post->ID, 'kh_recheck_sent', true );

			echo '<p style="margin:0 0 6px">Email pelapor: <code>' . esc_html( $email ? $email : '— belum ada' ) . '</code></p>';
			echo '<p style="margin:0 0 6px">Cek ulang terakhir: ' . esc_html( $sent ? $sent : '— belum pernah' ) . '</p>';

			$still = kh_cek_url( $post->ID, 'masih' );
			$found = kh_cek_url( $post->ID, 'ditemukan' );
			echo '<p style="margin:8px 0 4px"><strong>Masih hilang:</strong><br><input type="text" readonly style="width:100%;font-size:11px" value="' . esc_attr( $still ) . '" onclick="this.select()"></p>';
			echo '<p style="margin:8px 0 4px"><strong>Sudah ditemukan:</strong><br><input type="text" readonly style="width:100%;font-size:11px" value="' . esc_attr( $found ) . '" onclick="this.select()"></p>';
			echo '<p class="description" style="margin-top:8px">Tautan ini mengubah status laporan. Kirim hanya ke pelapor.</p>';
		},
		'kendaraan',
		'side',
		'default'
	);
} );
