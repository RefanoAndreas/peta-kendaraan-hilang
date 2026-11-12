<?php
/**
 * Importer laporan dari URL artikel berita.
 * Admin tempel URL -> parser ekstrak plat/model/lokasi -> draft.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'kh_import_menu' );
function kh_import_menu() {
	add_submenu_page(
		'edit.php?post_type=kendaraan',
		'Import dari URL',
		'Import dari URL',
		'edit_posts',
		'kh-import',
		'kh_import_page'
	);
}

function kh_import_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Akses ditolak.' );
	}
	$notice = get_transient( 'kh_import_notice' );
	if ( $notice ) {
		delete_transient( 'kh_import_notice' );
	}
	?>
	<div class="wrap">
		<h1>Import Laporan dari URL</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $notice ); ?></p></div>
		<?php endif; ?>
		<p>Tempel URL artikel (satu per baris). Sistem mengambil halaman sekali, membaca nomor polisi &amp; detail kendaraan,
			lalu membuat <strong>draft</strong> untuk kamu periksa sebelum publish.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="kh_import" />
			<?php wp_nonce_field( 'kh_import_urls', 'kh_import_nonce' ); ?>
			<textarea name="kh_urls" rows="6" class="large-text code" placeholder="https://www.suarasurabaya.net/kelanakota/2026/..."></textarea>
			<p>
				<label><input type="checkbox" name="kh_geocode" value="1" checked> Cari koordinat otomatis (Nominatim)</label>
			</p>
			<?php submit_button( 'Import sekarang' ); ?>
		</form>

		<hr />
		<h2>Contoh hasil pembacaan</h2>
		<p class="description">Parser mengenali pola seperti: <em>"motor Vario bernomor polisi KH 4430 TW. Motor keluaran 2015 berwarna hitam tersebut dilaporkan hilang di sebuah kos kawasan Gunungsari, Surabaya."</em></p>
	</div>
	<?php
}

add_action( 'admin_post_kh_import', 'kh_handle_import' );
function kh_handle_import() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Akses ditolak.' );
	}
	check_admin_referer( 'kh_import_urls', 'kh_import_nonce' );

	$raw  = isset( $_POST['kh_urls'] ) ? (string) wp_unslash( $_POST['kh_urls'] ) : '';
	$geo  = ! empty( $_POST['kh_geocode'] );
	$urls = preg_split( '/[\r\n]+/', $raw );
	$urls = array_values( array_unique( array_filter( array_map( 'trim', $urls ) ) ) );

	$created = 0;
	$skipped = 0;
	$errors  = array();
	$ai_ok   = 0;
	$catatan = array();

	foreach ( array_slice( $urls, 0, 20 ) as $url ) {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$errors[] = esc_html( $url ) . ' — bukan URL valid';
			continue;
		}

		$res = kh_import_from_url( $url, $geo );
		if ( is_wp_error( $res ) ) {
			$errors[] = esc_html( $url ) . ' — ' . esc_html( $res->get_error_message() );
			continue;
		}
		$created += $res['created'];
		$skipped += $res['skipped'];
		if ( 'ai' === $res['engine'] ) {
			$ai_ok++;
		}
		if ( ! empty( $res['note'] ) ) {
			$catatan[] = $res['note'];
		}
	}

	$msg = sprintf( '<strong>%d draft</strong> dibuat, %d dilewati (sudah ada).', $created, $skipped );
	$msg .= $ai_ok
		? sprintf( ' Mesin: <strong>AI</strong> untuk %d artikel.', $ai_ok )
		: ' Mesin: parser regex.';
	if ( $catatan ) {
		$msg .= '<br>' . esc_html( implode( '; ', array_unique( $catatan ) ) );
	}
	if ( $errors ) {
		$msg .= '<br>Gagal: ' . implode( '; ', $errors );
	}

	set_transient( 'kh_import_notice', $msg, 120 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=kendaraan&page=kh-import' ) );
	exit;
}

/**
 * Hormati robots.txt: jangan ambil halaman yang dilarang untuk bot umum.
 *
 * Cache per host 12 jam. Kalau robots.txt tidak ada atau tidak terbaca, izinkan
 * (perilaku lama) — supaya importer tidak mati hanya karena situs tidak punya file itu.
 */
