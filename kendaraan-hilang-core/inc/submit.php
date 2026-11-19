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
		update_option( 'kh_google_key', sanitize_text_field( wp_unslash( $_POST['kh_google_key'] ?? '' ) ) );
		update_option( 'kh_ga_id', sanitize_text_field( wp_unslash( $_POST['kh_ga_id'] ?? '' ) ) );

		$notif = sanitize_email( wp_unslash( $_POST['kh_notif_email'] ?? '' ) );
		update_option( 'kh_notif_email', $notif );
		update_option( 'kh_tg_token', sanitize_text_field( wp_unslash( $_POST['kh_tg_token'] ?? '' ) ) );
		update_option( 'kh_tg_chat', sanitize_text_field( wp_unslash( $_POST['kh_tg_chat'] ?? '' ) ) );
		update_option( 'kh_ai_key', sanitize_text_field( wp_unslash( $_POST['kh_ai_key'] ?? '' ) ) );
		update_option( 'kh_ai_model', sanitize_text_field( wp_unslash( $_POST['kh_ai_model'] ?? '' ) ) );
		update_option( 'kh_ai_enabled', isset( $_POST['kh_ai_enabled'] ) ? '1' : '0' );
		update_option( 'kh_ig_aktif', isset( $_POST['kh_ig_aktif'] ) ? '1' : '0' );

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
					<th><label for="kh_google_key">Google Places — API key</label></th>
					<td>
						<input type="text" class="regular-text code" id="kh_google_key" name="kh_google_key"
							value="<?php echo esc_attr( get_option( 'kh_google_key', '' ) ); ?>" placeholder="AIza...">
						<p class="description">
							Dipakai untuk kotak <strong>Cari nama jalan</strong> di layar edit &amp; edit cepat.
							Peta tetap memakai MapLibre/OpenStreetMap — hanya pencariannya yang lewat Google.<br>
							Google Cloud Console → APIs &amp; Services → <em>Enable APIs</em>: <code>Places API (New)</code>
							(+ <code>Geocoding API</code> bila perlu) → Credentials → API key.
							Batasi key dengan <em>IP address</em> server (bukan HTTP referrer), karena panggilan dibuat dari server.<br>
							Kosongkan untuk kembali memakai OpenStreetMap (gratis). Jatah gratis Google: 10.000 panggilan/bulan per SKU.
							<?php if ( kh_google_aktif() ) : ?>
								<br><strong style="color:#00794a">Aktif</strong> — pencarian memakai Google Places.
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="kh_ga_id">Google Analytics 4</label></th>
					<td>
						<input type="text" class="regular-text code" id="kh_ga_id" name="kh_ga_id"
							value="<?php echo esc_attr( get_option( 'kh_ga_id', '' ) ); ?>" placeholder="G-XXXXXXXXXX">
						<p class="description">Measurement ID GA4. Kosongkan kalau belum mau memasang analytics — tidak ada script/cookie pelacakan.</p>
					</td>
				</tr>
				<tr>
					<th><label for="kh_notif_email">Email notifikasi</label></th>
					<td>
						<input type="email" class="regular-text" id="kh_notif_email" name="kh_notif_email"
							value="<?php echo esc_attr( get_option( 'kh_notif_email', get_option( 'admin_email' ) ) ); ?>">
						<p class="description">Penerima email "laporan baru", "info penemuan", dan "keberatan".</p>
					</td>
				</tr>
				<tr>
					<th><label for="kh_tg_token">Bot Telegram — token</label></th>
					<td>
						<input type="text" class="regular-text code" id="kh_tg_token" name="kh_tg_token"
							value="<?php echo esc_attr( get_option( 'kh_tg_token', '' ) ); ?>" placeholder="123456789:AAH...">
						<p class="description">
							Dari <strong>@BotFather</strong> di Telegram (<code>/newbot</code>). Disimpan di database WordPress,
							tidak pernah dikirim ke pengunjung. Kosongkan kalau belum mau memakai Telegram.
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="kh_tg_chat">Chat ID tujuan</label></th>
					<td>
						<input type="text" class="regular-text code" id="kh_tg_chat" name="kh_tg_chat"
							value="<?php echo esc_attr( get_option( 'kh_tg_chat', '' ) ); ?>" placeholder="123456789">
						<p class="description">
							Sudah kirim pesan apa saja ke bot? Klik <em>Cari Chat ID</em> di bawah, ID-nya akan muncul.
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="kh_ai_key">AI ekstraksi (DeepSeek)</label></th>
					<td>
						<input type="text" class="regular-text code" id="kh_ai_key" name="kh_ai_key"
							value="<?php echo esc_attr( get_option( 'kh_ai_key', '' ) ); ?>" placeholder="sk-...">
						<p class="description">
							API key dari <a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener">platform.deepseek.com</a>.
							Dipakai <strong>hanya saat admin mengimpor artikel</strong> — teks artikel yang publik dikirim untuk diekstrak,
							hasilnya disimpan sebagai data laporan. Kosongkan untuk memakai parser regex biasa.
						</p>
						<p>
							<label><input type="checkbox" name="kh_ai_enabled" value="1" <?php checked( '1', get_option( 'kh_ai_enabled', '1' ) ); ?>>
								Aktifkan AI saat impor (jatuh kembali ke regex kalau AI gagal)</label>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="kh_ai_model">Model AI</label></th>
					<td>
						<input type="text" class="regular-text code" id="kh_ai_model" name="kh_ai_model"
							value="<?php echo esc_attr( get_option( 'kh_ai_model', 'deepseek-flash' ) ); ?>" placeholder="deepseek-flash">
						<p class="description">Nama model DeepSeek. Default <code>deepseek-flash</code>; bisa diganti <code>deepseek-v4-pro</code> untuk akurasi lebih tinggi.</p>
					</td>
				</tr>
				<tr>
					<th>Ambil gambar dari link Instagram</th>
					<td>
						<p>
							<label><input type="checkbox" name="kh_ig_aktif" value="1" <?php checked( '1', get_option( 'kh_ig_aktif', '0' ) ); ?>>
								Aktifkan kotak &ldquo;tempel link postingan Instagram&rdquo; di halaman <em>Input dari Infografis</em></label>
						</p>
						<p class="description" style="max-width:78ch">
							<strong>Baca dulu:</strong> robots.txt dan Ketentuan Meta menyatakan pengambilan data dari Instagram
							secara otomatis hanya boleh dengan izin tertulis dari pemilik konten. Nyalakan saklar ini
							<b>hanya kalau kamu punya izin</b> (mis. akun media mitra atau postingan milikmu sendiri).
							Kalau tidak ada izin, jalur yang aman: buka postingannya di browser, simpan gambarnya,
							lalu unggah lewat kotak <em>Unggah gambar</em> — hasil AI-nya sama.
							Saklar ini bisa dimatikan kapan saja dan kotaknya langsung hilang.
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

		<?php
		$msg = isset( $_GET['kh_tg_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['kh_tg_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $msg ) :
			?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<?php if ( kh_tg_token() ) : ?>
			<h2>Bantuan Telegram</h2>
			<p style="max-width:70ch">
				Kirim pesan apa saja (<code>/start</code>) ke bot kamu, lalu klik <em>Cari Chat ID</em>.
				Setelah kolom Chat ID terisi &amp; disimpan, klik <em>Kirim tes</em>.
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<?php wp_nonce_field( 'kh_tg_find' ); ?>
				<input type="hidden" name="action" value="kh_tg_find">
				<button class="button">Cari Chat ID</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<?php wp_nonce_field( 'kh_tg_test' ); ?>
				<input type="hidden" name="action" value="kh_tg_test">
				<button class="button button-primary">Kirim tes</button>
			</form>
			<p class="description">
				Status: <?php echo kh_tg_aktif() ? '<strong style="color:#1a7f37">aktif</strong> — notifikasi laporan baru dikirim ke Telegram' : 'token tersimpan, <strong>Chat ID belum diisi</strong>'; ?>.
			</p>
		<?php endif; ?>

		<?php
		$ai_msg = isset( $_GET['kh_ai_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['kh_ai_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $ai_msg ) :
			?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $ai_msg ); ?></p></div>
		<?php endif; ?>

		<?php if ( kh_ai_key() ) : ?>
			<h2>Uji AI ekstraksi</h2>
			<p style="max-width:70ch">
				Contoh paragraf berita akan diekstrak menjadi data kendaraan (plat, model, tahun, warna, lokasi, waktu, unit polisi).
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'kh_ai_test' ); ?>
				<input type="hidden" name="action" value="kh_ai_test">
				<button class="button">Uji ekstraksi AI</button>
				<span class="description" style="margin-left:8px">
					Model: <code><?php echo esc_html( kh_ai_model() ); ?></code> ·
					Status: <?php echo kh_ai_enabled() ? '<strong style="color:#1a7f37">aktif</strong>' : 'nonaktif'; ?>
				</span>
			</form>
		<?php endif; ?>
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

	/*
	 * Izin opsional untuk meneruskan DATA KENDARAAN (tanpa identitas pelapor) ke redaksi
	 * media. Disimpan dengan waktunya supaya jelas kapan izin diberikan; kosong = tidak ada
	 * izin. Admin tidak boleh meneruskan tanpa nilai ini.
	 */
	if ( (int) $req->get_param( 'izin_media' ) ) {
		update_post_meta( $post_id, 'kh_izin_media', current_time( 'mysql' ) );
	}

	if ( $jenis ) {
		wp_set_object_terms( $post_id, $jenis, 'kendaraan_jenis' );
	}
	if ( $wilayah ) {
		wp_set_object_terms( $post_id, $wilayah, 'kendaraan_wilayah' );
	}
	wp_set_object_terms( $post_id, 'Hilang', 'kendaraan_status' );

	/*
	 * Slug berbasis plat HARUS dibuat sekarang, bukan menunggu admin menyetujui.
	 * wp_insert_post() di atas berjalan sebelum meta ditulis (jadi hook save_post
	 * melihat kh_plat kosong), dan tanpa panggilan ini laporan pending memakai slug
	 * dari judul ("hilang-honda-beat-2014-di-veteran-gresik"). Tautan yang dikirim
	 * ke Telegram jadi mati: slug itu berubah saat laporan disetujui → 404.
	 */
	kh_pakai_slug_plat( $post_id );

	/* foto opsional: kalau ada dan valid, jadikan gambar utama laporan */
	$foto_id = absint( $req->get_param( 'foto_id' ) );
	$has_foto = $foto_id ? kh_attach_foto( $post_id, $foto_id, $email ) : false;

	set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

	kh_notify_new_report( $post_id, $name, $email );
	do_action( 'kh_new_report', $post_id, $name, $email );

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

