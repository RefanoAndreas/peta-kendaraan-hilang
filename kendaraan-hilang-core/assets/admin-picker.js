/* Pemilih titik peta di layar edit kendaraan (mendukung ACF dan meta box native) */
(function () {
	'use strict';

	/* konfigurasi dari wp_localize_script (endpoint pencarian) */
	var CFG = window.KH_ADMIN || {};

	function pick(selectors) {
		for (var i = 0; i < selectors.length; i++) {
			var el = document.querySelector(selectors[i]);
			if (el) { return el; }
		}
		return null;
	}

	function latInput() { return pick(['.acf-field[data-name="kh_lat"] input', '#kh_lat']); }
	function lngInput() { return pick(['.acf-field[data-name="kh_lng"] input', '#kh_lng']); }
	function lokasiInput() {
		return pick([
			'.acf-field[data-name="kh_lokasi"] input',
			'.acf-field[data-name="kh_lokasi"] textarea',
			'#kh_lokasi'
		]);
	}

	function fireChange(el) {
		if (!el) { return; }
		if (window.jQuery) {
			window.jQuery(el).trigger('change').trigger('input');
		} else {
			el.dispatchEvent(new Event('change', { bubbles: true }));
			el.dispatchEvent(new Event('input', { bubbles: true }));
		}
	}

	function mount() {
		var latEl = latInput();
		var lngEl = lngInput();
		if (!latEl || !lngEl) { return false; }
		if (document.getElementById('kh-picker')) { return true; }

		/* container: kotak pencarian + peta, disisipkan setelah field longitude */
		var wrap = document.createElement('div');
		wrap.id = 'kh-picker-wrap';
		wrap.style.cssText = 'margin-top:14px';

		var bar = document.createElement('div');
		bar.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;max-width:640px';

		var q = document.createElement('input');
		q.type = 'search';
		q.id = 'kh-pk-q';
		q.placeholder = 'Cari nama jalan / lokasi… (mis. Rungkut Asri)';
		q.style.cssText = 'flex:1;padding:6px 10px;border:1px solid #8c8f94;border-radius:4px';

		var go = document.createElement('button');
		go.type = 'button';
		go.id = 'kh-pk-go';
		go.className = 'button';
		go.textContent = 'Cari';

		bar.appendChild(q);
		bar.appendChild(go);

		var list = document.createElement('ul');
		list.id = 'kh-pk-list';
		list.hidden = true;
		list.style.cssText = 'max-height:190px;overflow:auto;margin:0 0 8px;padding:0;list-style:none;'
			+ 'border:1px solid #c3c4c7;border-radius:4px;background:#fff;max-width:640px';

		var box = document.createElement('div');
		box.id = 'kh-picker';
		box.style.cssText = 'height:340px;border:1px solid #c3c4c7;border-radius:4px';

		var info = document.createElement('p');
		info.id = 'kh-pk-info';
		info.className = 'description';
		info.style.margin = '8px 0 0';
		info.textContent = 'Cari nama jalan di atas, atau klik peta untuk menaruh pin. Latitude & longitude terisi otomatis.';

		wrap.appendChild(bar);
		wrap.appendChild(list);
		wrap.appendChild(box);
		wrap.appendChild(info);

		/* Sisipkan SETELAH seluruh baris field ACF (bukan di dalam baris flex
		   yang lebarnya hanya 25% per field) supaya peta melebar penuh. */
		var lngWrap = lngEl.closest ? lngEl.closest('.acf-field') : null;
		var fieldsBox = lngEl.closest ? (lngEl.closest('.acf-fields') || null) : null;

		if (fieldsBox) {
			wrap.style.cssText = 'margin-top:14px;clear:both;width:100%';
			fieldsBox.appendChild(wrap);
		} else if (lngWrap && lngWrap.parentNode) {
			lngWrap.parentNode.insertBefore(wrap, lngWrap.nextSibling);
		} else if (lngEl.closest && lngEl.closest('tr')) {
			var tr = document.createElement('tr');
			var td = document.createElement('td');
			td.colSpan = 2;
			td.appendChild(wrap);
			tr.appendChild(td);
			lngEl.closest('tr').parentNode.appendChild(tr);
		} else {
			return false;
		}

		var lat = parseFloat(latEl.value);
		var lng = parseFloat(lngEl.value);
		var has = !isNaN(lat) && !isNaN(lng);
		var center = has ? [lat, lng] : [-7.2575, 112.7521];

		var map = L.map(box).setView(center, has ? 15 : 10);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap'
		}).addTo(map);

		var marker = has ? L.marker(center).addTo(map) : null;

		function setFields(ll) {
			latEl.value = ll.lat.toFixed(6);
			lngEl.value = ll.lng.toFixed(6);
			fireChange(latEl);
			fireChange(lngEl);
		}

		function placeAt(ll, zoom) {
			var p = L.latLng(ll); /* terima array [lat,lng] maupun LatLng */
			if (marker) { marker.setLatLng(p); } else { marker = L.marker(p).addTo(map); }
			map.setView(p, zoom || Math.max(map.getZoom(), 16));
			setFields(p);
		}

		map.on('click', function (e) {
			placeAt(e.latlng, map.getZoom());
		});

		/* kalau koordinat diubah manual, geser marker */
		function syncFromInputs() {
			var la = parseFloat(latEl.value);
			var ln = parseFloat(lngEl.value);
			if (isNaN(la) || isNaN(ln)) { return; }
			var p = L.latLng(la, ln);
			if (marker) { marker.setLatLng(p); } else { marker = L.marker(p).addTo(map); }
			map.setView(p, Math.max(map.getZoom(), 14));
		}
		latEl.addEventListener('change', syncFromInputs);
		lngEl.addEventListener('change', syncFromInputs);

		/* ---------- pencarian jalan/lokasi ---------- */
		var seq = 0;
		var timer = null;

		function tutup() {
			list.hidden = true;
			list.innerHTML = '';
		}

		function render(items) {
			list.innerHTML = '';
			if (!items.length) {
				var kosong = document.createElement('li');
				kosong.style.cssText = 'padding:8px 10px;color:#646970';
				kosong.textContent = 'Tidak ditemukan. Coba kata kunci lain atau klik langsung peta.';
				list.appendChild(kosong);
				list.hidden = false;
				return;
			}

			items.forEach(function (it) {
				var li = document.createElement('li');
				var b = document.createElement('button');
				b.type = 'button';
				b.textContent = it.label;
				b.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 10px;border:0;'
					+ 'border-bottom:1px solid #f0f0f1;background:#fff;cursor:pointer;font-size:13px';
				b.addEventListener('mouseenter', function () { b.style.background = '#f6f7f7'; });
				b.addEventListener('mouseleave', function () { b.style.background = '#fff'; });
				b.addEventListener('click', function () {
					placeAt([it.lat, it.lng], 16);

					/* isi field lokasi hanya kalau masih kosong */
					var lok = lokasiInput();
					if (lok && !lok.value.trim()) {
						lok.value = it.label.split('·')[0].trim();
						fireChange(lok);
					}

					info.textContent = 'Titik dipindah ke: ' + it.label;
					q.value = it.label.split('·')[0].trim();
					tutup();
				});
				li.appendChild(b);
				list.appendChild(li);
			});
			list.hidden = false;
		}

		function cari() {
			var teks = (q.value || '').trim();
			if (teks.length < 3) {
				info.textContent = 'Tulis minimal 3 huruf untuk mencari.';
				tutup();
				return;
			}
			if (!CFG.cari) { return; }

			var my = ++seq;
			info.textContent = 'Mencari “' + teks + '”…';

			fetch(CFG.cari + (CFG.cari.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(teks), {
				headers: { Accept: 'application/json' },
				credentials: 'same-origin'
			})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (my !== seq) { return; }
					var items = (res && res.items) || [];
					render(items);
					info.textContent = items.length
						? items.length + ' lokasi ditemukan — pilih salah satu.'
						: 'Lokasi tidak ditemukan. Coba kata kunci lain atau klik peta.';
				})
				.catch(function () {
					if (my !== seq) { return; }
					info.textContent = 'Pencarian gagal. Periksa koneksi lalu coba lagi.';
				});
		}

		go.addEventListener('click', cari);
		q.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); cari(); }
			if (e.key === 'Escape') { tutup(); }
		});
		q.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				if ((q.value || '').trim().length >= 3) { cari(); } else { tutup(); }
			}, 650);
		});

		setTimeout(function () { map.invalidateSize(); }, 400);
		return true;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	function boot() {
		var tries = 0;
		(function attempt() {
			if (mount()) { return; }
			if (++tries < 24) { setTimeout(attempt, 250); }
		})();
	}
})();