function kh_url_boleh_diambil( $url ) {
	$p = wp_parse_url( $url );
	if ( empty( $p['host'] ) ) {
		return true;
	}

	$host      = strtolower( $p['host'] );
	$cache_key = 'kh_robots2_' . md5( $host );
	$simpan    = get_transient( $cache_key );

	if ( false === $simpan ) {
		$rules      = array();
		$tidak_tahu = false;
		$robots_url = ( ! empty( $p['scheme'] ) ? $p['scheme'] : 'https' ) . '://' . $host . '/robots.txt';
		$resp       = wp_remote_get(
			$robots_url,
			array(
				'timeout'    => 10,
				'user-agent' => 'PetaKendaraanHilang/1.0 (+import manual admin)',
			)
		);

		if ( is_wp_error( $resp ) ) {
			$tidak_tahu = true;
		} else {
			$code = (int) wp_remote_retrieve_response_code( $resp );
			if ( 404 === $code || 410 === $code ) {
				$rules = array(); /* tidak ada robots.txt = tidak ada larangan */
			} elseif ( 200 !== $code ) {
				/* 429/403/5xx: kita tidak bisa memastikan aturannya. Jangan asal ambil. */
				$tidak_tahu = true;
			} else {
				$agent = null;
				foreach ( preg_split( '/\r\n|\r|\n/', (string) wp_remote_retrieve_body( $resp ) ) as $line ) {
					$line = trim( preg_replace( '/#.*/', '', $line ) );
					if ( '' === $line ) {
						continue;
					}
					if ( preg_match( '/^user-agent:\s*(.*)$/i', $line, $m ) ) {
						$agent = strtolower( trim( $m[1] ) );
					} elseif ( preg_match( '/^disallow:\s*(.*)$/i', $line, $m ) ) {
						/* hanya aturan untuk bot umum (*) yang kita hormati */
						if ( null === $agent || '*' === $agent ) {
							$jarak = trim( $m[1] );
							if ( '' !== $jarak ) {
								$rules[] = $jarak;
							}
						}
					}
				}
			}
		}

		$simpan = array(
			'rules'       => $rules,
			'tidak_tahu'  => $tidak_tahu,
		);
		/* kegagalan sementara jangan di-cache lama */
		set_transient( $cache_key, $simpan, $tidak_tahu ? 10 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS );
	}

	if ( ! empty( $simpan['tidak_tahu'] ) ) {
		return false;
	}

	$path = ( ! empty( $p['path'] ) ? $p['path'] : '/' ) . ( ! empty( $p['query'] ) ? '?' . $p['query'] : '' );
	foreach ( (array) $simpan['rules'] as $rule ) {
		$rule = rtrim( $rule, '*' );
		if ( '' !== $rule && 0 === strpos( $path, $rule ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Ambil satu artikel dan ubah jadi draft.
 */
function kh_import_from_url( $url, $geocode = true ) {
	if ( ! kh_url_boleh_diambil( $url ) ) {
		return new WP_Error(
			'robots',
			'Halaman ini tidak boleh diambil otomatis: robots.txt situs itu melarang, atau statusnya tidak bisa dipastikan sekarang. Masukkan datanya manual lewat halaman Input dari Infografis.'
		);
	}

	$resp = wp_remote_get(
		$url,
		array(
			'timeout'    => 25,
			'user-agent' => 'PetaKendaraanHilang/1.0 (+import manual admin)',
		)
	);
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	if ( 200 !== (int) $code ) {
		return new WP_Error( 'http', 'HTTP ' . $code );
	}

	$html = wp_remote_retrieve_body( $resp );
	if ( ! $html ) {
		return new WP_Error( 'empty', 'Halaman kosong' );
	}

	$parsed = kh_parse_article( $html, $url );

	/* AI (opsional) — lebih paham kalimat berita daripada pola regex.
	   Kalau gagal, tetap lanjut dengan hasil regex. */
	$ai_note     = '';
	$regex_items = $parsed['items'];

	if ( function_exists( 'kh_ai_enabled' ) && kh_ai_enabled() && ! empty( $parsed['text'] ) ) {
		$ai = kh_ai_extract_kendaraan( $parsed['title'], $parsed['text'], $parsed['tanggal'] );

		if ( is_wp_error( $ai ) ) {
			$ai_note = 'AI gagal (' . $ai->get_error_message() . '), memakai parser biasa.';
		} else {
			/* AI jadi sumber utama; plat yang hanya tertangkap regex ikut ditambahkan
			   supaya tidak ada laporan yang hilang. */
			$sudah = array();
			foreach ( $ai['items'] as $it ) {
				$sudah[] = kh_normalize_plat( $it['plat'] );
			}

			$tambahan = 0;
			foreach ( $regex_items as $ri ) {
				$p = kh_normalize_plat( $ri['plat'] );
				if ( '' !== $p && ! in_array( $p, $sudah, true ) ) {
					$ai['items'][] = $ri;
					$sudah[]       = $p;
					$tambahan++;
				}
			}

			$parsed['items']  = $ai['items'];
			$parsed['engine'] = 'ai';
			if ( ! empty( $ai['tanggal'] ) ) {
				$parsed['tanggal'] = $ai['tanggal'];
			}
			if ( $tambahan ) {
				$ai_note = sprintf( '%d plat hanya terbaca regex dan tetap disertakan.', $tambahan );
			}
		}
	}

	if ( empty( $parsed['items'] ) ) {
		return new WP_Error( 'noplate', 'Tidak ada nomor polisi yang terbaca' . ( $ai_note ? ' — ' . $ai_note : '' ) );
	}

	$created = 0;
	$skipped = 0;

	foreach ( $parsed['items'] as $item ) {
		$plat = kh_normalize_plat( $item['plat'] );

		$dupe = new WP_Query(
			array(
				'post_type'      => 'kendaraan',
				'post_status'    => 'any',
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
			$skipped++;
			continue;
		}

		$lat = '';
		$lng = '';
		if ( $geocode && ! empty( $item['lokasi'] ) ) {
			$geo = kh_geocode_lokasi( $item['lokasi'], $item['wilayah'] );
			if ( $geo ) {
				$lat = $geo[0];
				$lng = $geo[1];
			}
		}

		$judul = trim(
			sprintf(
				'Hilang %s%s di %s',
				trim( $item['merk'] . ' ' . $item['model'] ),
				$item['tahun'] ? ' ' . $item['tahun'] : '',
				$item['lokasi'] ? $item['lokasi'] : $item['wilayah']
			)
		);

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'kendaraan',
				'post_status'  => 'draft',
				'post_title'   => $judul,
				'post_content' => ! empty( $item['ringkasan'] ) ? $item['ringkasan'] : kh_buat_ringkasan( $item, $parsed['tanggal'] ),
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}

		kh_save_meta_values(
			$post_id,
			array(
				'plat'        => $item['plat'],
				'merk'        => $item['merk'],
				'model'       => $item['model'],
				'tahun'       => $item['tahun'],
				'warna'       => $item['warna'],
				'ciri'        => $item['ciri'],
				'tanggal'     => ! empty( $item['tanggal'] ) ? $item['tanggal'] : $parsed['tanggal'],
				'jam'         => $item['jam'],
				'lokasi'      => $item['lokasi'],
				'lat'         => $lat,
				'lng'         => $lng,
				'polsek'      => $item['polsek'],
				'unit'        => ! empty( $item['unit'] ) ? $item['unit'] : '',
				'sumber_nama' => 'Suara Surabaya',
				'sumber_url'  => esc_url_raw( $url ),
			)
		);
		update_post_meta( $post_id, 'kh_verifikasi', 'media' );

		if ( $item['jenis'] ) {
			wp_set_object_terms( $post_id, $item['jenis'], 'kendaraan_jenis' );
		}
		if ( $item['wilayah'] ) {
			wp_set_object_terms( $post_id, $item['wilayah'], 'kendaraan_wilayah' );
		}
		wp_set_object_terms( $post_id, 'Hilang', 'kendaraan_status' );

		$created++;
	}

	return array(
		'created' => $created,
		'skipped' => $skipped,
		'engine'  => isset( $parsed['engine'] ) ? $parsed['engine'] : 'regex',
		'note'    => $ai_note,
	);
}

/**
 * Parser artikel berita.
 */
function kh_parse_article( $html, $url ) {
	$out = array(
		'title'   => '',
		'tanggal' => '',
		'items'   => array(),
		'text'    => '',
		'engine'  => 'regex',
	);

	/* judul */
	if ( preg_match( '#<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)#i', $html, $m ) ) {
		$out['title'] = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
	}

	/* tanggal publikasi */
	if ( preg_match( '#<meta[^>]+property=["\']article:published_time["\'][^>]+content=["\']([^"\']+)#i', $html, $m ) ) {
		$out['tanggal'] = substr( $m[1], 0, 10 );
	} elseif ( preg_match( '#<time[^>]+datetime=["\']([^"\']+)#i', $html, $m ) ) {
		$out['tanggal'] = substr( $m[1], 0, 10 );
	} elseif ( preg_match( '#"datePublished"\s*:\s*"([^"]+)"#i', $html, $m ) ) {
		$out['tanggal'] = substr( $m[1], 0, 10 );
	} else {
		$out['tanggal'] = wp_date( 'Y-m-d' );
	}

	/* ambil area artikel */
	$body = $html;
	if ( preg_match( '#<article[^>]*>(.*?)</article>#is', $html, $m ) ) {
		$body = $m[1];
	}
	$body = preg_replace( '#<(script|style|figure|aside|nav)[^>]*>.*?</\1>#is', ' ', $body );
	$body = preg_replace( '#</p>#i', "</p>\n\n", $body );
	$text = wp_strip_all_tags( $body );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/\s+/', ' ', $text );

	$out['text'] = $text;

	$paras = preg_split( '/\n{2,}/', wp_strip_all_tags( str_replace( '</p>', "\n\n", $body ) ) );
	$paras = array_values( array_filter( array_map( 'trim', $paras ), function ( $p ) { return strlen( $p ) > 40; } ) );

	if ( ! $paras ) {
		$paras = array( $text );
	}

	foreach ( $paras as $i => $para ) {
		$plats = kh_find_plates( $para );
		if ( ! $plats ) {
			continue;
		}
		$ctx = $para;
		if ( isset( $paras[ $i + 1 ] ) ) {
			$ctx .= ' ' . $paras[ $i + 1 ];
		}
		foreach ( $plats as $plat ) {
			$out['items'][] = kh_extract_item( $plat, $para . ' ' . ( isset( $paras[ $i + 1 ] ) ? $paras[ $i + 1 ] : '' ) );
		}
	}

	return $out;
}

function kh_find_plates( $text ) {
	$found = array();
	/* pola plat Indonesia: B 1234 XYZ / L 3183 DAC / W 3626 WT */
	if ( preg_match_all( '/\b([A-Z]{1,2})\s?(\d{1,4})\s?([A-Z]{1,3})\b/', $text, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $match ) {
			$plat = strtoupper( trim( $match[1] . ' ' . $match[2] . ' ' . $match[3] ) );
			if ( ! in_array( $plat, $found, true ) ) {
				$found[] = $plat;
			}
		}
	}
	return $found;
}

function kh_extract_item( $plat, $ctx ) {
	$motors = array( 'Beat', 'Vario', 'Scoopy', 'Supra', 'Mio', 'Jupiter', 'Nmax', 'NMAX', 'Aerox', 'Revo', 'Genio', 'PCX', 'Satria', 'Sonic', 'Vixion', 'X-Ride', 'Blade', 'Shogun', 'Smash', 'Fino', 'Lexi', 'Grand', 'Absolute', 'Vision', 'Stylo', 'Elite', 'Gear', 'Freego' );
	$cars   = array( 'Avanza', 'Xenia', 'Brio', 'Mobilio', 'Jazz', 'Innova', 'Calya', 'Sigra', 'Ayla', 'Agya', 'Terios', 'Rush', 'Ertiga', 'Xpander', 'Triton', 'Hilux', 'Pajero' );
	$warna  = array( 'hitam', 'putih', 'merah', 'biru', 'abu-abu', 'silver', 'kelabu', 'hijau', 'kuning', 'cokelat', 'oranye', 'ungu', 'krem', 'gold', 'motif', 'doff' );

	$item = array(
		'plat'    => $plat,
		'merk'    => '',
		'model'   => '',
		'tahun'   => '',
		'warna'   => '',
		'ciri'    => '',
		'lokasi'  => '',
		'wilayah' => '',
		'polsek'  => '',
		'unit'    => '',
		'jam'     => '',
		'jenis'   => 'Motor',
		'ringkasan' => '',
	);

	$lower = ' ' . $ctx . ' ';

	foreach ( $motors as $mr ) {
		if ( false !== stripos( $lower, ' ' . $mr . ' ' ) ) {
			$item['model'] = strtoupper( $mr ) === 'NMAX' ? 'NMAX' : ucfirst( strtolower( $mr ) );
			$item['jenis'] = 'Motor';
			break;
		}
	}
	if ( ! $item['model'] ) {
		foreach ( $cars as $cr ) {
			if ( false !== stripos( $lower, ' ' . $cr . ' ' ) ) {
				$item['model'] = ucfirst( strtolower( $cr ) );
				$item['jenis'] = 'Mobil';
				break;
			}
		}
	}

	if ( preg_match( '/(?:motor|mobil)\s+(Honda|Yamaha|Suzuki|Kawasaki|Toyota|Daihatsu|Mitsubishi|Nissan|Datsun|Wuling|Hyundai|Kia)\b/i', $ctx, $m ) ) {
		$item['merk'] = $m[1];
	} elseif ( $item['model'] ) {
		$honda = array( 'Beat', 'Vario', 'Scoopy', 'Supra', 'Revo', 'Genio', 'PCX', 'Blade', 'Grand', 'Vision', 'Stylo', 'Elite', 'Gear', 'Freego', 'Absolute' );
		if ( in_array( $item['model'], $honda, true ) ) {
			$item['merk'] = 'Honda';
		}
	}

	if ( preg_match( '/(?:keluaran|tahun)\s+(\d{4})/i', $ctx, $m ) ) {
		$item['tahun'] = $m[1];
	} elseif ( preg_match( '/\b(20[0-2]\d)\b/', $ctx, $m ) ) {
		$item['tahun'] = $m[1];
	}

	foreach ( $warna as $w ) {
		if ( preg_match( '/\b(' . preg_quote( $w, '/' ) . '(?:\s+(?:dengan\s+)?(?:strip|motif|kombinasi)\s+[a-z]+(?:-[a-z]+)?)?)\b/i', $ctx, $m ) ) {
			$item['warna'] = ucfirst( trim( $m[1] ) );
			break;
		}
	}

	if ( preg_match( '/hilang\s+di\s+(?:sebuah\s+|kawasan\s+|rumah\s+|depan\s+|area\s+)?([^.,;]{4,70})/i', $ctx, $m ) ) {
		$item['lokasi'] = trim( $m[1] );
	} elseif ( preg_match( '/\bdi\s+(jalan\s+[^.,;]{4,60})/i', $ctx, $m ) ) {
		$item['lokasi'] = trim( $m[1] );
	}
	$item['lokasi'] = preg_replace( '/\s+(pada|saat|sekitar|yang)\b.*$/i', '', $item['lokasi'] );
	$item['lokasi'] = kh_clean_lokasi( $item['lokasi'] );

	$kota = array( 'Surabaya', 'Sidoarjo', 'Gresik', 'Jombang', 'Pasuruan', 'Probolinggo', 'Mojokerto', 'Malang', 'Bangkalan', 'Lamongan', 'Tuban', 'Bojonegoro' );
	foreach ( $kota as $k ) {
		if ( false !== stripos( $ctx, $k ) ) {
			$item['wilayah'] = $k;
			break;
		}
	}

	if ( $item['wilayah'] && $item['lokasi'] && false === stripos( $item['lokasi'], $item['wilayah'] ) ) {
		$item['lokasi'] = rtrim( $item['lokasi'], ' ,' ) . ', ' . $item['wilayah'];
	}

	/* Unit kepolisian: polsek, polres, polresta, polrestabes, atau polda.
	   Kata kunci dicari tanpa peduli huruf besar/kecil, tetapi nama wilayah
	   hanya diambil dari kata berhuruf besar (standar penulisan berita). */
	if ( preg_match( '/\b(?:makopol\w+|mapol\w+|polrestabes|polresta|polsekta|polsek|polres|polda)\b/i', $ctx, $mk, PREG_OFFSET_CAPTURE ) ) {
		$ekor      = substr( $ctx, $mk[0][1] );
		$unit_teks = $mk[0][0];
		if ( preg_match( '/^[^\s,\.;]+(?:\s+[A-Z][\w\.\-]*){0,4}/u', $ekor, $m2 ) ) {
			$unit_teks = $m2[0];
		}
		/* buang ekor kalimat berikutnya: "Polsek Tambaksari. Masih" → "Polsek Tambaksari" */
		$unit_teks = trim( preg_replace( '/\..*$/u', '', $unit_teks ) );
		$unit_info      = kh_deteksi_unit( $unit_teks );
		$item['polsek'] = $unit_info['nama'];
		$item['unit']   = $unit_info['level'];
	}

	if ( preg_match( '/pukul\s+(\d{1,2}[.:]\d{2})/i', $ctx, $m ) ) {
		$item['jam'] = str_replace( '.', ':', $m[1] );
	}

	if ( preg_match( '/(masih\s+standar[^.,;]*|strip\s+[a-z]+(?:-[a-z]+)?|motif[^.,;]*)/i', $ctx, $m ) ) {
		$item['ciri'] = trim( $m[1] );
	}

	/* Catatan kepatuhan: isi artikel sumber TIDAK disimpan.
	   Ringkasan dibuat ulang dari data (lihat kh_buat_ringkasan()). */
	return $item;
}

/**
 * Bersihkan frasa lokasi dari kata pengisi ("kos", "tempat makan", dst).
 */
function kh_clean_lokasi( $lokasi ) {
	$lokasi = trim( preg_replace( '/\s+/', ' ', (string) $lokasi ) );
	$fillers = array( 'sebuah ', 'kos ', 'kosan ', 'tempat makan ', 'warung kopi ', 'warung ', 'rumah ', 'depan ', 'halaman ', 'area ', 'parkiran ', 'masjid ', 'sekolah ', 'kantor ', 'pertokoan ' );

	$changed = true;
	while ( $changed ) {
		$changed = false;
		foreach ( $fillers as $f ) {
			if ( 0 === stripos( $lokasi, $f ) ) {
				$lokasi  = substr( $lokasi, strlen( $f ) );
				$changed = true;
			}
		}
	}

	$lokasi = trim( $lokasi, ' ,.' );
	if ( '' === $lokasi ) {
		return '';
	}
	return mb_strtoupper( mb_substr( $lokasi, 0, 1 ) ) . mb_substr( $lokasi, 1 );
}

/**
 * Geocode lokasi via Nominatim (satu request per item).
 */
function kh_geocode_lokasi( $lokasi, $wilayah ) {
	$base = trim( (string) $lokasi );
	$base = preg_replace( '/^(kawasan|area|daerah|kompleks|sekitar)\s+/i', '', $base );
	/* "Minimarket Mulyosari" → "Mulyosari" (Nominatim tidak kenal nama toko) */
	$base = preg_replace( '/^(depan\s+)?(minimarket|supermarket|swalayan|toko|warung|ruko|parkiran|halaman)\s+/i', '', $base );

	$candidates = array();
	if ( $base && $wilayah ) {
		$candidates[] = $base . ', ' . $wilayah . ', Jawa Timur';
	}
	if ( $base ) {
		$candidates[] = $base . ', Jawa Timur';
	}
	/* kalau ada nama jalan, coba jalan itu saja */
	if ( preg_match( '/((?:jalan|jl\.?)\s+[^,]+)/i', $base, $m ) ) {
		$jalan         = trim( $m[1] );
		$candidates[]  = $jalan . ( $wilayah ? ', ' . $wilayah . ', Jawa Timur' : ', Jawa Timur' );
	}
	if ( $wilayah ) {
		$candidates[] = $wilayah . ', Jawa Timur';
	}

	$pusat  = kh_pusat_wilayah();
	$center = ( $wilayah && isset( $pusat[ $wilayah ] ) ) ? $pusat[ $wilayah ] : null;

	foreach ( $candidates as $q ) {
		$list   = kh_nominatim_list( $q );
		$best   = null;
		$best_d = null;

		foreach ( $list as $p ) {
			if ( ! $center ) {
				$best = $p;
				break;
			}
			$d = kh_jarak_km( $center[0], $center[1], $p[0], $p[1] );
			/* tolak hasil yang jauh dari wilayahnya (mis. "Laban" di Pulau Bawean) */
			if ( $d <= 45 && ( null === $best_d || $d < $best_d ) ) {
				$best   = $p;
				$best_d = $d;
			}
		}

		if ( $best ) {
			return $best;
		}
	}

	return null;
}

/**
 * Titik pusat tiap wilayah (untuk menyaring hasil geocoding yang ngawur).
 */
function kh_pusat_wilayah() {
	return array(
		'Surabaya'    => array( -7.2575, 112.7521 ),
		'Sidoarjo'    => array( -7.4478, 112.7183 ),
		'Gresik'      => array( -7.1566, 112.6555 ),
		'Jombang'     => array( -7.5461, 112.2265 ),
		'Pasuruan'    => array( -7.6469, 112.9075 ),
		'Probolinggo' => array( -7.7543, 113.2159 ),
		'Mojokerto'   => array( -7.4708, 112.4338 ),
		'Malang'      => array( -7.9666, 112.6326 ),
		'Bangkalan'   => array( -7.0455, 112.7387 ),
		'Lamongan'    => array( -7.1183, 112.4159 ),
		'Tuban'       => array( -6.8976, 112.0648 ),
		'Bojonegoro'  => array( -7.1500, 111.8817 ),
	);
}

/**
 * Jarak dua titik (km, haversine).
 */
function kh_jarak_km( $lat1, $lng1, $lat2, $lng2 ) {
	$r    = 6371;
	$dLat = deg2rad( $lat2 - $lat1 );
	$dLng = deg2rad( $lng2 - $lng1 );
	$a    = sin( $dLat / 2 ) * sin( $dLat / 2 )
		+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dLng / 2 ) * sin( $dLng / 2 );
	return $r * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

/**
 * Beberapa kandidat hasil Nominatim (limit 5, dibatasi Jawa Timur).
 */
function kh_nominatim_list( $q ) {
	$key    = 'kh_geo5_' . md5( $q );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$url  = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&countrycodes=id'
		. '&viewbox=111.0,-9.0,114.9,-6.2&q=' . rawurlencode( $q );
	$resp = wp_remote_get(
		$url,
		array(
			'timeout'    => 20,
			'headers'    => array( 'User-Agent' => 'PetaKendaraanHilang/1.0 (admin import)' ),
		)
	);

	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return array();
	}

	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	$out  = array();
	if ( is_array( $data ) ) {
		foreach ( $data as $row ) {
			if ( empty( $row['lat'] ) || empty( $row['lon'] ) ) {
				continue;
			}
			$out[] = array( round( (float) $row['lat'], 5 ), round( (float) $row['lon'], 5 ) );
		}
	}

	set_transient( $key, $out, WEEK_IN_SECONDS );
	sleep( 1 );

	return $out;
}

/**
 * Satu panggilan Nominatim (dengan cache).
 */
function kh_nominatim( $q ) {
	$cached = get_transient( 'kh_geo_' . md5( $q ) );
	if ( $cached ) {
		return $cached;
	}

	$url  = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=id&q=' . rawurlencode( $q );
	$resp = wp_remote_get(
		$url,
		array(
			'timeout'    => 20,
			'user-agent' => 'PetaKendaraanHilang/1.0 (admin import; kontak: ' . get_option( 'admin_email' ) . ')',
		)
	);
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( empty( $data[0]['lat'] ) ) {
		return null;
	}
	$pair = array( round( (float) $data[0]['lat'], 5 ), round( (float) $data[0]['lon'], 5 ) );
	set_transient( 'kh_geo_' . md5( $q ), $pair, WEEK_IN_SECONDS );
	sleep( 1 );
	return $pair;
}
