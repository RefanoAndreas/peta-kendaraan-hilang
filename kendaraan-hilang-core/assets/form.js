/* Form pelapor: Google Sign-In + peta pilih titik + kirim ke REST */
(function () {
	'use strict';

	var CFG = window.KH_FORM || {};
	var form = document.getElementById('kh-form');
	if (!form) { return; }

	var submitBtn = document.getElementById('kh-submit');
	var msg = document.getElementById('kh-msg');
	var authStatus = document.getElementById('auth-status');
	var latEl = document.getElementById('f-lat');
	var lngEl = document.getElementById('f-lng');
	var picked = false;

	/* ---------------- peta pilih titik ---------------- */
	var mapEl = document.getElementById('kh-form-map');
	var marker = null;

	if (mapEl && typeof L !== 'undefined') {
		var map = L.map(mapEl, { scrollWheelZoom: true }).setView([-7.27, 112.74], 11);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap'
		}).addTo(map);

		map.on('click', function (e) {
			if (marker) { marker.setLatLng(e.latlng); } else { marker = L.marker(e.latlng).addTo(map); }
			latEl.value = e.latlng.lat.toFixed(6);
			lngEl.value = e.latlng.lng.toFixed(6);
			picked = true;
			var info = document.getElementById('pick-info');
			if (info) { info.textContent = 'Titik dipilih: ' + latEl.value + ', ' + lngEl.value; }
		});

		setTimeout(function () { map.invalidateSize(); }, 300);
		if (navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(function (pos) {
				map.setView([pos.coords.latitude, pos.coords.longitude], 13);
			}, function () {}, { timeout: 6000 });
		}

		/* ---------------- cari nama jalan (geocoding) ---------------- */
		var geoQ = document.getElementById('kh-geo-q');
		var geoBtn = document.getElementById('kh-geo-btn');
		var geoList = document.getElementById('kh-geo-list');
		var geoInfo = document.getElementById('pick-info');
		var geoSeq = 0;

		function geoPoint(lat, lng, label) {
			var ll = [lat, lng];
			if (marker) { marker.setLatLng(ll); } else { marker = L.marker(ll).addTo(map); }
			latEl.value = Number(lat).toFixed(6);
			lngEl.value = Number(lng).toFixed(6);
			picked = true;
			map.setView(ll, 16);
			if (geoInfo) {
				geoInfo.textContent = label ? 'Titik dipilih: ' + label : 'Titik dipilih: ' + latEl.value + ', ' + lngEl.value;
			}
			var lok = form.querySelector('[name="lokasi"]');
			if (lok && !lok.value.trim() && label) {
				lok.value = String(label).split('·')[0].trim();
			}
		}

		function geoClose() {
			if (geoList) { geoList.hidden = true; geoList.innerHTML = ''; }
		}

		function geoRender(items, q) {
			if (!geoList) { return; }
			geoList.innerHTML = '';
			if (!items.length) {
				geoList.innerHTML = '<li class="geo-empty">Tidak ditemukan. Coba kata kunci lain atau klik langsung peta.</li>';
				geoList.hidden = false;
				return;
			}
			items.forEach(function (it) {
				var li = document.createElement('li');
				var b = document.createElement('button');
				b.type = 'button';
				b.textContent = it.label;
				b.addEventListener('click', function () {
					geoPoint(it.lat, it.lng, it.label);
					geoClose();
					if (geoQ) { geoQ.value = it.label.split('·')[0].trim(); }
				});
				li.appendChild(b);
				geoList.appendChild(li);
			});
			geoList.hidden = false;
		}

		function geoCari() {
			var q = (geoQ && geoQ.value || '').trim();
			if (q.length < 3) {
				if (geoInfo) { geoInfo.textContent = 'Tulis minimal 3 huruf untuk mencari.'; }
				return;
			}
			if (!CFG.cari) { return; }
			var my = ++geoSeq;
			if (geoInfo) { geoInfo.textContent = 'Mencari “' + q + '”…'; }
			fetch(CFG.cari + (CFG.cari.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q), {
				headers: { Accept: 'application/json' }
			})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (my !== geoSeq) { return; }
					var items = (res && res.items) || [];
					geoRender(items, q);
					if (geoInfo && items.length) {
						geoInfo.textContent = items.length + ' lokasi ditemukan — pilih salah satu.';
					} else if (geoInfo) {
						geoInfo.textContent = 'Lokasi tidak ditemukan. Coba kata kunci lain atau klik peta.';
					}
				})
				.catch(function () {
					if (my !== geoSeq) { return; }
					if (geoInfo) { geoInfo.textContent = 'Pencarian gagal. Periksa koneksi lalu coba lagi.'; }
				});
		}

		if (geoBtn) { geoBtn.addEventListener('click', geoCari); }
		if (geoQ) {
			geoQ.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); geoCari(); }
				if (e.key === 'Escape') { geoClose(); }
			});
			geoQ.addEventListener('input', function () {
				clearTimeout(geoQ._t);
				if (geoQ.value.trim().length < 3) { geoClose(); return; }
				geoQ._t = setTimeout(geoCari, 550);
			});
		}
		document.addEventListener('click', function (e) {
			if (geoList && !geoList.hidden && !geoList.contains(e.target) && e.target !== geoQ && e.target !== geoBtn) {
				geoClose();
			}
		});
	}

	/* ---------------- Google Sign-In ---------------- */
	var credential = null;
	var profile = null;

	function parseJwt(t) {
		try {
			var p = t.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
			return JSON.parse(decodeURIComponent(escape(atob(p))));
		} catch (e) { return {}; }
	}

	function enableSubmit() { if (submitBtn) { submitBtn.disabled = false; } }

	/* kartu "Login sebagai …" */
	var userBox = document.createElement('div');
	userBox.className = 'kh-user';
	userBox.hidden = true;
	userBox.innerHTML =
		'<img class="kh-avatar" id="kh-avatar" alt="">' +
		'<div class="kh-user-txt">' +
		'<strong id="kh-user-name"></strong>' +
		'<span id="kh-user-email"></span>' +
		'</div>' +
		'<button type="button" class="kh-signout" id="kh-signout">Ganti akun</button>';

	var authBox = document.getElementById('kh-auth');
	if (authBox) { authBox.appendChild(userBox); }

	function showUser(p) {
		profile = p;
		if (!userBox) { return; }
		userBox.hidden = false;
		var nameEl = document.getElementById('kh-user-name');
		var mailEl = document.getElementById('kh-user-email');
		var avEl = document.getElementById('kh-avatar');
		if (nameEl) { nameEl.textContent = 'Login sebagai ' + (p.name || p.given_name || p.email || 'pengguna Google'); }
		if (mailEl) { mailEl.textContent = p.email || ''; }
		if (avEl) {
			if (p.picture) { avEl.src = p.picture; avEl.hidden = false; } else { avEl.hidden = true; }
		}
		var slot = document.getElementById('gsi-slot');
		if (slot) { slot.hidden = true; }
		if (authStatus) { authStatus.textContent = 'Akun terverifikasi. Isi data lalu kirim laporan.'; }
	}

	function clearUser() {
		credential = null;
		profile = null;
		if (userBox) { userBox.hidden = true; }
		var slot = document.getElementById('gsi-slot');
		if (slot) { slot.hidden = false; }
		if (authStatus) { authStatus.textContent = 'Diperlukan sebelum mengirim laporan.'; }
		if (submitBtn) { submitBtn.disabled = true; }
	}

	document.addEventListener('click', function (e) {
		if (e.target && e.target.id === 'kh-signout') {
			clearUser();
			if (window.google && google.accounts && google.accounts.id) {
				google.accounts.id.disableAutoSelect();
			}
		}
	});

	if (CFG.loggedIn) {
		if (authStatus) { authStatus.textContent = 'Masuk sebagai pengguna situs (' + CFG.userEmail + ')'; }
		showUser({ email: CFG.userEmail, name: 'Pengguna situs' });
		enableSubmit();
	} else if (!CFG.clientId) {
		if (authStatus) {
			authStatus.innerHTML = '<span style="color:#ff8a6a">Form belum aktif — admin belum mengatur Google Client ID.</span>';
		}
	} else {
		var s = document.createElement('script');
		s.src = 'https://accounts.google.com/gsi/client';
		s.async = true;
		s.defer = true;
		s.onload = function () {
			if (!window.google || !google.accounts) { return; }
			google.accounts.id.initialize({
				client_id: CFG.clientId,
				callback: function (resp) {
					credential = resp.credential;
					var payload = parseJwt(credential);
					showUser(payload);
					enableSubmit();
					if (msg) { setMsg('Siap. Lengkapi data kendaraan lalu kirim.', 'ok'); }
				}
			});
			google.accounts.id.renderButton(document.getElementById('gsi-slot'), {
				theme: 'filled_black',
				size: 'large',
				shape: 'rectangular',
				text: 'signin_with',
				locale: 'id'
			});
		};
		document.head.appendChild(s);
	}

	/* ---------------- kirim ---------------- */
	function setMsg(text, kind) {
		if (!msg) { return; }
		msg.textContent = text || '';
		msg.className = 'kh-form-msg' + (kind ? ' is-' + kind : '');
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();

		if (!CFG.loggedIn && !credential) {
			setMsg('Silakan masuk dengan Google dulu.', 'err');
			return;
		}

		var data = {
			credential: credential || '',
			plat: form.plat.value.trim(),
			jenis: form.jenis.value,
			merk: form.merk.value.trim(),
			model: form.model.value.trim(),
			tahun: form.tahun.value.trim(),
			warna: form.warna.value.trim(),
			wilayah: form.wilayah.value,
			lokasi: form.lokasi.value.trim(),
			tanggal: form.tanggal.value,
			jam: form.jam.value,
			polsek: form.polsek.value.trim(),
			sttlp: form.sttlp ? form.sttlp.value.trim() : '',
			ciri: form.ciri.value.trim(),
			lat: latEl ? latEl.value : '',
			lng: lngEl ? lngEl.value : ''
		};

		var decl = document.getElementById('kh-pernyataan');
		if (decl && !decl.checked) {
			setMsg('Centang pernyataan bahwa laporan ini benar dulu.', 'err');
			return;
		}

		if (!data.plat || !data.lokasi) {
			setMsg('Nomor polisi dan lokasi wajib diisi.', 'err');
			return;
		}

		submitBtn.disabled = true;
		setMsg('Mengirim…', '');

		fetch(CFG.rest, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CFG.nonce || ''
			},
			body: JSON.stringify(data)
		})
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
			.then(function (res) {
				if (res.ok && res.body && res.body.ok) {
					var who = profile && (profile.name || profile.email) ? profile.name || profile.email : 'akun Google';
					setMsg('Laporan terkirim sebagai ' + who + '. Menunggu verifikasi admin — terima kasih!', 'ok');
					if (typeof window.gtag === 'function') {
						window.gtag('event', 'lapor_kendaraan', { jenis: data.jenis || 'lain' });
					}
					form.reset();
					if (marker) { marker.remove(); marker = null; }
					picked = false;
					var info = document.getElementById('pick-info');
					if (info) { info.textContent = 'Klik peta untuk menandai lokasi kejadian.'; }
				} else {
					setMsg((res.body && res.body.message) || 'Gagal mengirim laporan.', 'err');
					submitBtn.disabled = false;
				}
			})
			.catch(function () {
				setMsg('Koneksi bermasalah. Coba lagi.', 'err');
				submitBtn.disabled = false;
			});
	});
})();
