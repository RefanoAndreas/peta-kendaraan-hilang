<?php
/**
 * Notifikasi Telegram (opsional).
 *
 * Hanya pesan keluar (Bot API sendMessage) — tanpa webhook, tanpa polling,
 * jadi tidak menambah beban ke situs. Token disimpan di opsi WordPress dan
 * tidak pernah dikirim ke browser pengunjung.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token bot (validasi bentuk: 123456789:AA...).
 */
function kh_tg_token() {
	$t = trim( (string) get_option( 'kh_tg_token', '' ) );
	return preg_match( '/^\d{6,}:[A-Za-z0-9_-]{20,}$/', $t ) ? $t : '';
}

/**
 * Chat ID tujuan.
 */
function kh_tg_chat() {
	return trim( (string) get_option( 'kh_tg_chat', '' ) );
}

/**
 * Notifikasi Telegram siap dipakai?
 */
function kh_tg_aktif() {
	return kh_tg_token() && kh_tg_chat();
}

/**
 * Panggil Bot API.
 */
function kh_tg_api( $method, $args = array(), $timeout = 15 ) {
	$token = kh_tg_token();
	if ( ! $token ) {
		return new WP_Error( 'kh_tg', 'Token bot belum diisi.' );
	}

	$resp = wp_remote_post(
		'https://api.telegram.org/bot' . $token . '/' . $method,
		array(
			'timeout' => $timeout,
			'body'    => $args,
		)
	);

	if ( is_wp_error( $resp ) ) {
		return $resp;
	}

	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
		$msg = is_array( $body ) && isset( $body['description'] ) ? $body['description'] : 'respons tidak dikenali';
		return new WP_Error( 'kh_tg', 'Telegram: ' . $msg );
	}

	return $body['result'];
}

/**
 * Kirim pesan teks (HTML ringan).
 */
function kh_tg_send( $text, $chat = '' ) {
	$chat = $chat ? $chat : kh_tg_chat();
	if ( ! $chat ) {
		return new WP_Error( 'kh_tg', 'Chat ID belum diisi.' );
	}

	return kh_tg_api(
		'sendMessage',
		array(
			'chat_id'                  => $chat,
			'text'                     => $text,
			'parse_mode'               => 'HTML',
			'disable_web_page_preview' => 'true',
		)
	);
}

/**
 * Ringkasan satu laporan untuk pesan Telegram.
 */
function kh_tg_kartu( $post_id, $judul = 'Laporan baru' ) {
	$plat   = get_post_meta( $post_id, 'kh_plat', true );
	$merk   = trim( get_post_meta( $post_id, 'kh_merk', true ) . ' ' . get_post_meta( $post_id, 'kh_model', true ) );
	$tahun  = get_post_meta( $post_id, 'kh_tahun', true );
	$warna  = get_post_meta( $post_id, 'kh_warna', true );
	$lokasi = get_post_meta( $post_id, 'kh_lokasi', true );
	$tgl    = kh_normalize_tanggal( get_post_meta( $post_id, 'kh_tanggal', true ) );
	$jam    = get_post_meta( $post_id, 'kh_jam', true );
	$polsek = get_post_meta( $post_id, 'kh_polsek', true );
	$sttlp  = get_post_meta( $post_id, 'kh_sttlp', true );

	$e = function ( $s ) {
		return esc_html( (string) $s );
	};

	$baris = array(
		'🚨 <b>' . $e( $judul ) . '</b>',
		'<b>' . $e( $plat ) . '</b> — ' . $e( $merk ) . ( $tahun ? ' ' . $e( $tahun ) : '' ) . ( $warna ? ' · ' . $e( $warna ) : '' ),
	);
	if ( $lokasi ) {
		$baris[] = '📍 ' . $e( $lokasi );
	}
	$waktu = trim( kh_tanggal_teks( $tgl ) . ( $jam ? ' ' . $jam . ' WIB' : '' ) );
	if ( $waktu ) {
		$baris[] = '🕒 ' . $e( $waktu );
	}
	if ( $polsek || $sttlp ) {
		$baris[] = '👮 ' . $e( $polsek ? $polsek : 'STTLP: ' . $sttlp );
	}

	$baris[] = '';

	/*
	 * Tautan publik hanya kalau laporan sudah tayang.
	 *
	 * Dulu baris ini selalu menautkan get_permalink(), termasuk untuk laporan
	 * yang masih pending — dan halaman pending memang tidak bisa dibuka
	 * pengunjung (404). Tautan mati seperti itu juga muncul saat slug masih
	 * berbasis judul lalu berubah setelah disetujui.
	 */
	$tautan = '<a href="' . esc_url( get_edit_post_link( $post_id, '' ) ) . '">✏️ Edit di admin</a>';
	if ( 'publish' === get_post_status( $post_id ) ) {
		$tautan .= ' · <a href="' . esc_url( get_permalink( $post_id ) ) . '">🔗 Lihat halaman</a>';
	} else {
		$tautan .= "\n⏳ Belum tayang — halaman publik baru ada setelah laporan disetujui.";
	}
	$baris[] = $tautan;

	return implode( "\n", $baris );
}

