/* Peta + pencarian jalan di form "Edit Cepat" kendaraan.
 *
 * Catatan: WordPress meng-clone baris template #inline-edit setiap kali form
 * dibuka (id elemen jadi ganda), jadi semua pencarian elemen memakai class dan
 * dibatasi ke baris yang sedang terbuka (tr.inline-edit-row ber-id edit-N).
 */
(function () {
	'use strict';

	var CFG = window.KH_QE || {};
	var map = null;
	var marker = null;
	var seq = 0;
	var timer = null;
	var labelTerpilih = '';

	function barisAktif() {
		var rows = document.querySelectorAll('tr.inline-edit-row');
		for (var i = rows.length - 1; i >= 0; i--) {
			var r = rows[i];
			if (r.id && r.id.indexOf('edit-') === 0 && r.offsetParent !== null) { return r; }
		}
		return null;
	}

	function q(cls) {
		var r = barisAktif();
		return r ? r.querySelector('.' + cls) : null;
	}

	function input() {
		return { lat: q('kh-qe-lat'), lng: q('kh-qe-lng'), lok: q('kh-qe-lokasi') };
	}

	function info(teks) {
		var i = q('kh-qe-info');
		if (i) { i.textContent = teks; }
	}

	function tutupDaftar() {
		var l = q('kh-qe-list');
		if (l) { l.hidden = true; l.innerHTML = ''; }
	}

	function bersihkanPeta() {
		if (map) { try { map.remove(); } catch (e) {} }
		map = null;
		marker = null;
	}

	function taruhPin(ll, zoom) {
		if (!map) { return; }
		var p = (ll && typeof ll.lat === 'number') ? ll : L.latLng(ll);
		if (marker) { marker.setLatLng(p); } else { marker = L.marker(p).addTo(map); }
		map.setView(p, zoom || Math.max(map.getZoom(), 16));
		var f = input();
		if (f.lat) { f.lat.value = p.lat.toFixed(6); }
		if (f.lng) { f.lng.value = p.lng.toFixed(6); }
	}

	function buatPeta() {
		var box = q('kh-qe-map');
		if (!box || map || typeof L === 'undefined') { return; }

		var f = input();
		var la = parseFloat(f.lat && f.lat.value);
		var ln = parseFloat(f.lng && f.lng.value);
		var ada = !isNaN(la) && !isNaN(ln) && la !== 0 && ln !== 0;

		map = L.map(box, { scrollWheelZoom: false }).setView(ada ? [la, ln] : [-7.2575, 112.7521], ada ? 15 : 10);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap'
		}).addTo(map);
		if (ada) { marker = L.marker([la, ln]).addTo(map); }

		map.on('click', function (e) {
			taruhPin(e.latlng);
			info('Pin dipindah. Latitude & longitude sudah diperbarui.');
		});

		/* baris baru tampil: ukuran kontainer perlu dihitung ulang */
		setTimeout(function () { if (map) { map.invalidateSize(); } }, 200);
	}

	function render(items) {
		var l = q('kh-qe-list');
		if (!l) { return; }
		l.innerHTML = '';
		if (!items.length) {
			var kosong = document.createElement('li');
			kosong.style.cssText = 'padding:8px 10px;color:#646970';
			kosong.textContent = 'Tidak ditemukan. Coba kata kunci lain atau klik langsung peta.';
			l.appendChild(kosong);
			l.hidden = false;
			return;
		}
		items.forEach(function (it) {
			var li = document.createElement('li');
			var b = document.createElement('button');
			b.type = 'button';
			b.textContent = it.label;
			b.addEventListener('click', function () {
				taruhPin([it.lat, it.lng], 16);
				labelTerpilih = it.label.split('·')[0].trim();
				var pakai = q('kh-qe-pakai');
				if (pakai) { pakai.hidden = false; }
				info('Titik dipindah ke: ' + it.label);
				var f = input();
				if (f.lok && !f.lok.value.trim()) { f.lok.value = labelTerpilih; }
				tutupDaftar();
			});
			li.appendChild(b);
			l.appendChild(li);
		});
		l.hidden = false;
	}

	/* ---- koordinat dari URL Google Maps (tanpa API key) ---- */
	function koordinatDari(teks) {
		if (!CFG.koordinat) { return Promise.reject(new Error('Endpoint koordinat tidak tersedia.')); }
		return fetch(CFG.koordinat, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				'X-WP-Nonce': CFG.nonce || ''
			},
			credentials: 'same-origin',
			body: JSON.stringify({ url: teks })
		}).then(function (r) {
			return r.json().then(function (js) {
				if (!r.ok) {
					var pesan = (js && (js.message || (js.data && js.data.message))) || ('HTTP ' + r.status);
					throw new Error(pesan);
				}
				return js;
			});
		});
	}

	function ambilUrl() {
		var kotak = q('kh-qe-url');
		var teks = ((kotak && kotak.value) || '').trim();
		if (teks.length < 8) {
			info('Tempel dulu URL Google Maps-nya.');
			return;
		}

		info('Membaca koordinat dari URL…');
		koordinatDari(teks).then(function (res) {
			var la = parseFloat(res.lat);
			var ln = parseFloat(res.lng);

			if (isNaN(la) || isNaN(ln)) {
				var cariKotak = q('kh-qe-q');
				if (res.nama && cariKotak) {
					cariKotak.value = res.nama;
					info('URL tidak memuat koordinat — mencari “' + res.nama + '” lewat pencarian nama…');
					cari();
				} else {
					info('URL tidak memuat koordinat. Di Google Maps: klik kanan titik lokasi → Copy link.');
				}
				return;
			}

			taruhPin([la, ln], 17);
			var f = input();
			if (f.lok && !f.lok.value.trim() && res.nama) {
				f.lok.value = res.nama;
			}
			info('Koordinat terisi: ' + la.toFixed(6) + ', ' + ln.toFixed(6)
				+ (res.nama ? ' — ' + res.nama : '') + ' · klik Perbarui untuk menyimpan.');
		}).catch(function (e) {
			info('Gagal membaca URL: ' + (e && e.message ? e.message : 'tidak diketahui'));
		});
	}

	function cari() {
		var kotak = q('kh-qe-q');
		var teks = ((kotak && kotak.value) || '').trim();
		if (teks.length < 3) {
			info('Tulis minimal 3 huruf untuk mencari.');
			tutupDaftar();
			return;
		}
		if (!CFG.cari) { return; }

		var saya = ++seq;
		info('Mencari “' + teks + '”…');

		fetch(CFG.cari + (CFG.cari.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(teks), {
			headers: { Accept: 'application/json' },
			credentials: 'same-origin'
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (saya !== seq) { return; }
				var items = (res && res.items) || [];
				render(items);
				info(items.length ? items.length + ' lokasi ditemukan — pilih salah satu.' : 'Lokasi tidak ditemukan. Coba kata kunci lain atau klik peta.');
			})
			.catch(function () {
				if (saya !== seq) { return; }
				info('Pencarian gagal. Periksa koneksi lalu coba lagi.');
			});
	}

	function pasangEvent() {
		var go = q('kh-qe-go');
		var kotak = q('kh-qe-q');
		var pakai = q('kh-qe-pakai');
		var ambil = q('kh-qe-ambil');
		var urlKotak = q('kh-qe-url');
		var f = input();

		if (go) { go.addEventListener('click', cari); }
		if (ambil) { ambil.addEventListener('click', ambilUrl); }
		if (urlKotak) {
			urlKotak.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); ambilUrl(); }
			});
			/* tempel URL → langsung dibaca, tidak perlu klik tombol */
			urlKotak.addEventListener('paste', function () { setTimeout(ambilUrl, 80); });
		}

		if (kotak) {
			kotak.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); cari(); }
				if (e.key === 'Escape') { tutupDaftar(); }
			});
			kotak.addEventListener('input', function () {
				clearTimeout(timer);
				timer = setTimeout(function () {
					if ((kotak.value || '').trim().length >= 3) { cari(); } else { tutupDaftar(); }
				}, 650);
			});
		}

		if (pakai) {
			pakai.addEventListener('click', function () {
				var fx = input();
				if (fx.lok && labelTerpilih) {
					fx.lok.value = labelTerpilih;
					info('Nama jalan dimasukkan ke kolom Lokasi kejadian.');
				}
			});
		}

		['lat', 'lng'].forEach(function (k) {
			if (!f[k]) { return; }
			f[k].addEventListener('change', function () {
				var la = parseFloat(f.lat.value);
				var ln = parseFloat(f.lng.value);
				if (isNaN(la) || isNaN(ln)) { return; }
				taruhPin([la, ln], map ? Math.max(map.getZoom(), 14) : 15);
			});
		});
	}

	function siapkan(pid) {
		var editRow = document.getElementById('edit-' + pid) || barisAktif();
		if (!editRow) { return; }

		var dataRow = document.getElementById('post-' + pid);
		var data = dataRow ? dataRow.querySelector('.kh-qe-data') : null;
		var f = input();
		if (!f.lat || !f.lng) { return; }

		f.lat.value = data ? (data.getAttribute('data-lat') || '') : '';
		f.lng.value = data ? (data.getAttribute('data-lng') || '') : '';
		if (f.lok) { f.lok.value = data ? (data.getAttribute('data-lokasi') || '') : ''; }

		var kotak = q('kh-qe-q');
		if (kotak) { kotak.value = ''; }
		var urlKotak = q('kh-qe-url');
		if (urlKotak) { urlKotak.value = ''; }
		labelTerpilih = '';
		var pakai = q('kh-qe-pakai');
		if (pakai) { pakai.hidden = true; }
		tutupDaftar();
		info('Cari nama jalan atau klik peta untuk menaruh pin.');

		if (!editRow.getAttribute('data-kh-siap')) {
			pasangEvent();
			editRow.setAttribute('data-kh-siap', '1');
		}

		bersihkanPeta();
		buatPeta();
	}

	function pasang() {
		if (!window.inlineEditPost || typeof window.inlineEditPost.edit !== 'function' || window.inlineEditPost.__khPatched) {
			return;
		}
		var asli = window.inlineEditPost.edit;
		window.inlineEditPost.edit = function (id) {
			var hasil = asli.apply(this, arguments);
			var pid = 0;
			try {
				pid = (id && typeof id === 'object') ? parseInt(this.getId(id), 10) : parseInt(id, 10);
			} catch (e) { pid = 0; }
			if (pid) { setTimeout(function () { siapkan(pid); }, 40); }
			return hasil;
		};
		window.inlineEditPost.__khPatched = true;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', pasang);
	} else {
		pasang();
	}
})();