/* ------------------------------------------------------------------
 * Notifikasi email: laporan baru (ke admin + tanda terima ke pelapor)
 * ------------------------------------------------------------------ */

function kh_notif_email() {
	$to = get_option( 'kh_notif_email' );
	return ( $to && is_email( $to ) ) ? $to : get_option( 'admin_email' );
}

function kh_notify_new_report( $post_id, $name, $email ) {
	$plat   = get_post_meta( $post_id, 'kh_plat', true );
	$merk   = trim( get_post_meta( $post_id, 'kh_merk', true ) . ' ' . get_post_meta( $post_id, 'kh_model', true ) );
	$tahun  = get_post_meta( $post_id, 'kh_tahun', true );
	$warna  = get_post_meta( $post_id, 'kh_warna', true );
	$lokasi = get_post_meta( $post_id, 'kh_lokasi', true );
	$tgl    = kh_normalize_tanggal( get_post_meta( $post_id, 'kh_tanggal', true ) );
	$jam    = get_post_meta( $post_id, 'kh_jam', true );
	$lat    = get_post_meta( $post_id, 'kh_lat', true );
	$lng    = get_post_meta( $post_id, 'kh_lng', true );
	$sttlp  = get_post_meta( $post_id, 'kh_sttlp', true );
	$ciri   = get_post_meta( $post_id, 'kh_ciri', true );

	$edit  = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	$antre = admin_url( 'edit.php?post_type=kendaraan&post_status=pending' );
	$peta  = ( $lat && $lng ) ? 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng ) : '';

	$lines = array(
		'Ada laporan kehilangan baru yang menunggu verifikasi.',
		'',
		'Plat      : ' . $plat,
		'Kendaraan : ' . trim( $merk . ( $tahun ? ' ' . $tahun : '' ) ) . ( $warna ? ' · ' . $warna : '' ),
		'Lokasi    : ' . $lokasi,
		'Waktu     : ' . trim( kh_tanggal_teks( $tgl ) . ( $jam ? ' ' . $jam . ' WIB' : '' ) ),
	);
	if ( $peta ) {
		$lines[] = 'Koordinat : ' . $lat . ', ' . $lng . '  (' . $peta . ')';
	}
	$lines[] = 'Pelapor   : ' . trim( $name . ( $email ? ' <' . $email . '>' : '' ) );
	$lines[] = 'STTLP     : ' . ( $sttlp ? $sttlp : '— belum diisi' );
	$lines[] = 'Foto      : ' . ( has_post_thumbnail( $post_id ) ? get_permalink( $post_id ) : 'tidak ada' );
	if ( $ciri ) {
		$lines[] = 'Catatan   : ' . $ciri;
	}
	$lines[] = '';
	$lines[] = 'Verifikasi / edit : ' . $edit;
	$lines[] = 'Semua pending     : ' . $antre;

	wp_mail(
		kh_notif_email(),
		'[Peta Kendaraan] Laporan baru: ' . $plat . ' — ' . $lokasi,
		implode( "\n", $lines )
	);

	if ( $email && is_email( $email ) ) {
		$body = array(
			'Terima kasih, laporan kamu sudah kami terima.',
			'',
			'Plat      : ' . $plat,
			'Kendaraan : ' . trim( $merk . ( $tahun ? ' ' . $tahun : '' ) ),
			'Lokasi    : ' . $lokasi,
			'Waktu     : ' . trim( $tgl . ( $jam ? ' ' . $jam . ' WIB' : '' ) ),
			'',
			'Laporan akan diperiksa admin dulu sebelum tampil di peta.',
			'Begitu tayang, tautannya bisa kamu bagikan ke WhatsApp — biasanya paling cepat menyebar lewat grup lingkungan.',
			'',
			'PENTING: kalau ada orang mengaku menemukan kendaraanmu dan meminta uang/biaya di muka,',
			'itu modus penipuan. Serah terima sebaiknya dilakukan di kantor polisi.',
			'',
			get_bloginfo( 'name' ) . ' — ' . home_url( '/' ),
		);
		wp_mail(
			$email,
			'[Peta Kendaraan Hilang] Laporan kamu diterima — ' . $plat,
			implode( "\n", $body )
		);
	}
}