/**
 * Kabar saat laporan warga disetujui → kirim tautan yang sudah pasti benar.
 *
 * Terbatas pada transisi pending → publish, supaya impor massal tidak
 * membanjiri chat dengan puluhan pesan.
 */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'kendaraan' !== $post->post_type || 'publish' !== $new || 'pending' !== $old ) {
		return;
	}
	if ( ! kh_tg_aktif() ) {
		return;
	}

	kh_pakai_slug_plat( $post->ID );

	$plat = (string) get_post_meta( $post->ID, 'kh_plat', true );
	$teks = "✅ <b>Laporan tayang</b>\n"
		. '<b>' . esc_html( $plat ? $plat : get_the_title( $post ) ) . '</b>'
		. "\n🔗 " . esc_url( get_permalink( $post ) );

	$r = kh_tg_send( $teks );
	if ( is_wp_error( $r ) ) {
		error_log( '[kh-tg] ' . $r->get_error_message() ); // phpcs:ignore
	}
}, 10, 3 );

/**
 * Laporan baru masuk → kirim ke Telegram.
 */
add_action( 'kh_new_report', function ( $post_id, $nama = '', $email = '' ) {
	if ( ! kh_tg_aktif() ) {
		return;
	}

	$teks = kh_tg_kartu( $post_id, 'Laporan baru — menunggu verifikasi' );
	if ( $nama ) {
		$teks .= "\n👤 Pelapor: " . esc_html( $nama );
	}

	$r = kh_tg_send( $teks );
	if ( is_wp_error( $r ) ) {
		error_log( '[kh-tg] ' . $r->get_error_message() ); // phpcs:ignore
	}
}, 10, 3 );

/**
 * Info penemuan dari pengunjung → kirim ke Telegram.
 */
add_action( 'kh_temuan', function ( $post_id, $info = '', $identitas = '' ) {
	if ( ! kh_tg_aktif() ) {
		return;
	}

	$teks = kh_tg_kartu( $post_id, 'Ada yang mengaku menemukan kendaraan' );
	if ( $info ) {
		$teks .= "\n💬 " . esc_html( wp_trim_words( $info, 40, '…' ) );
	}
	if ( $identitas ) {
		$teks .= "\n🪪 " . esc_html( $identitas );
	}

	$r = kh_tg_send( $teks );
	if ( is_wp_error( $r ) ) {
		error_log( '[kh-tg] ' . $r->get_error_message() ); // phpcs:ignore
	}
}, 10, 2 );

/* ------------------------------------------------------------------
 * Alat bantu di halaman Pengaturan
 * ------------------------------------------------------------------ */

/**
 * Tombol: cari chat ID dari pesan terakhir yang masuk ke bot.
 */
add_action( 'admin_post_kh_tg_find', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Akses ditolak.' );
	}
	check_admin_referer( 'kh_tg_find' );

	$res = kh_tg_api( 'getUpdates', array( 'limit' => 20, 'timeout' => 0 ) );
	if ( is_wp_error( $res ) ) {
		wp_safe_redirect( add_query_arg( 'kh_tg_msg', rawurlencode( 'Gagal: ' . $res->get_error_message() ), admin_url( 'edit.php?post_type=kendaraan&page=kh-settings' ) ) );
		exit;
	}

	$ketemu = array();
	foreach ( (array) $res as $u ) {
		$msg = isset( $u['message'] ) ? $u['message'] : ( isset( $u['channel_post'] ) ? $u['channel_post'] : null );
		if ( ! $msg || empty( $msg['chat']['id'] ) ) {
			continue;
		}
		$nama = isset( $msg['chat']['title'] ) ? $msg['chat']['title'] : ( isset( $msg['chat']['username'] ) ? '@' . $msg['chat']['username'] : ( isset( $msg['chat']['first_name'] ) ? $msg['chat']['first_name'] : 'tanpa nama' ) );
		$ketemu[ (string) $msg['chat']['id'] ] = $nama;
	}

	if ( ! $ketemu ) {
		$pesan = 'Belum ada pesan. Buka bot di Telegram, tekan Start / kirim pesan apa saja, lalu coba lagi.';
	} else {
		$potongan = array();
		foreach ( $ketemu as $id => $nama ) {
			$potongan[] = $id . ' (' . $nama . ')';
		}
		$pesan = 'Chat ditemukan: ' . implode( ', ', $potongan ) . ' — salin ID-nya ke kolom Chat ID.';
	}

	wp_safe_redirect( add_query_arg( 'kh_tg_msg', rawurlencode( $pesan ), admin_url( 'edit.php?post_type=kendaraan&page=kh-settings' ) ) );
	exit;
} );

/**
 * Tombol: kirim pesan uji.
 */
add_action( 'admin_post_kh_tg_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Akses ditolak.' );
	}
	check_admin_referer( 'kh_tg_test' );

	$teks = "✅ <b>Tes notifikasi</b>\nBot Telegram untuk Peta Kendaraan Hilang sudah tersambung.\nSitus: " . esc_html( home_url( '/' ) );
	$res  = kh_tg_send( $teks );

	$pesan = is_wp_error( $res ) ? 'Gagal: ' . $res->get_error_message() : 'Terkirim. Cek chat Telegram kamu.';

	wp_safe_redirect( add_query_arg( 'kh_tg_msg', rawurlencode( $pesan ), admin_url( 'edit.php?post_type=kendaraan&page=kh-settings' ) ) );
	exit;
} );
