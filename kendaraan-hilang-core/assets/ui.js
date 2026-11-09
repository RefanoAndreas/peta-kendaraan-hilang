/* Peta Kendaraan Hilang — peta vektor (MapLibre) + data REST WordPress */
(function () {
	'use strict';

	var CFG = window.KH_UI || {};
	var $ = function (s) { return document.querySelector(s); };
	if (!CFG.endpoint) { console.warn('[kh] endpoint kosong'); return; }

	/* ---------- state ---------- */
	var D = [];
	var filt = 'all', qs = '', sel = -1, periode = 'all';
	var map = null, markers = [], popup = null, meMarker = null, meAt = null;
	var lastZoom = null, moveTimer = null;

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

	function colorOf(d) {
		var s = stateOf(d);
		return s === 'got' ? '#35f0c8' : (s === 'rep' ? '#2fb4ff' : '#ff5a3c');
	}

	function tagOf(d) {
		var s = stateOf(d);
		if (s === 'got') { return { text: 'DITEMUKAN', cls: ' got' }; }
		if (s === 'rep') { return { text: 'SUDAH LAPOR', cls: ' rep' }; }
		return { text: 'BELUM LAPOR', cls: '' };
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

	function markerEl(d) {
		var wrap = document.createElement('div');
		wrap.className = 'mk-hex mk-' + stateOf(d);
		wrap.setAttribute('title', d.plat || '');
		wrap.innerHTML =
			'<svg viewBox="-20 -20 40 40" width="34" height="34" aria-hidden="true">' +
			'<polygon class="pulse" points="' + HEX_PTS + '" fill="currentColor" fill-opacity=".45"/>' +
			'<polygon points="' + HEX_PTS + '" fill="currentColor" fill-opacity=".26" stroke="currentColor" stroke-width="2"/>' +
			'<polygon points="' + HEX_CORE + '" fill="none" stroke="currentColor" stroke-width="1.2"/>' +
			'<circle r="2.4" fill="#fff"/>' +
			'</svg>' +
			(d.plat ? '<span class="plat-tag">' + esc(d.plat) + '</span>' : '');
		return wrap;
	}

	function clusterEl(count) {
		var wrap = document.createElement('div');
		wrap.className = 'mk-cluster';
		wrap.innerHTML =
			'<svg viewBox="-26 -26 52 52" width="46" height="46" aria-hidden="true">' +
			'<polygon points="0,-20 17.3,-10 17.3,10 0,20 -17.3,10 -17.3,-10" fill="currentColor" fill-opacity=".18" stroke="currentColor" stroke-width="2"/>' +
			'<text class="cnt" x="0" y="0">' + count + '</text>' +
			'</svg>';
		return wrap;
	}

	function popupHtml(d) {
		var c = colorOf(d);
		return '<div class="pop-plat" style="color:' + c + '">' + esc(d.plat) + '</div>' +
			'<div class="pop-row">' + esc(kendaraan(d)) + (d.warna ? ' · ' + esc(d.warna) : '') + '</div>' +
			'<div class="pop-row">' + esc(d.lokasi || '') + '</div>' +
			'<div class="pop-row">' + esc(d.waktu || '') + '</div>' +
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

		map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-left');
		window.KH_ML = map;

		map.on('load', function () { $('#boot').classList.add('hide'); });

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

	/* ---------- clustering sederhana ---------- */
	function clusterMarkers(items) {
		if (!map) { return []; }
		var z = Math.min(16, Math.max(8, Math.round(map.getZoom())));
		var cell = 0.082 * Math.pow(2, 10 - z); /* ≈60px per sel */
		var buckets = {};

		items.forEach(function (d) {
			var kx = Math.floor(d.lng / cell);
			var ky = Math.floor(d.lat / cell);
			var k = kx + ':' + ky;
			if (!buckets[k]) { buckets[k] = []; }
			buckets[k].push(d);
		});

		return Object.keys(buckets).map(function (k) { return buckets[k]; });
	}

	function renderMarkers(items) {
		if (!map) { return; }
		markers.forEach(function (m) { m.remove(); });
		markers = [];

		clusterMarkers(items).forEach(function (group) {
			if (group.length === 1) {
				var d = group[0];
				var node = markerEl(d);
				var mk = new maplibregl.Marker({ element: node, anchor: 'center' })
					.setLngLat([d.lng, d.lat])
					.addTo(map);
				node.addEventListener('click', function (ev) {
					ev.stopPropagation();
					select(d.i, true);
				});
				markers.push(mk);
				return;
			}

			var lat = group.reduce(function (s, x) { return s + x.lat; }, 0) / group.length;
			var lng = group.reduce(function (s, x) { return s + x.lng; }, 0) / group.length;
			var el = clusterEl(group.length);
			var cm = new maplibregl.Marker({ element: el, anchor: 'center' })
				.setLngLat([lng, lat])
				.addTo(map);
			el.addEventListener('click', function (ev) {
				ev.stopPropagation();
				map.easeTo({
					center: [lng, lat],
					zoom: Math.min(17, map.getZoom() + 2),
					duration: 600
				});
			});
			markers.push(cm);
		});
	}

	/* ---------- filter ---------- */
	function visible() {
		var q = qs;
		return D.filter(function (d) {
			if (periode !== 'all' && daysAgo(d.tanggal) > parseInt(periode, 10)) { return false; }
			if (filt === 'open' && stateOf(d) !== 'open') { return false; }
			if (filt === 'rep' && stateOf(d) !== 'rep') { return false; }
			if (filt === 'got' && stateOf(d) !== 'got') { return false; }
			if (filt !== 'all' && filt !== 'open' && filt !== 'rep' && filt !== 'got') {
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
		return '<div class="item' + (st === 'open' ? '' : (st === 'rep' ? ' rep' : ' got')) +
			(d.i === sel ? ' sel' : '') + '" data-i="' + d.i + '">' +
			'<div class="row"><span class="plat">' + esc(d.plat) + '</span>' +
			'<span class="tag' + tg.cls + '">' + tg.text + '</span></div>' +
			'<div class="m">' + esc(kendaraan(d)) + (d.warna ? ' · ' + esc(d.warna) : '') + '</div>' +
			'<div class="s">' + esc(d.lokasi || '') + (d.waktu ? '<br>' + esc(d.waktu) : '') + '</div>' +
			'</div>';
	}

	function render() {
		var items = visible();
		var belum = 0, lapor = 0;

		items.forEach(function (d) {
			var s = stateOf(d);
			if (s === 'open') { belum++; } else { lapor++; }
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
					'<span class="tl-dot tl-' + stateOf(d) + '"></span>' +
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
		$('#card').classList.add('show');

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
			popup.setLngLat([d.lng, d.lat]).setHTML(popupHtml(d)).addTo(map);
		}

		var it = document.querySelector('#list .item.sel');
		if (it) { it.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }

		if (window.innerWidth <= 760) { closePanel(); }
	}

	function closeCard() {
		sel = -1;
		$('#card').classList.remove('show');
		Array.prototype.forEach.call(document.querySelectorAll('#list .item'), function (el) {
			el.classList.remove('sel');
		});
		Array.prototype.forEach.call(document.querySelectorAll('#timeline .tl-item'), function (el) {
			el.classList.remove('sel');
		});
		if (popup) { popup.remove(); }
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
		var it = e.target.closest('.item');
		if (it) { select(parseInt(it.dataset.i, 10), true); }
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

	fetch(CFG.endpoint + '?limit=1000', { headers: { Accept: 'application/json' } })
		.then(function (r) { return r.json(); })
		.then(function (res) {
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
			extra += '<button type="button" class="chip" data-f="' +
				esc(d.wilayah.toLowerCase().replace(/[^a-z0-9]+/g, '-')) + '">' + esc(d.wilayah) + '</button>';
		});
		$('#chips').innerHTML = '<button type="button" class="chip on" data-f="all">Semua</button>' + extra +
			'<button type="button" class="chip" data-f="open">Belum lapor</button>' +
			'<button type="button" class="chip" data-f="rep">Sudah lapor</button>';
	}
})();
