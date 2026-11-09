<?php
/**
 * Form pelapor publik + verifikasi Google Sign-In.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Pengaturan (Client ID Google)
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'kh_settings_menu' );
function kh_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=kendaraan',
		'Pengaturan Peta',
		'Pengaturan',
		'manage_options',
		'kh-settings',
		'kh_settings_page'
	);
}

function kh_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Akses ditolak.' );
	}

	if ( isset( $_POST['kh_settings_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['kh_settings_nonce'] ) ), 'kh_settings' ) ) {
		update_option( 'kh_google_client_id', sanitize_text_field( wp_unslash( $_POST['kh_google_client_id'] ?? '' ) ) );
		echo '<div class="notice notice-success is-dismissible"><p>Pengaturan disimpan.</p></div>';
	}

	$client = get_option( 'kh_google_client_id', '' );
	$page   = get_page_by_path( 'lapor' );
	?>
	<div class="wrap">
		<h1>Pengaturan Peta Kendaraan Hilang</h1>
		<form method="post">
			<?php wp_nonce_field( 'kh_settings', 'kh_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="kh_google_client_id">Google OAuth Client ID</label></th>
					<td>
						<input type="text" class="regular-text code" id="kh_google_client_id" name="kh_google_client_id"
							value="<?php echo esc_attr( $client ); ?>" placeholder="1234567890-abc.apps.googleusercontent.com">
						<p class="description">
							Dari Google Cloud Console → APIs &amp; Services → Credentials → OAuth 2.0 Client ID (Web application).<br>
							Authorized JavaScript origins: <code><?php echo esc_html( home_url() ); ?></code>
						</p>
					</td>
				</tr>
				<tr>
					<th>Halaman form pelapor</th>
					<td>
						<?php if ( $page ) : ?>
							<a href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank"><?php echo esc_html( get_permalink( $page ) ); ?></a>
						<?php else : ?>
							<em>Belum dibuat (akan dibuat otomatis saat halaman dimuat).</em>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Simpan' ); ?>
		</form>
	</div>
	<?php
}

/* ------------------------------------------------------------------
 * Halaman /lapor/ dibuat otomatis
 * ------------------------------------------------------------------ */

add_action( 'init', 'kh_ensure_lapor_page', 20 );
function kh_ensure_lapor_page() {
	if ( get_option( 'kh_lapor_page_id' ) ) {
		return;
	}
	$existing = get_page_by_path( 'lapor' );
	if ( $existing ) {
		update_option( 'kh_lapor_page_id', $existing->ID );
		return;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Laporkan Kendaraan Hilang',
			'post_name'    => 'lapor',
			'post_content' => '',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_option( 'kh_lapor_page_id', $id );
	}
}

