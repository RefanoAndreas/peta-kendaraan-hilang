(function () {
	'use strict';

	var cfg = window.KH_MAP_CFG || {};
	var el = document.getElementById('kh-map');
	if (!el || typeof L === 'undefined') { return; }

	if (!cfg.endpoint && el.dataset.endpoint) { cfg.endpoint = el.dataset.endpoint; }
	if (!cfg.ringkasan && el.dataset.ringkasan) { cfg.ringkasan = el.dataset.ringkasan; }
	if (!cfg.jenis && el.dataset.jenis) { cfg.jenis = el.dataset.jenis; }
	if (!cfg.wilayah && el.dataset.wilayah) { cfg.wilayah = el.dataset.wilayah; }
	if (!cfg.status && el.dataset.status) { cfg.status = el.dataset.status; }

	if (!cfg.endpoint) {
		var apiLink = document.querySelector('link[rel="https://api.w.org/"]');
		if (apiLink) {
			var root = apiLink.href.replace(/\/$/, '');
			cfg.endpoint = root + '/kh/v1/peta';
			cfg.ringkasan = root + '/kh/v1/ringkasan';
		}
	}
	if (!cfg.endpoint) { console.warn('[kh-map] endpoint REST tidak ditemukan'); return; }

	var center = [-7.3, 112.72];
	var map = L.map(el, { scrollWheelZoom: true }).setView(center, 10);

	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; kontributor <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
	}).addTo(map);

	var cluster = (typeof L.markerClusterGroup === 'function')
		? L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45 })
		: L.layerGroup();
	map.addLayer(cluster);

	var selJenis = document.getElementById('kh-filter-jenis');
	var selStatus = document.getElementById('kh-filter-status');
	var selWilayah = document.getElementById('kh-filter-wilayah');
	var countEl = document.getElementById('kh-count');

	function esc(s) {
		return String(s === undefined || s === null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	function popupHtml(it) {
		var kendaraan = [it.merk, it.model, it.tahun].filter(Boolean).join(' ');
		var waktu = [it.tanggal, it.jam].filter(Boolean).join(' ');
		var html = '<div class="kh-pop">';
		if (it.plat) { html += '<div class="kh-pop-plat">' + esc(it.plat) + '</div>'; }
		if (kendaraan) { html += '<div class="kh-pop-title">' + esc(kendaraan) + '</div>'; }
		if (it.warna) { html += '<div class="kh-pop-row">Warna: ' + esc(it.warna) + '</div>'; }
		if (waktu) { html += '<div class="kh-pop-row">' + esc(waktu) + ' WIB</div>'; }
		if (it.lokasi) { html += '<div class="kh-pop-row">' + esc(it.lokasi) + '</div>'; }
		html += '<div class="kh-pop-row"><a href="' + esc(it.url) + '">Lihat detail</a></div>';
		html += '</div>';
		return html;
	}

	function render(items) {
		cluster.clearLayers();
		var bounds = [];
		items.forEach(function (it) {
			if (!it.lat || !it.lng) { return; }
			var m = L.marker([it.lat, it.lng]);
			m.bindPopup(popupHtml(it), { maxWidth: 280 });
			cluster.addLayer(m);
			bounds.push([it.lat, it.lng]);
		});
		if (bounds.length) {
			map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
		}
		if (countEl) {
			countEl.textContent = items.length + ' laporan ditampilkan';
		}
	}

	function load() {
		var p = new URLSearchParams();
		if (selJenis && selJenis.value) { p.set('jenis', selJenis.value); }
		if (selStatus && selStatus.value) { p.set('status', selStatus.value); }
		if (selWilayah && selWilayah.value) { p.set('wilayah', selWilayah.value); }
		var url = cfg.endpoint + (p.toString() ? '?' + p.toString() : '');
		fetch(url, { headers: { Accept: 'application/json' } })
			.then(function (r) { return r.json(); })
			.then(function (d) { render(Array.isArray(d.items) ? d.items : []); })
			.catch(function (e) {
				if (countEl) { countEl.textContent = 'Gagal memuat data peta.'; }
				console.error('[kh-map]', e);
			});
	}

	[selJenis, selStatus, selWilayah].forEach(function (s) {
		if (s) { s.addEventListener('change', load); }
	});

	if (cfg.ringkasan && selWilayah) {
		fetch(cfg.ringkasan, { headers: { Accept: 'application/json' } })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				(d.wilayah || []).forEach(function (name) {
					var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
					var o = document.createElement('option');
					o.value = slug;
					o.textContent = name;
					selWilayah.appendChild(o);
				});
			})
			.catch(function () {});
	}

	load();
	setTimeout(function () { map.invalidateSize(); }, 300);
})();
