<?php
/**
 * Foto kendaraan (OPSIONAL).
 *
 * Alur: form mengirim foto lebih dulu ke /kh/v1/foto (multipart) → dapat attachment ID,
 * lalu ID itu disertakan saat submit laporan. Tanpa foto, laporan tetap bisa dikirim.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifikasi id_token Google → [email, name] atau WP_Error.
 */
function kh_verify_google_credential( $cred ) {
	$client_id = get_option( 'kh_google_client_id', '' );

	if ( ! $cred ) {
		return new WP_Error( 'kh_auth', 'Silakan masuk dengan Google terlebih dahulu.', array( 'status' => 401 ) );
	}
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

	return array(
		'email' => sanitize_email( $data['email'] ),
		'name'  => sanitize_text_field( $data['name'] ?? '' ),
	);
}

/**
 * REST: unggah satu foto (opsional).
 */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'kh/v1',
		'/foto',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'kh_rest_upload_foto',
		)
	);
} );

function kh_rest_upload_foto( WP_REST_Request $req ) {
	/* 1. identitas */
	$email = '';
	if ( is_user_logged_in() ) {
		$email = wp_get_current_user()->user_email;
	} else {
		$auth = kh_verify_google_credential( (string) $req->get_param( 'credential' ) );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$email = $auth['email'];
	}

	/* 2. rate limit: 10 foto / jam / email */
	$rl = 'kh_foto_rl_' . md5( strtolower( $email ) );
	$n  = (int) get_transient( $rl );
	if ( $n >= 10 ) {
		return new WP_Error( 'kh_rate', 'Terlalu banyak unggahan. Coba lagi nanti.', array( 'status' => 429 ) );
	}

	/* 3. file */
	$files = $req->get_file_params();
	if ( empty( $files['foto'] ) || ! isset( $files['foto']['tmp_name'] ) ) {
		return new WP_Error( 'kh_no_file', 'Tidak ada foto yang dikirim.', array( 'status' => 400 ) );
	}

	$file = $files['foto'];

	if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'kh_upload', 'Foto gagal diunggah (kode ' . (int) $file['error'] . ').', array( 'status' => 400 ) );
	}
	if ( (int) $file['size'] > 15 * 1024 * 1024 ) {
		return new WP_Error( 'kh_size', 'Ukuran foto maksimal 15 MB.', array( 'status' => 413 ) );
	}

	/* HEIC/HEIF: getimagesize() gagal total → beri pesan yang jelas lebih dulu. */
	$declared = strtolower( (string) ( $file['type'] ?? '' ) );
	$nama     = (string) ( $file['name'] ?? '' );
	if ( false !== strpos( $declared, 'hei' ) || preg_match( '/\.hei[cf]$/i', $nama ) ) {
		return new WP_Error(
			'kh_heic',
			'Foto HEIC belum didukung. Di iPhone: buka foto → Bagikan → Simpan sebagai JPEG (atau ubah Kamera → Format → Paling Kompatibel).',
			array( 'status' => 400 )
		);
	}

	$info = @getimagesize( $file['tmp_name'] ); // phpcs:ignore
	if ( ! $info || empty( $info['mime'] ) ) {
		return new WP_Error( 'kh_mime', 'File ini bukan gambar yang valid.', array( 'status' => 400 ) );
	}

	$allowed = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);
	if ( ! isset( $allowed[ $info['mime'] ] ) ) {
		$hint = ( false !== strpos( (string) $info['mime'], 'hei' ) )
			? 'Foto HEIC belum didukung. Simpan dulu sebagai JPEG dari aplikasi Galeri/Foto, lalu unggah lagi.'
			: 'Format foto harus JPG, PNG, atau WEBP.';
		return new WP_Error( 'kh_mime', $hint, array( 'status' => 400 ) );
	}
	if ( (int) $info[0] < 200 || (int) $info[1] < 200 ) {
		return new WP_Error( 'kh_small', 'Foto terlalu kecil (minimal 200×200 piksel).', array( 'status' => 400 ) );
	}

	/* 4. simpan */
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$up = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
			),
		)
	);

	if ( ! empty( $up['error'] ) ) {
		return new WP_Error( 'kh_upload', $up['error'], array( 'status' => 400 ) );
	}

	$title = 'Foto laporan ' . gmdate( 'Y-m-d H:i' );

	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => $up['type'],
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => get_current_user_id(),
		),
		$up['file']
	);

	if ( is_wp_error( $attach_id ) ) {
		return new WP_Error( 'kh_attach', 'Foto tersimpan tapi gagal didaftarkan.', array( 'status' => 500 ) );
	}

	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $up['file'] ) );
	update_post_meta( $attach_id, 'kh_uploader_email', $email );

	set_transient( $rl, $n + 1, HOUR_IN_SECONDS );

	return new WP_REST_Response(
		array(
			'ok'  => true,
			'id'  => (int) $attach_id,
			'url' => wp_get_attachment_image_url( $attach_id, 'large' ),
		),
		201
	);
}

/**
 * Lampirkan foto ke laporan (dipanggil saat submit).
 * Mengembalikan true kalau terpasang.
 */
function kh_attach_foto( $post_id, $foto_id, $email ) {
	$foto_id = absint( $foto_id );
	if ( ! $foto_id ) {
		return false;
	}

	$att = get_post( $foto_id );
	if ( ! $att || 'attachment' !== $att->post_type ) {
		return false;
	}
	if ( 0 !== strpos( (string) get_post_mime_type( $foto_id ), 'image/' ) ) {
		return false;
	}

	$owner = get_post_meta( $foto_id, 'kh_uploader_email', true );
	if ( $owner && $email && strtolower( $owner ) !== strtolower( $email ) ) {
		return false; // bukan foto milik akun ini
	}
	if ( $att->post_parent && (int) $att->post_parent !== (int) $post_id ) {
		return false; // sudah dipakai laporan lain
	}

	wp_update_post(
		array(
			'ID'          => $foto_id,
			'post_parent' => $post_id,
		)
	);
	set_post_thumbnail( $post_id, $foto_id );

	return true;
}

/**
 * Bersihkan foto yang diunggah tapi laporannya tidak pernah dikirim.
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'kh_cleanup_photos' ) ) {
		wp_schedule_event( time() + 7200, 'daily', 'kh_cleanup_photos' );
	}
} );

add_action( 'kh_cleanup_photos', function () {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'column' => 'post_date',
					'before' => '24 hours ago',
				),
			),
			'meta_query'     => array(
				array(
					'key'     => 'kh_uploader_email',
					'compare' => 'EXISTS',
				),
			),
			'post_parent'    => 0,
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_attachment( $id, true );
	}
} );