/* ------------------------------------------------------------------
 * REST: terima laporan
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route(
		'kh/v1',
		'/lapor',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'kh_rest_lapor',
		)
	);
} );

function kh_rest_lapor( WP_REST_Request $req ) {
	$client_id = get_option( 'kh_google_client_id', '' );
	$cred      = (string) $req->get_param( 'credential' );

	$email = '';
	$name  = '';

	/* 1. kalau admin/pengguna WP sudah login, boleh tanpa Google */
	if ( is_user_logged_in() ) {
		$u     = wp_get_current_user();
		$email = $u->user_email;
		$name  = $u->display_name;
	}

	/* 2. verifikasi id_token Google */
	if ( $cred ) {
		if ( ! $client_id ) {
			return new WP_Error( 'kh_no_client', 'Admin belum mengatur Google Client ID.', array( 'status' => 503 ) );
		}
		$resp = wp_remote_get(
			'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode( $cred ),
			array( 'timeout' => 15 )
		);
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'kh_verify', 'Gagal memverifikasi akun Google.', array( 'status' => 502 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data['aud'] ) || $data['aud'] !== $client_id ) {
			return new WP_Error( 'kh_aud', 'Token Google tidak cocok dengan client ID situs.', array( 'status' => 403 ) );
		}
		if ( empty( $data['email'] ) ) {
			return new WP_Error( 'kh_email', 'Akun Google tidak mengirim email.', array( 'status' => 403 ) );
		}
		$verified = isset( $data['email_verified'] ) ? filter_var( $data['email_verified'], FILTER_VALIDATE_BOOLEAN ) : false;
		if ( ! $verified ) {
			return new WP_Error( 'kh_unverified', 'Email Google belum terverifikasi.', array( 'status' => 403 ) );
		}
		$email = sanitize_email( $data['email'] );
		$name  = sanitize_text_field( $data['name'] ?? '' );
	}

	if ( ! $email ) {
		return new WP_Error( 'kh_auth', 'Silakan masuk dengan Google terlebih dahulu.', array( 'status' => 401 ) );
	}

	/* 3. rate limit: 3 laporan / jam / email */
	$rl_key = 'kh_rl_' . md5( strtolower( $email ) );
	$count  = (int) get_transient( $rl_key );
	if ( $count >= 3 ) {
		return new WP_Error( 'kh_rate', 'Terlalu banyak laporan dari akun ini. Coba lagi nanti.', array( 'status' => 429 ) );
	}

	/* 4. validasi field */
	$plat   = kh_normalize_plat( (string) $req->get_param( 'plat' ) );
	$lokasi = sanitize_text_field( (string) $req->get_param( 'lokasi' ) );

	if ( strlen( $plat ) < 4 ) {
		return new WP_Error( 'kh_plat', 'Nomor polisi wajib diisi dengan benar.', array( 'status' => 400 ) );
	}
	if ( strlen( $lokasi ) < 3 ) {
		return new WP_Error( 'kh_lokasi', 'Lokasi kejadian wajib diisi.', array( 'status' => 400 ) );
	}

	/* 5. duplikat plat */
	$dupe = new WP_Query(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'kh_plat',
					'value' => $plat,
				),
			),
		)
	);
	if ( $dupe->posts ) {
		return new WP_Error( 'kh_dupe', 'Laporan dengan nomor polisi ini sudah ada.', array( 'status' => 409 ) );
	}

	/* 6. buat pengguna kalau belum ada */
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		$uid = wp_insert_user(
			array(
				'user_login'   => sanitize_user( current( explode( '@', $email ) ) . '_' . wp_rand( 100, 999 ), true ),
				'user_email'   => $email,
				'display_name' => $name ? $name : $email,
				'user_pass'    => wp_generate_password( 24 ),
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $uid ) ) {
			return new WP_Error( 'kh_user', 'Gagal membuat akun: ' . $uid->get_error_message(), array( 'status' => 500 ) );
		}
	} else {
		$uid = $user->ID;
	}

	/* 7. simpan sebagai pending */
	$merk   = sanitize_text_field( (string) $req->get_param( 'merk' ) );
	$model  = sanitize_text_field( (string) $req->get_param( 'model' ) );
	$tahun  = sanitize_text_field( (string) $req->get_param( 'tahun' ) );
	$warna  = sanitize_text_field( (string) $req->get_param( 'warna' ) );
	$ciri   = sanitize_textarea_field( (string) $req->get_param( 'ciri' ) );
	$tgl    = sanitize_text_field( (string) $req->get_param( 'tanggal' ) );
	$jam    = sanitize_text_field( (string) $req->get_param( 'jam' ) );
	$polsek = sanitize_text_field( (string) $req->get_param( 'polsek' ) );
	$sttlp  = sanitize_text_field( (string) $req->get_param( 'sttlp' ) );
	$lat    = (string) $req->get_param( 'lat' );
	$lng    = (string) $req->get_param( 'lng' );
	$jenis  = sanitize_text_field( (string) $req->get_param( 'jenis' ) );
	$wilayah = sanitize_text_field( (string) $req->get_param( 'wilayah' ) );

	$judul = trim( sprintf( 'Hilang %s%s di %s', trim( $merk . ' ' . $model ), $tahun ? ' ' . $tahun : '', $lokasi ) );

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'kendaraan',
			'post_status'  => 'pending',
			'post_title'   => $judul,
			'post_author'  => $uid,
			'post_content' => $ciri ? $ciri : '',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'kh_save', 'Gagal menyimpan laporan.', array( 'status' => 500 ) );
	}

	kh_save_meta_values(
		$post_id,
		array(
			'plat'        => $plat,
			'merk'        => $merk,
			'model'       => $model,
			'tahun'       => $tahun,
			'warna'       => $warna,
			'ciri'        => $ciri,
			'tanggal'     => $tgl,
			'jam'         => $jam,
			'lokasi'      => $lokasi,
			'lat'         => is_numeric( $lat ) ? $lat : '',
			'lng'         => is_numeric( $lng ) ? $lng : '',
			'polsek'      => $polsek,
			'sttlp'       => $sttlp,
			'sumber_nama' => 'Laporan warga',
			'sumber_url'  => '',
		)
	);

	// Laporan publik selalu mulai dari level paling rendah; admin yang menaikkan.
	update_post_meta( $post_id, 'kh_verifikasi', 'warga' );

	update_post_meta( $post_id, 'kh_pelapor_email', $email );
	update_post_meta( $post_id, 'kh_pelapor_nama', $name );

	if ( $jenis ) {
		wp_set_object_terms( $post_id, $jenis, 'kendaraan_jenis' );
	}
	if ( $wilayah ) {
		wp_set_object_terms( $post_id, $wilayah, 'kendaraan_wilayah' );
	}
	wp_set_object_terms( $post_id, 'Hilang', 'kendaraan_status' );

	set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'id'      => $post_id,
			'message' => 'Laporan diterima dan menunggu verifikasi admin.',
		),
		201
	);
}

