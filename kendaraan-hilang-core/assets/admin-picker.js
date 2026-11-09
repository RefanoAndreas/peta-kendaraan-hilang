/* Pemilih titik peta di layar edit kendaraan (mendukung ACF dan meta box native) */
(function () {
	'use strict';

	function pick(selectors) {
		for (var i = 0; i < selectors.length; i++) {
			var el = document.querySelector(selectors[i]);
			if (el) { return el; }
		}
		return null;
	}

	function latInput() { return pick(['.acf-field[data-name="kh_lat"] input', '#kh_lat']); }
	function lngInput() { return pick(['.acf-field[data-name="kh_lng"] input', '#kh_lng']); }

	function mount() {
		var latEl = latInput();
		var lngEl = lngInput();
		if (!latEl || !lngEl) { return false; }
		if (document.getElementById('kh-picker')) { return true; }

		/* container: setelah field longitude (ACF) atau setelah baris native */
		var box = document.createElement('div');
		box.id = 'kh-picker';
		box.style.cssText = 'height:340px;margin-top:14px;border:1px solid #c3c4c7;border-radius:4px';

		var host = null;
		var lngWrap = lngEl.closest ? lngEl.closest('.acf-field') : null;
		if (lngWrap && lngWrap.parentNode) {
			host = lngWrap.parentNode;
		} else if (lngEl.closest && lngEl.closest('tr')) {
			host = lngEl.closest('tr').parentNode;
			var tr = document.createElement('tr');
			var td = document.createElement('td');
			td.colSpan = 2;
			td.appendChild(box);
			tr.appendChild(td);
			host.appendChild(tr);
			host = null;
		}

		if (host) {
			var note = document.createElement('p');
			note.className = 'description';
			note.style.margin = '10px 0 0';
			note.textContent = 'Klik peta untuk mengisi Latitude & Longitude otomatis. Geser/zoom dulu bila perlu.';
			host.appendChild(note);
			host.appendChild(box);
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
			/* ACF menandai form berubah lewat event jQuery */
			if (window.jQuery) {
				window.jQuery(latEl).trigger('change').trigger('input');
				window.jQuery(lngEl).trigger('change').trigger('input');
			} else {
				latEl.dispatchEvent(new Event('change', { bubbles: true }));
				lngEl.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}

		map.on('click', function (e) {
			if (marker) { marker.setLatLng(e.latlng); } else { marker = L.marker(e.latlng).addTo(map); }
			setFields(e.latlng);
		});

		/* kalau koordinat diubah manual, geser marker */
		function syncFromInputs() {
			var la = parseFloat(latEl.value);
			var ln = parseFloat(lngEl.value);
			if (isNaN(la) || isNaN(ln)) { return; }
			if (marker) { marker.setLatLng([la, ln]); } else { marker = L.marker([la, ln]).addTo(map); }
			map.setView([la, ln], Math.max(map.getZoom(), 14));
		}
		latEl.addEventListener('change', syncFromInputs);
		lngEl.addEventListener('change', syncFromInputs);

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