/* ------------------------------------------------------------------
 * REST: "Saya menemukan kendaraan ini"
 * Disimpan privat (tidak tampil di publik), diteruskan ke admin + pelapor.
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route(
		'kh/v1',
		'/temuan',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'kh_rest_temuan',
		)
	);
} );

/**
 * Kontak penemu harus bisa ditindaklanjuti: nomor WA Indonesia atau email.
 *
 * @return string 'wa' | 'email' | '' (tidak valid)
 */
function kh_jenis_kontak( $kontak ) {
	$s = trim( (string) $kontak );
	if ( '' === $s ) {
		return '';
	}
	if ( is_email( $s ) ) {
		return 'email';
	}
	$angka = preg_replace( '/[^0-9+]/', '', $s );
	/* 08xx…, 628xx…, +628xx… — nomor Indonesia yang masuk akal */
	if ( preg_match( '/^(?:\+?62|0)8[1-9][0-9]{6,11}$/', $angka ) ) {
		return 'wa';
	}
	return '';
}

function kh_rest_temuan( WP_REST_Request $req ) {
	$id      = absint( $req->get_param( 'id' ) );
	$lokasi  = sanitize_text_field( (string) $req->get_param( 'lokasi' ) );
	$catatan = sanitize_textarea_field( (string) $req->get_param( 'catatan' ) );
	$kontak  = sanitize_text_field( (string) $req->get_param( 'kontak' ) );
	$cred    = (string) $req->get_param( 'credential' );
	$jebakan = trim( (string) $req->get_param( 'web' ) );

	/* Honeypot: kolom tersembunyi yang hanya diisi bot. Diterima diam-diam, tidak disimpan. */
	if ( '' !== $jebakan ) {
		return new WP_REST_Response( array( 'ok' => true, 'message' => 'Terima kasih.' ), 201 );
	}

	if ( ! $id || 'kendaraan' !== get_post_type( $id ) ) {
		return new WP_Error( 'kh_notfound', 'Data tidak ditemukan.', array( 'status' => 404 ) );
	}
	if ( mb_strlen( trim( $lokasi ) ) < 3 ) {
		return new WP_Error( 'kh_lokasi', 'Tulis di mana kendaraan terlihat (minimal 3 huruf).', array( 'status' => 400 ) );
	}

	/*
	 * Identitas: login WordPress, login Google, atau kontak yang formatnya valid.
	 * Tanpa salah satu dari itu, info penemuan tidak bisa ditindaklanjuti sama sekali
	 * (admin tidak punya siapa pun untuk dihubungi) — jadi ditolak di sini.
	 */
	$email        = '';
	$nama         = '';
	$terverifikasi = 0;

	if ( is_user_logged_in() ) {
		$u             = wp_get_current_user();
		$email         = $u->user_email;
		$nama          = $u->display_name;
		$terverifikasi = is_email( $email ) ? 1 : 0;
	} elseif ( '' !== $cred ) {
		$client_id = get_option( 'kh_google_client_id', '' );
		if ( ! $client_id ) {
			return new WP_Error( 'kh_no_client', 'Verifikasi Google belum siap di situs ini.', array( 'status' => 503 ) );
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
		$verified_email = isset( $data['email_verified'] ) ? filter_var( $data['email_verified'], FILTER_VALIDATE_BOOLEAN ) : false;
		if ( empty( $data['email'] ) || ! $verified_email ) {
			return new WP_Error( 'kh_email', 'Email Google belum terverifikasi.', array( 'status' => 403 ) );
		}
		$email         = sanitize_email( $data['email'] );
		$nama          = sanitize_text_field( $data['name'] ?? '' );
		$terverifikasi = 1;
	}

	$jenis_kontak = kh_jenis_kontak( $kontak );
	if ( '' !== $kontak && '' === $jenis_kontak ) {
		return new WP_Error( 'kh_kontak_format', 'Nomor WA atau email tidak valid. Contoh: 081234567890 atau nama@email.com.', array( 'status' => 400 ) );
	}
	if ( ! $terverifikasi && '' === $jenis_kontak ) {
		return new WP_Error(
			'kh_kontak',
			'Isi nomor WA atau email kamu (atau masuk dengan Google) supaya admin bisa menghubungi. Tanpa kontak, info tidak bisa ditindaklanjuti.',
			array( 'status' => 400 )
		);
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	$key = 'kh_temuan_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 3 ) {
		return new WP_Error( 'kh_rate', 'Terlalu banyak kiriman dari jaringan ini hari ini.', array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, DAY_IN_SECONDS );

	$list = get_post_meta( $id, 'kh_temuan', true );
	if ( ! is_array( $list ) ) {
		$list = array();
	}

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	$list[] = array(
		'waktu'         => current_time( 'mysql' ),
		'lokasi'        => $lokasi,
		'catatan'       => $catatan,
		'kontak'        => $kontak,
		'jenis_kontak'  => $jenis_kontak,
		'email'         => $email,
		'nama'          => $nama,
		'terverifikasi' => $terverifikasi,
		'ip'            => $ip,
		'ua'            => mb_substr( $ua, 0, 160 ),
	);
	$list = array_slice( $list, -20 );

	update_post_meta( $id, 'kh_temuan', $list );

	$status_identitas = $terverifikasi
		? 'terverifikasi Google' . ( $email ? ' (' . $email . ')' : '' )
		: 'belum terverifikasi — hanya kontak yang diisi sendiri';

	$info_temuan = trim( $lokasi . ( $catatan ? ' — ' . $catatan : '' ) . ( $kontak ? ' (kontak: ' . $kontak . ')' : '' ) );
	do_action( 'kh_temuan', $id, $info_temuan, $status_identitas );

	$plat  = get_post_meta( $id, 'kh_plat', true );
	$edit  = admin_url( 'post.php?post=' . $id . '&action=edit' );
	$body  = array(
		'Ada informasi penemuan untuk: ' . get_the_title( $id ) . ' (' . $plat . ')',
		'',
		'Terlihat di : ' . $lokasi,
		'Keterangan  : ' . ( $catatan ? $catatan : '—' ),
		'Kontak      : ' . ( $kontak ? $kontak : '— tidak diisi' ),
		'Identitas   : ' . $status_identitas,
		'IP / UA     : ' . $ip . ' / ' . mb_substr( $ua, 0, 80 ),
		'Waktu       : ' . current_time( 'mysql' ),
		'',
		'Edit data : ' . $edit,
		'',
		$terverifikasi
			? '(Identitas terverifikasi lewat Google, info sudah diteruskan ke pelapor.)'
			: '(Identitas BELUM terverifikasi — hubungi kontak di atas dulu. Info ini TIDAK diteruskan ke pelapor.)',
	);
	wp_mail( kh_notif_email(), '[Peta Kendaraan] Info penemuan: ' . $plat, implode( "\n", $body ) );

	/*
	 * Hanya info dari identitas terverifikasi yang diteruskan otomatis ke pelapor.
	 *
	 * Alasannya: kontak yang diisi sendiri belum tentu benar. Kalau semua kiriman
	 * diteruskan, satu info iseng "ketemu di X" bisa membuat orang yang sedang
	 * kehilangan motor berhari-hari mengejar angin. Untuk yang belum terverifikasi,
	 * admin yang memutuskan setelah menghubungi kontaknya.
	 */
	$pelapor = get_post_meta( $id, 'kh_pelapor_email', true );
	if ( $terverifikasi && $pelapor && is_email( $pelapor ) ) {
		wp_mail(
			$pelapor,
			'[Peta Kendaraan Hilang] Ada info penemuan untuk ' . $plat,
			implode(
				"\n",
				array(
					'Kabar baik — ada orang yang melaporkan melihat kendaraanmu.',
					'',
					'Terlihat di : ' . $lokasi,
					'Keterangan  : ' . ( $catatan ? $catatan : '—' ),
					'',
					'Kontak pelapor tidak kami berikan langsung demi keamanan kedua pihak.',
					'Admin akan meneruskan dan memverifikasi. Untuk serah terima, lakukan di kantor polisi.',
					'',
					'JANGAN mentransfer uang ke siapa pun yang mengaku menemukan kendaraan ini.',
					'',
					get_permalink( $id ),
				)
			)
		);
	}

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'message' => $terverifikasi
				? 'Terima kasih! Info kamu diteruskan ke admin dan pelapor. Untuk serah terima, lakukan di kantor polisi — jangan minta atau memberi uang.'
				: 'Terima kasih! Info kamu masuk ke admin. Kami hubungi kontakmu dulu untuk memastikan, baru diteruskan ke pelapor. Untuk serah terima, lakukan di kantor polisi — jangan minta atau memberi uang.',
		),
		201
	);
}

/**
 * Notifikasi admin untuk info penemuan.
 */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kendaraan' !== $screen->id ) {
		return;
	}
	$found = new WP_Query(
		array(
			'post_type'      => 'kendaraan',
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'kh_temuan',
					'compare' => 'EXISTS',
				),
			),
		)
	);
	if ( ! $found->posts ) {
		return;
	}
	echo '<div class="notice notice-info"><p><strong>Ada info penemuan masuk</strong> — cek: ';
	$links = array();
	foreach ( $found->posts as $pid ) {
		$list    = get_post_meta( $pid, 'kh_temuan', true );
		$links[] = '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( get_the_title( $pid ) )
			. ' (' . ( is_array( $list ) ? count( $list ) : 0 ) . ')</a>';
	}
	echo wp_kses_post( implode( ' · ', $links ) );
	echo '</p></div>';
} );