/* ------------------------------------------------------------------
 * REST: laporkan data yang bermasalah (keberatan / takedown request)
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route(
		'kh/v1',
		'/lapor-masalah',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'kh_rest_lapor_masalah',
		)
	);
} );

function kh_rest_lapor_masalah( WP_REST_Request $req ) {
	$id     = absint( $req->get_param( 'id' ) );
	$alasan = sanitize_textarea_field( (string) $req->get_param( 'alasan' ) );

	if ( ! $id || 'kendaraan' !== get_post_type( $id ) ) {
		return new WP_Error( 'kh_notfound', 'Data tidak ditemukan.', array( 'status' => 404 ) );
	}
	if ( mb_strlen( trim( $alasan ) ) < 5 ) {
		return new WP_Error( 'kh_alasan', 'Tulis alasan minimal 5 karakter.', array( 'status' => 400 ) );
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	$key = 'kh_abuse_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		return new WP_Error( 'kh_rate', 'Terlalu banyak keberatan dari jaringan ini hari ini.', array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, DAY_IN_SECONDS );

	$count = (int) get_post_meta( $id, 'kh_dilaporkan', true );
	update_post_meta( $id, 'kh_dilaporkan', $count + 1 );
	update_post_meta( $id, 'kh_dilaporkan_alasan', $alasan );
	update_post_meta( $id, 'kh_dilaporkan_waktu', current_time( 'mysql' ) );

	$link = admin_url( 'post.php?post=' . $id . '&action=edit' );
	$body = "Ada keberatan terhadap satu data kendaraan.\n\n"
		. 'Judul: ' . get_the_title( $id ) . "\n"
		. 'Plat : ' . get_post_meta( $id, 'kh_plat', true ) . "\n"
		. 'Alasan: ' . $alasan . "\n"
		. 'Waktu : ' . current_time( 'mysql' ) . "\n"
		. 'Total keberatan: ' . ( $count + 1 ) . "\n\n"
		. 'Periksa: ' . $link . "\n";

	wp_mail( get_option( 'admin_email' ), '[Peta Kendaraan] Keberatan: ' . get_the_title( $id ), $body );

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'message' => 'Terima kasih. Keberatan diteruskan ke admin untuk diperiksa.',
		),
		201
	);
}

/**
 * Notifikasi di admin kalau ada data yang dilaporkan bermasalah.
 */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kendaraan' !== $screen->id ) {
		return;
	}
	$reported = new WP_Query(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'kh_dilaporkan',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	if ( ! $reported->posts ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Ada data yang dilaporkan bermasalah</strong> — periksa: ';
	$links = array();
	foreach ( $reported->posts as $pid ) {
		$links[] = '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a>';
	}
	echo wp_kses_post( implode( ' · ', $links ) );
	echo '</p></div>';
} );
