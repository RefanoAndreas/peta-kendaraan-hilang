/* Kartu Story 1080x1920 untuk Instagram / Facebook Story (gambar + QR). */
(function () {
	'use strict';

	var btn = document.getElementById('kh-story-btn');
	var dataEl = document.getElementById('kh-story-data');
	if (!btn || !dataEl) { return; }

	var S;
	try { S = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
	if (!S || !S.url) { return; }

	var W = 1080, H = 1920;
	var canvas = document.createElement('canvas');
	canvas.width = W;
	canvas.height = H;
	var ctx = canvas.getContext('2d');

	function rr(x, y, w, h, r) {
		ctx.beginPath();
		ctx.moveTo(x + r, y);
		ctx.arcTo(x + w, y, x + w, y + h, r);
		ctx.arcTo(x + w, y + h, x, y + h, r);
		ctx.arcTo(x, y + h, x, y, r);
		ctx.arcTo(x, y, x + w, y, r);
		ctx.closePath();
	}

	function spaced(text, x, y, spacing, align) {
		var total = 0;
		var i;
		for (i = 0; i < text.length; i++) { total += ctx.measureText(text[i]).width + spacing; }
		total -= spacing;
		var cx = align === 'center' ? x - total / 2 : x;
		for (i = 0; i < text.length; i++) {
			ctx.fillText(text[i], cx, y);
			cx += ctx.measureText(text[i]).width + spacing;
		}
	}

	function wrap(text, maxWidth) {
		var words = String(text || '').split(/\s+/);
		var lines = [], line = '';
		words.forEach(function (w) {
			var test = line ? line + ' ' + w : w;
			if (ctx.measureText(test).width > maxWidth && line) {
				lines.push(line);
				line = w;
			} else {
				line = test;
			}
		});
		if (line) { lines.push(line); }
		return lines;
	}

	function font(size, weight, family) {
		ctx.font = (weight || 500) + ' ' + size + 'px ' + (family || 'Rajdhani, Arial, sans-serif');
	}

	function draw(qrImg) {
		/* latar */
		var g = ctx.createLinearGradient(0, 0, 0, H);
		g.addColorStop(0, '#04121a');
		g.addColorStop(0.5, '#030a12');
		g.addColorStop(1, '#06161f');
		ctx.fillStyle = g;
		ctx.fillRect(0, 0, W, H);

		/* grid halus */
		ctx.strokeStyle = 'rgba(53,240,200,0.05)';
		ctx.lineWidth = 1;
		for (var x = 0; x < W; x += 60) {
			ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke();
		}
		for (var y = 0; y < H; y += 60) {
			ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
		}

		/* sudut bingkai */
		ctx.strokeStyle = 'rgba(53,240,200,0.55)';
		ctx.lineWidth = 5;
		var m = 46, len = 70;
		[[m, m, 1, 1], [W - m, m, -1, 1], [m, H - m, 1, -1], [W - m, H - m, -1, -1]].forEach(function (c) {
			ctx.beginPath();
			ctx.moveTo(c[0] + c[2] * len, c[1]);
			ctx.lineTo(c[0], c[1]);
			ctx.lineTo(c[0], c[1] + c[3] * len);
			ctx.stroke();
		});

		ctx.textAlign = 'left';

		/* brand */
		ctx.fillStyle = '#35f0c8';
		font(30, 700, 'Orbitron, Arial, sans-serif');
		spaced('PETA KENDARAAN HILANG', W / 2, 112, 6, 'center');
		ctx.fillStyle = '#7e98a3';
		font(24, 500, 'Orbitron, Arial, sans-serif');
		spaced('JAWA TIMUR', W / 2, 152, 10, 'center');

		/* badge status */
		ctx.textAlign = 'center';
		font(34, 700, 'Orbitron, Arial, sans-serif');
		var stText = S.status || 'HILANG';
		var stW = ctx.measureText(stText).width + 64;
		var stX = W / 2 - stW / 2;
		rr(stX, 200, stW, 74, 8);
		ctx.fillStyle = hexA(S.statusCol || '#ff5a3c', 0.14);
		ctx.fill();
		ctx.strokeStyle = S.statusCol || '#ff5a3c';
		ctx.lineWidth = 3;
		ctx.stroke();
		ctx.fillStyle = S.statusCol || '#ff5a3c';
		ctx.fillText(stText, W / 2, 250);

		/* plat besar */
		ctx.fillStyle = '#f4fbfd';
		var plat = S.plat || '';
		var size = 150;
		do {
			font(size, 700, 'Rajdhani, Arial, sans-serif');
			size -= 6;
		} while (ctx.measureText(plat).width > 880 && size > 60);
		ctx.textAlign = 'center';
		spaced(plat, W / 2, 430, 6, 'center');

		/* kendaraan + warna */
		ctx.fillStyle = '#cfe4ec';
		font(54, 600);
		ctx.fillText(S.kendaraan || '', W / 2, 512);
		if (S.warna) {
			ctx.fillStyle = '#8fb0bb';
			font(40, 500);
			ctx.fillText(S.warna, W / 2, 572);
		}

		/* pemisah */
		ctx.strokeStyle = 'rgba(53,240,200,0.5)';
		ctx.lineWidth = 3;
		ctx.beginPath(); ctx.moveTo(W / 2 - 110, 630); ctx.lineTo(W / 2 + 110, 630); ctx.stroke();

		/* lokasi */
		ctx.fillStyle = '#35f0c8';
		font(26, 700, 'Orbitron, Arial, sans-serif');
		spaced('TERAKHIR TERLIHAT', W / 2, 700, 8, 'center');

		ctx.fillStyle = '#e8f6fb';
		font(48, 600);
		var lok = wrap(S.lokasi || 'Jawa Timur', 860).slice(0, 2);
		lok.forEach(function (line, i) {
			ctx.fillText(line, W / 2, 768 + i * 58);
		});

		if (S.tanggal) {
			ctx.fillStyle = '#8fb0bb';
			font(38, 500);
			ctx.fillText(S.tanggal, W / 2, 768 + lok.length * 58 + 26);
		}

		/* chip keadaan verifikasi */
		var cy = 768 + lok.length * 58 + 96;
		ctx.textAlign = 'center';
		font(28, 700, 'Orbitron, Arial, sans-serif');
		var kt = (S.keadaan || '').toUpperCase();
		var kw = ctx.measureText(kt).width + 56;
		rr(W / 2 - kw / 2, cy, kw, 62, 31);
		ctx.fillStyle = hexA(S.keadaanCol || '#35f0c8', 0.12);
		ctx.fill();
		ctx.strokeStyle = S.keadaanCol || '#35f0c8';
		ctx.lineWidth = 2;
		ctx.stroke();
		ctx.fillStyle = S.keadaanCol || '#35f0c8';
		ctx.fillText(kt, W / 2, cy + 42);

		/* QR */
		var qs = 400, qx = (W - qs) / 2, qy = 1120;
		rr(qx - 26, qy - 26, qs + 52, qs + 52, 22);
		ctx.fillStyle = '#ffffff';
		ctx.fill();
		if (qrImg) {
			ctx.drawImage(qrImg, qx, qy, qs, qs);
		} else {
			ctx.fillStyle = '#04121a';
			font(30, 600);
			ctx.fillText('QR tidak tersedia', W / 2, qy + qs / 2);
		}

		ctx.fillStyle = '#cfe4ec';
		font(34, 600);
		ctx.fillText('Pindai untuk detail & melapor', W / 2, 1610);

		ctx.fillStyle = '#35f0c8';
		var u = shortUrl(S.url);
		var ufs = 30;
		font(ufs, 600);
		while (ctx.measureText(u).width > 880 && ufs > 18) {
			ufs -= 2;
			font(ufs, 600);
		}
		if (ctx.measureText(u).width > 880) {
			var cut = u;
			while (ctx.measureText(cut + '…').width > 880 && cut.length > 20) {
				cut = cut.slice(0, -1);
			}
			u = cut + '…';
		}
		ctx.fillText(u, W / 2, 1672);

		ctx.fillStyle = '#5f7f8a';
		font(26, 500);
		ctx.fillText('Sumber: ' + (S.sumber || 'laporan warga'), W / 2, 1740);
	}

	function shortUrl(u) {
		return String(u || '').replace(/^https?:\/\//, '').replace(/\/$/, '');
	}

	function hexA(hex, a) {
		var h = String(hex || '#35f0c8').replace('#', '');
		if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
		var n = parseInt(h, 16);
		return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
	}

	/* ---------- overlay ---------- */
	var overlay = null, previewUrl = null, blob = null;

	function buildOverlay() {
		var wrap = document.createElement('div');
		wrap.className = 'kh-story-overlay';
		wrap.innerHTML =
			'<div class="kh-story-modal" role="dialog" aria-modal="true" aria-label="Bagikan ke Story">' +
			'<div class="kh-story-head"><strong>Bagikan ke Story</strong>' +
			'<button type="button" class="kh-story-x" aria-label="Tutup">&times;</button></div>' +
			'<div class="kh-story-body"><img class="kh-story-preview" alt="Pratinjau kartu story"></div>' +
			'<p class="kh-story-hint">Simpan gambarnya, lalu unggah ke Instagram/Facebook Story. ' +
			'Untuk membuat tautan bisa diklik, gunakan stiker link (kalau akunmu punya akses) — atau cukup minta orang memindai QR.</p>' +
			'<div class="kh-story-actions">' +
			'<button type="button" class="kh-story-share">Bagikan…</button>' +
			'<button type="button" class="kh-story-dl">Unduh gambar</button>' +
			'</div></div>';
		document.body.appendChild(wrap);

		wrap.addEventListener('click', function (e) {
			if (e.target === wrap || e.target.classList.contains('kh-story-x')) { close(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { close(); }
		});
		wrap.querySelector('.kh-story-dl').addEventListener('click', function () {
			var a = document.createElement('a');
			a.href = previewUrl;
			a.download = 'kendaraan-hilang-' + (S.plat || 'laporan').replace(/[^A-Za-z0-9]+/g, '-') + '.png';
			document.body.appendChild(a);
			a.click();
			a.remove();
		});
		wrap.querySelector('.kh-story-share').addEventListener('click', function () {
			if (!blob) { return; }
			var file = new File([blob], 'kendaraan-hilang.png', { type: 'image/png' });
			if (navigator.canShare && navigator.canShare({ files: [file] })) {
				navigator.share({
					files: [file],
					text: 'Hilang ' + (S.kendaraan || '') + ' — ' + (S.plat || '') + ' di ' + (S.lokasi || '') + '. ' + S.url
				}).catch(function () {});
			} else {
				wrap.querySelector('.kh-story-dl').click();
			}
		});

		return wrap;
	}

	function close() {
		if (overlay) { overlay.remove(); overlay = null; }
		document.body.classList.remove('kh-story-open');
	}

	function open() {
		if (!overlay) { overlay = buildOverlay(); }
		var img = overlay.querySelector('.kh-story-preview');
		if (!img.getAttribute('src')) {
			img.src = previewUrl;
		}
		overlay.style.display = 'flex';
		document.body.classList.add('kh-story-open');
	}

	function ready() {
		return new Promise(function (res) {
			if (document.fonts && document.fonts.ready) {
				document.fonts.ready.then(function () { res(); }, function () { res(); });
			} else {
				res();
			}
		});
	}

	function loadQr() {
		return new Promise(function (res) {
			if (!S.qr) { return res(null); }
			var img = new Image();
			img.onload = function () { res(img); };
			img.onerror = function () { res(null); };
			img.src = S.qr;
		});
	}

	btn.addEventListener('click', function () {
		if (previewUrl) { open(); return; }
		btn.disabled = true;
		btn.textContent = 'Menyiapkan…';

		Promise.all([ready(), loadQr()]).then(function (r) {
			draw(r[1]);
			previewUrl = canvas.toDataURL('image/png');
			canvas.toBlob(function (b) { blob = b; }, 'image/png');
			btn.disabled = false;
			btn.textContent = 'Story IG/FB';
			open();
		});
	});
})();
