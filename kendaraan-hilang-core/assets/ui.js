/* Peta Kendaraan Hilang — peta vektor (MapLibre) + data REST WordPress */
(function () {
	'use strict';

	var CFG = window.KH_UI || {};
	var $ = function (s) { return document.querySelector(s); };
	if (!CFG.endpoint) { console.warn('[kh] endpoint kosong'); return; }

	/* ---------- state ---------- */
	var D = [];
	var filt = 'all', qs = '', sel = -1, periode = 'all';
	var verif = '', showArsip = false;
	var map = null, markers = [], popup = null, meMarker = null, meAt = null;
	/* pengelompokan marker (cluster) — pilihan pengguna disimpan di localStorage */
	var clusterOn = true;
	try { clusterOn = '0' !== localStorage.getItem('kh_cluster'); } catch (e) {}
	var lastZoom = null, moveTimer = null, popupClosing = false, popupReadding = false;

	/* ---------- util ---------- */
	function esc(s) {
		return String(s === undefined || s === null ? '' : s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
	var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

	function waktuIndo(iso, jam) {
		if (!iso) { return jam ? jam + ' WIB' : ''; }
		var p = String(iso).split('-');
		if (p.length !== 3) { return iso; }
		var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
		var t = HARI[d.getDay()] + ', ' + parseInt(p[2], 10) + '/' + parseInt(p[1], 10) + '/' + p[0];
		return jam ? t + ' · ' + jam + ' WIB' : t;
	}

	function kendaraan(d) {
		var m = [d.merk, d.model].filter(Boolean).join(' ');
		if (!m) { m = d.judul || 'Kendaraan'; }
		return d.tahun ? m + ' (' + d.tahun + ')' : m;
	}

	function stateOf(d) {
		if ((d.status || '').toLowerCase() === 'ditemukan') { return 'got'; }
		return d.polsek ? 'rep' : 'open';
	}

	/* warna marker mengikuti level verifikasi */
	function verifOf(d) {
		var v = (d.verifikasi || 'warga').toLowerCase();
		return (v === 'media' || v === 'polisi') ? v : 'warga';
	}

	function colorOf(d) {
		if (stateOf(d) === 'got') { return '#b06bff'; }
		var v = verifOf(d);
		if (v === 'polisi') { return '#35f0c8'; }
		if (v === 'media') { return '#2fb4ff'; }
		return '#ffb02e';
	}

	function tagOf(d) {
		if (stateOf(d) === 'got') { return { text: 'DITEMUKAN', cls: ' got' }; }
		var v = verifOf(d);
		if (v === 'polisi') { return { text: 'LAPOR POLISI', cls: ' polisi' }; }
		if (v === 'media') { return { text: 'TERVERIFIKASI MEDIA', cls: ' media' }; }
		return { text: 'BELUM DIVERIFIKASI', cls: ' warga' };
	}

	function daysAgo(iso) {
		if (!iso) { return 9999; }
		var p = String(iso).split('-');
		if (p.length !== 3) { return 9999; }
		var t = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10)).getTime();
		return Math.round((Date.now() - t) / 86400000);
	}

	/* ---------- marker ---------- */
	var HEX_PTS = '0,-15 13,-7.5 13,7.5 0,15 -13,7.5 -13,-7.5';
	var HEX_CORE = '0,-7 6,-3.5 6,3.5 0,7 -6,3.5 -6,-3.5';
	var HEX_BIG = '0,-20 17.3,-10 17.3,10 0,20 -17.3,10 -17.3,-10';

	/* ---------- penanda peta sebagai layer vektor ----------
	 * Dulu tiap laporan = satu elemen DOM (261 <div>, masing-masing dengan listener
	 * sendiri). Itu penyumbang terbesar Total Blocking Time di Lighthouse mobile
	 * (long task maplibre 609 ms; ui.js 228 & 191 ms). Sekarang semua laporan masuk
	 * SATU GeoJSON source dan digambar MapLibre di GPU: node DOM hilang, interaksi
	 * cukup satu listener per layer.
	 */
	var KH_HEX_COLOR = { warga: '#ffb02e', media: '#2fb4ff', polisi: '#35f0c8', got: '#b06bff' };
	var khInteractionBound = false;

	/*
	 * Gambar ikon hexagon ke canvas lalu daftarkan sebagai image MapLibre.
	 * Warna tetap per status (sama dengan .mk-hex di ui.css) supaya legenda
	 * di layar tidak berubah arti.
	 */
	function khHexIcon(color, alpha) {
		var pr = 2, size = 34 * pr, c = document.createElement('canvas');
		c.width = size;
		c.height = size;
		var ctx = c.getContext('2d'), mid = size / 2, R = mid - 3 * pr;

		function hex(r) {
			ctx.beginPath();
			for (var k = 0; k < 6; k++) {
				var a = Math.PI / 180 * (60 * k - 90);
				var x = mid + r * Math.cos(a), y = mid + r * Math.sin(a);
				if (k === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
			}
			ctx.closePath();
		}

		ctx.lineJoin = 'round';
		hex(R);
		ctx.fillStyle = color;
		ctx.globalAlpha = 0.26 * alpha;
		ctx.fill();
		ctx.globalAlpha = alpha;
		ctx.strokeStyle = color;
		ctx.lineWidth = 2 * pr;
		ctx.stroke();
		hex(R * 0.46);
		ctx.lineWidth = 1.2 * pr;
		ctx.stroke();
		ctx.beginPath();
		ctx.arc(mid, mid, 2.4 * pr, 0, Math.PI * 2);
		ctx.fillStyle = '#fff';
		ctx.fill();

		return { width: size, height: size, data: ctx.getImageData(0, 0, size, size).data };
	}

	/* Pastikan source + layer penanda ada. Aman dipanggil berkali-kali. */
	function khEnsureLayers() {
		if (!map || !map.isStyleLoaded()) { return; }

		if (!map.getSource('kh-src')) {
			map.addSource('kh-src', {
				type: 'geojson',
				data: { type: 'FeatureCollection', features: [] },
				cluster: clusterOn,
				clusterRadius: 46,
				/* 15 = zoom tempat select() mendarat, jadi laporan yang dipilih
				   selalu tampil sendiri (tidak tertelan kelompok). */
				clusterMaxZoom: 15
			});
		}

		['warga', 'media', 'polisi', 'got'].forEach(function (st) {
			var name = 'kh-hex-' + st;
			if (!map.hasImage(name)) {
				map.addImage(name, khHexIcon(KH_HEX_COLOR[st], st === 'got' ? 0.72 : 1), { pixelRatio: 2 });
			}
		});

		if (!map.getLayer('kh-sel')) {
			map.addLayer({
				id: 'kh-sel', type: 'circle', source: 'kh-src',
				filter: ['==', ['get', 'i'], -1],
				paint: {
					'circle-radius': 19,
					'circle-color': ['get', 'c'],
					'circle-opacity': 0.22,
					'circle-blur': 0.6
				}
			});
		}
		if (!map.getLayer('kh-glow')) {
			map.addLayer({
				id: 'kh-glow', type: 'circle', source: 'kh-src', minzoom: 11,
				filter: ['!', ['has', 'point_count']],
				paint: {
					'circle-radius': 11,
					'circle-color': ['get', 'c'],
					'circle-opacity': 0.14
					/* tanpa circle-blur: blur memaksa render offscreen per titik dan
					   jadi penyumbang TBT terbesar (diukur: lihat seo-audit/SEO-TEST) */
				}
			});
		}
		if (!map.getLayer('kh-cluster')) {
			map.addLayer({
				id: 'kh-cluster', type: 'circle', source: 'kh-src',
				filter: ['has', 'point_count'],
				paint: {
					'circle-radius': ['step', ['get', 'point_count'], 15, 10, 19, 40, 24, 100, 30],
					'circle-color': 'rgba(53,240,200,0.26)',
					'circle-stroke-color': '#35f0c8',
					'circle-stroke-width': 2
				}
			});
			map.addLayer({
				id: 'kh-cluster-count', type: 'symbol', source: 'kh-src',
				filter: ['has', 'point_count'],
				layout: {
					'text-field': ['get', 'point_count_abbreviated'],
					'text-font': ['Noto Sans Bold'],
					'text-size': 13,
					'text-allow-overlap': true
				},
				paint: { 'text-color': '#eafffb', 'text-halo-color': 'rgba(3,16,24,0.85)', 'text-halo-width': 1.6 }
			});
		}
		if (!map.getLayer('kh-point')) {
			map.addLayer({
				id: 'kh-point', type: 'symbol', source: 'kh-src',
				/* Di bawah zoom 11 cukup kelompok: 261 simbol berdesakan itu mahal
				   (main thread) dan secara visual tidak terbaca. */
				minzoom: 11,
				filter: ['!', ['has', 'point_count']],
				layout: {
					'icon-image': ['concat', 'kh-hex-', ['get', 'st']],
					'icon-size': 1.1,
					'icon-allow-overlap': true,
					'icon-ignore-placement': true
				}
			});
		}
		if (!map.getLayer('kh-plat')) {
			map.addLayer({
				id: 'kh-plat', type: 'symbol', source: 'kh-src', minzoom: 13.2,
				filter: ['!', ['has', 'point_count']],
				layout: {
					'text-field': ['get', 'plat'],
					'text-font': ['Noto Sans Bold'],
					'text-size': 10,
					'text-offset': [0, 1.7],
					'text-anchor': 'top',
					'text-allow-overlap': true
				},
				paint: { 'text-color': '#cfe9ee', 'text-halo-color': '#04080d', 'text-halo-width': 2 }
			});
		}

		if (!khInteractionBound) {
			khInteractionBound = true;
			map.on('click', 'kh-point', function (e) {
				if (e.features && e.features[0]) {
					select(parseInt(e.features[0].properties.i, 10), true);
				}
			});
			map.on('click', 'kh-cluster', function (e) {
				if (!e.features || !e.features[0]) { return; }
				var f = e.features[0];
				/*
				 * MapLibre GL JS 3/4 mengembalikan Promise dari getClusterExpansionZoom();
				 * bentuk callback lama (err, zoom) sudah dihapus. Dukung dua-duanya supaya
				 * tidak diam-diam tidak terjadi apa-apa saat versi di-upgrade.
				 */
				var hasil = map.getSource('kh-src').getClusterExpansionZoom(f.properties.cluster_id);
				function pindah(z) {
					map.easeTo({ center: f.geometry.coordinates, zoom: Math.min(17, (z || map.getZoom() + 2) + 0.2), duration: 500 });
				}
				if (hasil && typeof hasil.then === 'function') {
					hasil.then(pindah).catch(function () { pindah(null); });
				} else if (typeof hasil === 'number') {
					pindah(hasil);
				}
			});
			['kh-point', 'kh-cluster'].forEach(function (lid) {
				map.on('mouseenter', lid, function () { map.getCanvas().style.cursor = 'pointer'; });
				map.on('mouseleave', lid, function () { map.getCanvas().style.cursor = ''; });
			});
		}

		khRadiusToBack();
	}

	/* Lingkaran radius 1 km harus tetap DI BAWAH penanda (dulu penanda DOM selalu
	   di atas kanvas; sekarang penanda ikut kanvas, jadi urutan layer diatur). */
	function khRadiusToBack() {
		if (!map || !map.getLayer('kh-radius-fill') || !map.getLayer('kh-sel')) { return; }
		var urut;
		try { urut = map.getStyle().layers.map(function (l) { return l.id; }); } catch (e) { return; }
		var iRadius = urut.indexOf('kh-radius-fill');
		var iSel = urut.indexOf('kh-sel');
		/* Sudah di bawah penanda? jangan sentuh apa pun — moveLayer memicu styledata. */
		if (iRadius === -1 || iSel === -1 || iRadius < iSel) { return; }
		try {
			map.moveLayer('kh-radius-fill', 'kh-sel');
			map.moveLayer('kh-radius-line', 'kh-sel');
		} catch (e) { /* diabaikan */ }
	}

	/* Sorotan laporan terpilih. */
	function khSelFilter() {
		if (!map || !map.getLayer('kh-sel')) { return; }
		var d = D[sel];
		map.setFilter('kh-sel', ['==', ['get', 'i'], d ? d.i : -1]);
		if (d) { map.setPaintProperty('kh-sel', 'circle-color', colorOf(d)); }
	}

	/* Bangun ulang source (dipakai saat saklar pengelompokan diubah). */
	function khRebuildSource() {
		if (!map) { return; }
		['kh-plat', 'kh-point', 'kh-glow', 'kh-cluster-count', 'kh-cluster', 'kh-sel'].forEach(function (id) {
			if (map.getLayer(id)) { map.removeLayer(id); }
		});
		if (map.getSource('kh-src')) { map.removeSource('kh-src'); }
		khEnsureLayers();
		renderMarkers(visible());
	}

	function popupHtml(d) {
		var c = colorOf(d);
		var vtxt = stateOf(d) === 'got' ? 'Sudah ditemukan'
			: (verifOf(d) === 'polisi' ? 'Sudah lapor polisi' + (d.polsek ? ' · ' + d.polsek : '')
				: (verifOf(d) === 'media' ? 'Terverifikasi media' : 'Belum diverifikasi'));
		return '<div class="pop-plat" style="color:' + c + '">' + esc(d.plat) + '</div>' +
			'<div class="pop-row">' + esc(kendaraan(d)) + (d.warna ? ' · ' + esc(d.warna) : '') + '</div>' +
			'<div class="pop-row">' + esc(d.lokasi || '') + '</div>' +
			'<div class="pop-row">' + esc(d.waktu || '') + '</div>' +
			'<div class="pop-verif pop-verif-' + verifOf(d) + '">' + esc(vtxt) + '</div>' +
			'<a class="pop-link" href="' + esc(d.url) + '">Detail &rsaquo;</a>';
	}

	/* ---------- peta ---------- */
	function webglOk() {
		try {
			var c = document.createElement('canvas');
			var opts = { failIfMajorPerformanceCaveat: false };
			return !!(c.getContext('webgl2', opts) || c.getContext('webgl', opts));
		} catch (e) { return false; }
	}

	function initMap() {
		if (typeof maplibregl === 'undefined' || !window.KH_MAP_STYLE) {
			$('#realmap').innerHTML = '<div class="ml-fallback">Peta tidak dapat dimuat. Coba muat ulang halaman.</div>';
			return false;
		}
		if (!webglOk()) {
			$('#realmap').innerHTML = '<div class="ml-fallback">Browser ini belum mendukung WebGL sehingga peta tidak bisa ditampilkan. Coba browser lain atau perbarui browser.</div>';
			return false;
		}

		map = new maplibregl.Map({
			container: 'realmap',
			style: window.KH_MAP_STYLE,
			center: [112.65, -7.32],
			zoom: 10,
			attributionControl: true,
			antialias: true,
			failIfMajorPerformanceCaveat: false
		});

		/* Kontrol zoom bawaan MapLibre tidak dipakai: panel .ctl sudah menyediakan
		   zoom +/- , "tampilkan semua", dan "lokasi saya" dengan gaya HUD sendiri. */
		window.KH_ML = map;

		var bootTries = 0;
		var bootTimer = setInterval(function () {
			bootTries++;
			var ok = map && map.isStyleLoaded();
			if (ok) {
				$('#boot').classList.add('hide');
				var w = document.querySelector('.ml-warn');
				if (w) { w.remove(); }
				clearInterval(bootTimer);
				/*
				 * Style peta baru siap DI SINI. Data laporan biasanya tiba lebih dulu,
				 * saat isStyleLoaded() masih false — dulu itu membuat khEnsureLayers()
				 * menolak dan layer penanda tidak pernah dibuat (marker hilang semua,
				 * tanpa error di console). Jadi gambar ulang setelah style siap.
				 */
				khEnsureLayers();
				renderMarkers(visible());
				return;
			}
			if (bootTries === 12) { /* ~12 detik: sembunyikan overlay, beri pesan */
				$('#boot').classList.add('hide');
				var warn = document.createElement('div');
				warn.className = 'ml-warn';
				warn.innerHTML = 'Peta lambat dimuat. Kalau peta tidak muncul, ' +
					'<a href="" onclick="location.reload();return false;">muat ulang halaman</a> atau cek koneksi internet.';
				var rm = $('#realmap');
				if (rm) { rm.appendChild(warn); }
			}
			if (bootTries > 90) { clearInterval(bootTimer); }
		}, 1000);

		/* Jaring pengaman kalau interval di atas terlewat. JANGAN pakai 'styledata':
		   khEnsureLayers() memanggil moveLayer (untuk urutan radius), dan moveLayer
		   memicu 'styledata' lagi → loop yang membakar main thread (TBT 2,8 s → 7,8 s). */
		map.on('load', function () { khEnsureLayers(); renderMarkers(visible()); });

		map.on('zoom', function () {
			document.body.classList.toggle('show-plat', map.getZoom() >= 13.2);
		});

		map.on('zoomend', function () {
			var z = Math.round(map.getZoom() * 2) / 2;
			if (lastZoom === null || Math.abs(z - lastZoom) >= 0.5) {
				lastZoom = z;
				renderMarkers(visible());
			}
		});

		map.on('moveend', function () {
			clearTimeout(moveTimer);
			moveTimer = setTimeout(function () { renderMarkers(visible()); }, 220);
		});

		popup = new maplibregl.Popup({ maxWidth: '300px', closeButton: true, offset: 18 });

		/* MapLibre menutup popup sendiri saat peta diklik (closeOnClick bawaan).
		   Tanpa ini, popup hilang tapi kartu + radius + sorotan list tertinggal —
		   radius jadi tampil padahal tak ada item yang dipilih lagi.
		   Catatan: addTo() pada popup yang sudah terbuka juga memicu 'close'
		   (karena dia remove() dulu), jadi saat berpindah item event itu harus
		   diabaikan — kalau tidak, radius item baru langsung dihapus. */
		popup.on('close', function () {
			if (popupClosing || popupReadding) { return; }
			closeCard();
		});

		/* Tombol bawah (FAB "Daftar" + tombol lapor) menyingkir HANYA kalau popup
		   benar-benar menimpanya. Dulu popup bisa menutupi FAB sampai 58x40 px
		   setelah peta digeser ke bawah — tidak terlihat dari kode, hanya ketahuan
		   lewat pengukuran rect di layar HP. Diukur setiap geseran, bukan sekali
		   saat popup dibuka, supaya tetap benar setelah pengguna menggeser peta. */
		var rafSync = 0;
		function syncBottomUI() {
			var el = popup && popup.isOpen() ? popup.getElement() : null;
			if (!el || window.innerWidth > 900) {
				document.body.classList.remove('kh-popup-over-ui');
				return;
			}
			var a = el.getBoundingClientRect();
			var bentrok = false;
			['kh-fab', 'kh-add'].forEach(function (id) {
				var b = document.getElementById(id);
				if (!b || b.offsetParent === null) { return; }
				var r = b.getBoundingClientRect();
				var lebar = Math.min(a.right, r.right) - Math.max(a.left, r.left);
				var tinggi = Math.min(a.bottom, r.bottom) - Math.max(a.top, r.top);
				if (lebar > 0 && tinggi > 0) { bentrok = true; }
			});
			document.body.classList.toggle('kh-popup-over-ui', bentrok);
		}
		function scheduleUiSync() {
			if (rafSync) { return; }
			rafSync = window.requestAnimationFrame(function () {
				rafSync = 0;
				syncBottomUI();
			});
		}
		popup.on('open', function () {
			scheduleUiSync();

			/* Popup bisa keluar layar bawah kalau pin-nya dekat tepi (terukur: bottom
			   904 px di layar 844 px — tombol "Detail" ikut terpotong). Geser peta
			   sekali saat dibuka supaya seluruh popup masuk; jangan diulang saat
			   pengguna menggeser sendiri, nanti terasa seperti peta dilawan. */
			if (window.innerWidth > 900) { return; }
			var el = popup.getElement();
			if (!el) { return; }
			var sisa = el.getBoundingClientRect().bottom - (window.innerHeight - 12);
			if (sisa > 12) {
				map.panBy([0, sisa + 12], { duration: 260 });
			}
		});
		popup.on('close', function () { document.body.classList.remove('kh-popup-over-ui'); });
		map.on('move', scheduleUiSync);
		map.on('zoomend', scheduleUiSync);

		map.on('error', function (e) {
			console.warn('[map]', e && e.error ? e.error.message : e);
		});

		return true;
	}

	function fitAll() {
		if (!map) { return; }
		var items = visible();
		if (!items.length) {
			map.easeTo({ center: [112.65, -7.32], zoom: 10, duration: 500 });
			return;
		}
		var b = new maplibregl.LngLatBounds();
		items.forEach(function (d) { b.extend([d.lng, d.lat]); });
		map.fitBounds(b, { padding: 70, maxZoom: 14, duration: 700 });
	}

	/* ---------- render penanda ---------- */
	function renderMarkers(items) {
		if (!map) { return; }
		khEnsureLayers();
		var src = map.getSource('kh-src');
		if (!src) { return; }

		var feats = items.map(function (d) {
			var got = stateOf(d) === 'got';
			return {
				type: 'Feature',
				geometry: { type: 'Point', coordinates: [d.lng, d.lat] },
				properties: {
					i: d.i,
					st: got ? 'got' : verifOf(d),
					plat: d.plat || '',
					c: colorOf(d)
				}
			};
		});

		src.setData({ type: 'FeatureCollection', features: feats });
		khSelFilter();
	}

	/* ---------- filter ---------- */
	function visible() {
		var q = qs;
		return D.filter(function (d) {
			if (d.arsip && !showArsip) { return false; }
			if (verif && verifOf(d) !== verif) { return false; }
			if (periode !== 'all' && daysAgo(d.tanggal) > parseInt(periode, 10)) { return false; }
			if (filt === 'got' && stateOf(d) !== 'got') { return false; }
			if (filt === 'open' && stateOf(d) === 'got') { return false; }
			if (filt !== 'all' && filt !== 'open' && filt !== 'got') {
				var slug = String(d.wilayah || '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
				if (slug !== filt) { return false; }
			}
			if (q) {
				var hay = [d.plat, d.merk, d.model, d.warna, d.lokasi, d.judul, d.wilayah].join(' ').toLowerCase();
				if (hay.indexOf(q) === -1) { return false; }
			}
			return true;
		});
	}

	/* ---------- list ---------- */
	function cardHtml(d) {
		var tg = tagOf(d);
		var st = stateOf(d);
		return '<div class="item' + (st === 'open' ? '' : (st === 'rep' ? ' rep' : ' got')) + (d.arsip ? ' is-arsip' : '') +
			(d.i === sel ? ' sel' : '') + '" data-i="' + d.i + '">' +
			'<div class="row">' + (d.url
				? '<a class="plat plat-link" href="' + esc(d.url) + '">' + esc(d.plat) + '</a>'
				: '<span class="plat">' + esc(d.plat) + '</span>') +
			'<span class="tag' + tg.cls + '">' + tg.text + '</span></div>' +
			'<div class="m">' + esc(kendaraan(d)) + (d.warna ? ' · ' + esc(d.warna) : '') + '</div>' +
			'<div class="s">' + esc(d.lokasi || '') + (d.waktu ? '<br>' + esc(d.waktu) : '') + '</div>' +
			(d.arsip ? '<div class="arsip-tag">ARSIP · ' + d.umur_hari + ' hari</div>' : '') +
			'</div>';
	}

	function render() {
		var items = visible();
		var belum = 0, lapor = 0;

		/* Item yang sedang dipilih bisa hilang karena filter/pencarian:
		   jangan tinggalkan popup + radius untuk item yang tak tampil lagi. */
		if (sel >= 0 && items.indexOf(D[sel]) === -1) {
			closeCard();
		}

		items.forEach(function (d) {
			var v = verifOf(d);
			if (v === 'warga') { belum++; } else { lapor++; }
		});

		$('#list').innerHTML = items.length
			? items.map(cardHtml).join('')
			: '<div class="empty">Tidak ada laporan yang cocok.</div>';

		var tl = $('#timeline');
		if (tl) { tl.innerHTML = timelineHtml(items); }

		$('#n0').textContent = items.length;
		$('#n1').textContent = belum;
		$('#n2').textContent = lapor;
		$('#sub').textContent = D.length + ' laporan · Jawa Timur';

		var fc = $('#kh-fab-count');
		if (fc) { fc.textContent = items.length; }
		var fabEl = $('#kh-fab');
		if (fabEl) { fabEl.setAttribute('aria-label', 'Daftar ' + items.length + ' laporan'); }

		renderMarkers(items);
	}

	/* ---------- timeline ---------- */
	function timelineHtml(items) {
		if (!items.length) {
			return '<div class="empty">Tidak ada laporan pada rentang ini.</div>';
		}

		var groups = {};
		items.forEach(function (d) {
			var k = d.tanggal || 'tanpa-tanggal';
			if (!groups[k]) { groups[k] = []; }
			groups[k].push(d);
		});

		return Object.keys(groups).sort().reverse().map(function (k) {
			var list = groups[k].slice().sort(function (a, b) {
				return String(b.jam || '').localeCompare(String(a.jam || ''));
			});

			var label = 'Tanggal tidak tercatat';
			if ('tanpa-tanggal' !== k) {
				var p = k.split('-');
				var dt = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
				label = HARI[dt.getDay()] + ', ' + parseInt(p[2], 10) + ' ' +
					BULAN[parseInt(p[1], 10) - 1] + ' ' + p[0];
			}

			var rows = list.map(function (d) {
				return '<li class="tl-item' + (d.i === sel ? ' sel' : '') + '" data-i="' + d.i + '">' +
					'<span class="tl-dot tl-' + (stateOf(d) === 'got' ? 'got' : verifOf(d)) + '"></span>' +
					'<div class="tl-body">' +
					'<div class="tl-top"><span class="tl-plat">' + esc(d.plat) + '</span>' +
					'<span class="tl-jam">' + esc(d.jam || '--:--') + '</span></div>' +
					'<div class="tl-model">' + esc(kendaraan(d)) + (d.warna ? ' · ' + esc(d.warna) : '') + '</div>' +
					'<div class="tl-loc">' + esc(d.lokasi || '') + '</div>' +
					'</div></li>';
			}).join('');

			return '<div class="tl-group"><div class="tl-date">' + esc(label) +
				'<span class="tl-count">' + list.length + '</span></div>' +
				'<ul class="tl-list">' + rows + '</ul></div>';
		}).join('');
	}

	/* ---------- radius perkiraan lokasi ---------- */
	var RADIUS_M = 1000;

	/* Lingkaran geodesik (bukan lingkaran piksel), jadi skalanya tetap 1 km
	   berapa pun zoom-nya. Warna dibawa di properti fitur supaya border
	   lingkarannya sama dengan warna pin heksagon item itu. */
	function radiusPolygon(lng, lat, meters, steps, warna) {
		var pts = [], R = 6378137, d = meters / R;
		var la1 = lat * Math.PI / 180, lo1 = lng * Math.PI / 180;
		for (var i = 0; i <= steps; i++) {
			var brg = (i % steps) * (2 * Math.PI / steps);
			var la2 = Math.asin(Math.sin(la1) * Math.cos(d) + Math.cos(la1) * Math.sin(d) * Math.cos(brg));
			var lo2 = lo1 + Math.atan2(Math.sin(brg) * Math.sin(d) * Math.cos(la1), Math.cos(d) - Math.sin(la1) * Math.sin(la2));
			pts.push([lo2 * 180 / Math.PI, la2 * 180 / Math.PI]);
		}
		return { type: 'Feature', properties: { warna: warna || '#e8f6fb' }, geometry: { type: 'Polygon', coordinates: [pts] } };
	}

	/* Marching ants: MapLibre tak punya line-dash-offset, jadi fase garis
	   putus-putus dianimasikan lewat deret line-dasharray (pola resmi contoh
	   Mapbox "animate a line"). Hanya berjalan selama radius tampil. */
	var ANTS = [
		[0, 4, 3], [0.5, 4, 2.5], [1, 4, 2], [1.5, 4, 1.5], [2, 4, 1], [2.5, 4, 0.5], [3, 4, 0],
		[0, 0.5, 3, 3.5], [0, 1, 3, 3], [0, 1.5, 3, 2.5], [0, 2, 3, 2], [0, 2.5, 3, 1.5], [0, 3, 3, 1], [0, 3.5, 3, 0.5]
	];
	var antsTimer = null, antsIdx = 0;
	var kurangiGerak = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

	function startAnts() {
		if (antsTimer || kurangiGerak || !map || !map.getLayer('kh-radius-line')) { return; }
		antsTimer = setInterval(function () {
			if (!map || !map.getLayer('kh-radius-line')) { return; }
			antsIdx = (antsIdx + 1) % ANTS.length;
			map.setPaintProperty('kh-radius-line', 'line-dasharray', ANTS[antsIdx]);
		}, 70);
	}

	function stopAnts() {
		if (antsTimer) {
			clearInterval(antsTimer);
			antsTimer = null;
		}
	}

	function ensureRadiusSource() {
		if (!map || !map.isStyleLoaded() || map.getSource('kh-radius')) { return; }
		map.addSource('kh-radius', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
		/* Sisipkan DI BAWAH layer penanda: penanda sekarang digambar di kanvas,
		   jadi tanpa beforeId lingkaran radius akan menutupi hexagon. */
		var before = map.getLayer('kh-sel') ? 'kh-sel' : undefined;
		map.addLayer({
			id: 'kh-radius-fill', type: 'fill', source: 'kh-radius',
			paint: { 'fill-color': ['get', 'warna'], 'fill-opacity': 0.06 }
		}, before);
		map.addLayer({
			id: 'kh-radius-line', type: 'line', source: 'kh-radius',
			paint: {
				'line-color': ['get', 'warna'],
				'line-width': 2,
				'line-opacity': 0.95,
				'line-dasharray': ANTS[0]
			}
		}, before);
	}

	/* Hanya untuk item yang sedang dipilih; d = null → sembunyikan. */
	function setRadius(d) {
		var kosong = { type: 'FeatureCollection', features: [] };
		var pilih = !!(d && d.lat != null && d.lng != null);

		/* Jalur sembunyikan TIDAK boleh bergantung pada isStyleLoaded():
		   nilainya bisa false sesaat (tile sedang dimuat), dan kalau kita
		   keluar lebih awal di sini, timer marching-ants terus jalan
		   setelah popup ditutup. */
		if (!pilih) {
			stopAnts();
			if (map && map.getSource('kh-radius')) {
				map.getSource('kh-radius').setData(kosong);
			}
			var keyMati = document.getElementById('kh-radius-key');
			if (keyMati) { keyMati.hidden = true; }
			return;
		}

		if (!map) { return; }
		if (!map.isStyleLoaded()) {
			map.once('styledata', function () { setRadius(d); });
			return;
		}

		ensureRadiusSource();
		var src = map.getSource('kh-radius');
		if (!src) { return; }
		src.setData({
			type: 'FeatureCollection',
			features: [radiusPolygon(d.lng, d.lat, RADIUS_M, 64, colorOf(d))]
		});

		/* legenda ikut muncul, warnanya sama dengan pin yang dipilih */
		var key = document.getElementById('kh-radius-key');
		if (key) {
			key.style.setProperty('--kh-r', colorOf(d));
			key.hidden = false;
		}
		startAnts();
	}

	/* ---------- pilih ---------- */
	function select(i, fly, silent) {
		sel = i;
		var d = D[i];
		if (!d) { return; }

		$('#c-p').textContent = d.plat || '—';
		$('#c-p').style.color = colorOf(d);

		var st = stateOf(d);
		$('#c-d').innerHTML =
			'<dt>Kendaraan</dt><dd>' + esc(kendaraan(d)) + '</dd>' +
			'<dt>Warna</dt><dd>' + esc(d.warna || '—') + '</dd>' +
			'<dt>Lokasi</dt><dd>' + esc(d.lokasi || '—') + '</dd>' +
			'<dt>Waktu</dt><dd>' + esc(d.waktu || '—') + '</dd>' +
			'<dt>Status</dt><dd>' + (st === 'got' ? 'Sudah ditemukan' : (d.polsek ? 'Sudah lapor ke ' + esc(d.polsek) : 'Belum ada laporan polisi')) + '</dd>';

		$('#c-more').href = d.url || '#';
		/* Desktop: panel detail + popup. Mobile: cukup popup (ringkas, hemat ruang). */
		if (window.innerWidth > 760) {
			$('#card').classList.add('show');
		} else {
			$('#card').classList.remove('show');
		}

		Array.prototype.forEach.call(document.querySelectorAll('#list .item'), function (el) {
			el.classList.toggle('sel', parseInt(el.dataset.i, 10) === i);
		});
		Array.prototype.forEach.call(document.querySelectorAll('#timeline .tl-item'), function (el) {
			el.classList.toggle('sel', parseInt(el.dataset.i, 10) === i);
		});

		if (!silent) {
			try {
				var u = new URL(window.location.href);
				u.searchParams.set('laporan', d.pid);
				history.replaceState({}, '', u.toString());
			} catch (e) { /* diabaikan */ }
		}

		if (map) {
			if (fly) {
				map.flyTo({ center: [d.lng, d.lat], zoom: Math.max(map.getZoom(), 15), duration: 800 });
			}
			popupReadding = true;
			popup.setLngLat([d.lng, d.lat]).setHTML(popupHtml(d)).addTo(map);
			popupReadding = false;
			/* radius perkiraan 1 km hanya untuk item yang dipilih */
			if (map.isStyleLoaded()) {
				setRadius(d);
			} else {
				map.once('load', function () { setRadius(d); });
			}
		}

		khSelFilter();

		var it = document.querySelector('#list .item.sel');
		if (it) { it.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }

		if (window.innerWidth <= 760) { closePanel(); }
	}

	function closeCard() {
		sel = -1;
		khSelFilter();
		$('#card').classList.remove('show');
		Array.prototype.forEach.call(document.querySelectorAll('#list .item'), function (el) {
			el.classList.remove('sel');
		});
		Array.prototype.forEach.call(document.querySelectorAll('#timeline .tl-item'), function (el) {
			el.classList.remove('sel');
		});
		if (popup) {
			popupClosing = true;
			popup.remove();
			popupClosing = false;
		}
		setRadius(null);
	}

	/* ---------- toast ---------- */
	var toastTimer = null;
	function toast(msg) {
		var el = $('#toast');
		if (!el) { return; }
		el.textContent = msg;
		el.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { el.hidden = true; }, 2200);
	}

	/* ---------- kontrol ---------- */
	$('#list').addEventListener('click', function (e) {
		/* Tautan "Detail laporan" harus membuka halaman laporan, bukan memilih pin. */
		if (e.target.closest('a')) { return; }
		var it = e.target.closest('.item');
		/* Item hasil render server (belum diganti JS) tidak punya data-i. */
		if (it && it.dataset.i) { select(parseInt(it.dataset.i, 10), true); }
	});

	$('#timeline').addEventListener('click', function (e) {
		var it = e.target.closest('.tl-item');
		if (it) { select(parseInt(it.dataset.i, 10), true); }
	});

	Array.prototype.forEach.call(document.querySelectorAll('.view-btn'), function (btn) {
		btn.addEventListener('click', function () {
			Array.prototype.forEach.call(document.querySelectorAll('.view-btn'), function (b) {
				b.classList.remove('on');
			});
			btn.classList.add('on');
			var v = btn.dataset.view;
			$('#list').hidden = (v !== 'list');
			$('#timeline').hidden = (v !== 'timeline');
			var f = $('#tl-filter');
			if (f) { f.hidden = (v !== 'timeline'); }
		});
	});

	var perSel = $('#kh-periode');
	if (perSel) {
		perSel.addEventListener('change', function () {
			periode = perSel.value;
			render();
			fitAll();
		});
	}

	/* filter verifikasi */
	var selVerif = $('#kh-verif');
	if (selVerif) {
		selVerif.addEventListener('change', function () {
			verif = selVerif.value;
			render();
			fitAll();
		});
	}

	/* filter status (hilang / ditemukan) */
	var selStatus = $('#kh-status');
	if (selStatus) {
		selStatus.addEventListener('change', function () {
			var v = selStatus.value;
			filt = (v === 'ditemukan') ? 'got' : (v === 'hilang' ? 'open' : 'all');
			render();
			fitAll();
		});
	}

	/* tampilkan arsip */
	var cbArsip = $('#kh-arsip');
	if (cbArsip) {
		cbArsip.addEventListener('change', function () {
			showArsip = cbArsip.checked;
			render();
		});
	}

	$('#cx').onclick = closeCard;

	var copyBtn = $('#c-copy');
	if (copyBtn) {
		copyBtn.addEventListener('click', function () {
			var d = D[sel];
			if (!d) { return; }
			var url = location.origin + location.pathname + '?laporan=' + d.pid;
			var done = function () { toast('Tautan laporan disalin'); };
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done, function () { toast(url); });
			} else {
				toast(url);
			}
		});
	}

	$('#zf').onclick = fitAll;

	/* saklar pengelompokan marker (#zc) */
	var zcBtn = $('#zc');

	function clusterUI() {
		if (!zcBtn) { return; }
		zcBtn.classList.toggle('is-off', !clusterOn);
		zcBtn.setAttribute('aria-pressed', clusterOn ? 'true' : 'false');
		var teks = clusterOn
			? 'Sembunyikan pengelompokan marker'
			: 'Tampilkan pengelompokan marker';
		zcBtn.title = teks;
		zcBtn.setAttribute('aria-label', teks);
	}

	if (zcBtn) {
		zcBtn.onclick = function () {
			clusterOn = !clusterOn;
			try { localStorage.setItem('kh_cluster', clusterOn ? '1' : '0'); } catch (e) {}
			clusterUI();
			khRebuildSource();
			toast(clusterOn ? 'Marker dikelompokkan' : 'Marker ditampilkan satu per satu');
		};
		clusterUI();
	}
	$('#zi').onclick = function () { if (map) { map.zoomIn({ duration: 300 }); } };
	$('#zo').onclick = function () { if (map) { map.zoomOut({ duration: 300 }); } };

	var geoBtn = $('#zg');
	if (geoBtn) {
		geoBtn.onclick = function () {
			if (!navigator.geolocation) { toast('Browser tidak mendukung lokasi'); return; }
			toast('Mencari lokasi…');
			navigator.geolocation.getCurrentPosition(function (pos) {
				var ll = [pos.coords.longitude, pos.coords.latitude];
				meAt = ll;
				if (map) {
					if (!meMarker) {
						var el = document.createElement('div');
						el.className = 'mk-me';
						meMarker = new maplibregl.Marker({ element: el, anchor: 'center' }).setLngLat(ll).addTo(map);
					} else {
						meMarker.setLngLat(ll);
					}
					map.flyTo({ center: ll, zoom: 15, duration: 900 });
				}
				toast('Lokasi Anda ditampilkan');
			}, function () {
				toast('Izin lokasi ditolak');
			}, { enableHighAccuracy: true, timeout: 8000 });
		};
	}

	var qEl = $('#q');
	var tq;
	qEl.addEventListener('input', function () {
		clearTimeout(tq);
		var v = qEl.value.trim().toLowerCase();
		tq = setTimeout(function () { qs = v; render(); }, 180);
	});

	$('#chips').addEventListener('click', function (e) {
		var b = e.target.closest('.chip');
		if (!b) { return; }
		Array.prototype.forEach.call(document.querySelectorAll('.chip'), function (c) { c.classList.remove('on'); });
		b.classList.add('on');
		filt = b.dataset.f;
		render();
		fitAll();
	});

	/*
	 * Petunjuk geser untuk deretan chip: gradien di tepi kiri/kanan hanya muncul
	 * kalau masih ada chip tersembunyi di sisi itu. Kelas dipasang di #chips-wrap
	 * (bukan #chips yang ikut ter-scroll) supaya gradiennya menempel di tepi panel.
	 */
	var chipsEl = document.getElementById('chips');
	var chipsWrap = document.getElementById('chips-wrap');

	function chipsHint() {
		if (!chipsEl || !chipsWrap) { return; }
		var max = chipsEl.scrollWidth - chipsEl.clientWidth;
		chipsWrap.classList.toggle('more', max > 4 && chipsEl.scrollLeft < max - 4);
		chipsWrap.classList.toggle('less', chipsEl.scrollLeft > 4);
	}

	if (chipsEl) { chipsEl.addEventListener('scroll', chipsHint, { passive: true }); }
	document.addEventListener('kh:chips', chipsHint);
	window.addEventListener('resize', chipsHint);
	chipsHint();

	window.addEventListener('resize', function () { if (map) { map.resize(); } });

	/* ---------- panel geser (mobile) ---------- */
	var asideEl = document.querySelector('aside');
	var backdrop = $('#kh-backdrop');

	function openPanel() {
		if (!asideEl) { return; }
		asideEl.classList.add('open');
		if (backdrop) {
			backdrop.hidden = false;
			requestAnimationFrame(function () { backdrop.classList.add('show'); });
		}
	}

	function closePanel() {
		if (asideEl) { asideEl.classList.remove('open'); }
		if (backdrop) {
			backdrop.classList.remove('show');
			setTimeout(function () { backdrop.hidden = true; }, 260);
		}
	}

	var fab = $('#kh-fab');
	if (fab) { fab.addEventListener('click', openPanel); }

	var pclose = $('#kh-panel-close');
	if (pclose) { pclose.addEventListener('click', closePanel); }

	if (backdrop) { backdrop.addEventListener('click', closePanel); }

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { closePanel(); closeCard(); }
	});

	/* ---------- boot ---------- */
	initMap();

	fetch(CFG.endpoint + '?limit=1000&arsip=1', { headers: { Accept: 'application/json' } })
		.then(function (r) { return r.json(); })
		.then(function (res) {
			var wilParam = (location.search.match(/[?&]wil=([^&]+)/) || [])[1];
			if (wilParam) {
				filt = decodeURIComponent(wilParam).toLowerCase().replace(/[^a-z0-9]+/g, '-');
			}

			D = (res.items || [])
				.filter(function (x) { return x.lat && x.lng; })
				.map(function (x, idx) {
					return {
						i: idx,
						pid: x.id,
						plat: x.plat || '',
						merk: x.merk || '',
						model: x.model || '',
						tahun: x.tahun || '',
						warna: x.warna || '',
						wilayah: x.wilayah || '',
						lokasi: x.lokasi || '',
						waktu: waktuIndo(x.tanggal, x.jam),
						tanggal: x.tanggal || '',
						jam: x.jam || '',
						polsek: x.polsek || '',
						status: x.status || 'Hilang',
						verifikasi: x.verifikasi || 'warga',
						arsip: !!x.arsip,
						umur_hari: x.umur_hari || 0,
						lat: parseFloat(x.lat),
						lng: parseFloat(x.lng),
						judul: x.judul || '',
						url: x.url || ''
					};
				});

			buildChips();
			render();

			var deepId = new URLSearchParams(location.search).get('laporan');
			if (deepId) {
				openFromUrl();
			} else if (map) {
				fitAll();
			}

			if (!map) { $('#boot').classList.add('hide'); }
		})
		.catch(function (err) {
			$('#boot').textContent = 'GAGAL MEMUAT DATA';
			console.error('[kh]', err);
		});

	/* buka laporan dari ?laporan=ID */
	function openFromUrl() {
		var p = new URLSearchParams(location.search).get('laporan');
		if (!p) { return; }
		var idx = -1;
		D.forEach(function (d, k) { if (String(d.pid) === String(p)) { idx = k; } });
		if (idx >= 0) {
			select(idx, true, true);
		}
	}

	function buildChips() {
		var seen = {}, extra = '';
		D.forEach(function (d) {
			if (!d.wilayah || seen[d.wilayah]) { return; }
			seen[d.wilayah] = 1;
			var slug = d.wilayah.toLowerCase().replace(/[^a-z0-9]+/g, '-');
			extra += '<button type="button" class="chip' + (slug === filt ? ' on' : '') + '" data-f="' +
				esc(slug) + '">' + esc(d.wilayah) + '</button>';
		});
		$('#chips').innerHTML =
			'<button type="button" class="chip' + (filt === 'all' ? ' on' : '') + '" data-f="all">Semua</button>' + extra +
			'<button type="button" class="chip' + (filt === 'got' ? ' on' : '') + '" data-f="got">Ditemukan</button>';
		/* Beri tahu pengatur petunjuk geser bahwa isi chip berubah. */
		document.dispatchEvent(new Event('kh:chips'));
	}
})();
