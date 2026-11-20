/* =========================================================
   analitik.js — infografis laporan kendaraan hilang
   D3 (chart) · anime.js (angka & donut) · GSAP+ScrollTrigger (reveal)
   Data: REST /kh/v1/peta?limit=1000&arsip=1 (diambil di klien, selalu segar).
   ========================================================= */
(function () {
	'use strict';

	var AN = window.KH_AN || {};
	var C = {
		xm: '#35f0c8', rs: '#2fb4ff', warn: '#ffc94a', bad: '#ff5a3c',
		dim: '#6f8f98', line: '#12303a', line2: '#0d242c', tx: '#cfe9ee'
	};
	var PAL = ['#35f0c8', '#2fb4ff', '#ffc94a', '#a97bff', '#ff5a3c', '#4ade80', '#f472b6', '#60a5fa'];
	var RM = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var fmt = function (n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	var el = function (id) { return document.getElementById(id); };

	/* ---------- tooltip ---------- */
	var tip = document.createElement('div');
	tip.className = 'an-tip';
	document.body.appendChild(tip);
	function tipShow(ev, html) {
		tip.innerHTML = html;
		tip.style.opacity = '1';
		var pad = 14, w = tip.offsetWidth, h = tip.offsetHeight;
		var x = ev.clientX + pad, y = ev.clientY + pad;
		if (x + w > window.innerWidth - 8) x = ev.clientX - w - pad;
		if (y + h > window.innerHeight - 8) y = ev.clientY - h - pad;
		tip.style.left = x + 'px';
		tip.style.top = y + 'px';
	}
	function tipHide() { tip.style.opacity = '0'; }

	/* ---------- util ---------- */
	function countBy(arr, fn) {
		var m = new Map();
		arr.forEach(function (x) {
			var k = fn(x);
			if (k === null || k === undefined || k === '') k = 'Tidak diketahui';
			m.set(k, (m.get(k) || 0) + 1);
		});
		return m;
	}
	function sorted(map, limit) {
		var rows = [];
		map.forEach(function (v, k) { rows.push({ k: k, v: v }); });
		rows.sort(function (a, b) { return b.v - a.v || String(a.k).localeCompare(String(b.k)); });
		return limit ? rows.slice(0, limit) : rows;
	}
	function pct(a, b) { return b ? Math.round((a / b) * 100) : 0; }

	function family(m) {
		var s = (m || '').trim();
		if (!s) return 'Tidak diketahui';
		var u = s.toUpperCase();
		var PREFIX = [['BEAT', 'Beat'], ['VARIO', 'Vario'], ['SCOOPY', 'Scoopy'], ['PCX', 'PCX'],
			['NMAX', 'NMax'], ['N-MAX', 'NMax'], ['AEROX', 'Aerox'], ['CRF', 'CRF'],
			['SUPRA', 'Supra'], ['REVO', 'Revo'], ['GENIO', 'Genio'], ['MIO', 'Mio'],
			['STYLO', 'Stylo'], ['VIXION', 'Vixion'], ['CBR', 'CBR'], ['JUPITER', 'Jupiter'],
			['SONIC', 'Sonic'], ['XMAX', 'XMax'], ['LEXI', 'Lexi'], ['FINO', 'Fino'],
			['BLITZ', 'Blitz'], ['ABSOLUTE', 'Absolute'], ['ATHENA', 'Athena']];
		for (var i = 0; i < PREFIX.length; i++) {
			if (u.indexOf(PREFIX[i][0]) === 0) return PREFIX[i][1];
		}
		var base = s.replace(/\s*\d.*$/, '').trim();
		return base || 'Lainnya';
	}

	/* potong label panjang supaya tidak menabrak batang */
	function cut(txt, maxChars) {
		var t = String(txt);
		if (t.length <= maxChars) return t;
		return t.slice(0, Math.max(3, maxChars - 1)).replace(/[\s,\-]+$/, '') + '…';
	}

	function hourOf(jam) {
		var m = /^\s*(\d{1,2})[:.]/.exec(jam || '');
		if (!m) return null;
		var h = parseInt(m[1], 10);
		return h >= 0 && h <= 23 ? h : null;
	}

	function nearestKec(lat, lng) {
		var best = null, kec = AN.kecamatan || {};
		Object.keys(kec).forEach(function (name) {
			var p = kec[name], dLat = (p[1] - lat) * 111.32;
			var dLng = (p[2] - lng) * 111.32 * Math.cos(lat * Math.PI / 180);
			var d = Math.sqrt(dLat * dLat + dLng * dLng);
			if (!best || d < best.d) best = { name: name, reg: p[0], d: d };
		});
		return best;
	}

	/* ---------- SVG helper ---------- */
	function svgIn(host, height) {
		var w = Math.max(host.clientWidth || 320, 220);
		var h = height;
		host.innerHTML = '';
		var svg = d3.select(host).append('svg')
			.attr('viewBox', '0 0 ' + w + ' ' + h)
			.attr('preserveAspectRatio', 'xMinYMin meet')
			.attr('role', 'img');
		return { svg: svg, w: w, h: h };
	}

	function axisFont(sel) {
		return sel.attr('font-family', "'Share Tech Mono', monospace")
			.attr('font-size', 10)
			.attr('fill', C.dim)
			.attr('letter-spacing', '0.04em');
	}

	/* =========================================================
	   1. Bar horizontal (model, wilayah, kecamatan, plat)
	   ========================================================= */
	function barsH(host, rows, opt) {
		opt = opt || {};
		var rowH = opt.rowH || 26;
		var labelW = opt.labelW || Math.min(150, Math.max(84, host.clientWidth * 0.3));
		var s = svgIn(host, rows.length * rowH + 8);
		var svg = s.svg, w = s.w;
		var max = d3.max(rows, function (d) { return d.v; }) || 1;
		var x = d3.scaleLinear().domain([0, max]).range([0, w - labelW - 46]);

		var g = svg.selectAll('g.row').data(rows).enter().append('g')
			.attr('class', 'row')
			.attr('transform', function (d, i) { return 'translate(0,' + (i * rowH + 4) + ')'; });

		axisFont(g.append('text').attr('x', 0).attr('y', rowH * 0.62))
			.text(function (d) { return cut(d.k, Math.floor(labelW / 6.2)); })
			.attr('fill', C.tx).attr('font-size', 11.5)
			.attr('font-family', "'Rajdhani', sans-serif")
			.attr('text-anchor', 'start');

		g.append('rect').attr('x', labelW).attr('y', rowH * 0.22)
			.attr('height', Math.max(9, rowH * 0.42)).attr('rx', 1.5)
			.attr('fill', function (d, i) { return opt.mono ? C.xm : PAL[i % PAL.length]; })
			.attr('opacity', 0.9)
			.attr('width', 0)
			.on('mousemove', function (ev, d) {
				tipShow(ev, '<b>' + d.k + '</b><br><span class="mono">' + fmt(d.v) + ' laporan &middot; ' +
					pct(d.v, opt.total || rows.reduce(function (a, b) { return a + b.v; }, 0)) + '%</span>');
			})
			.on('mouseleave', tipHide)
			.transition().duration(RM ? 0 : 850).delay(function (d, i) { return i * 55; })
			.attr('width', function (d) { return Math.max(2, x(d.v)); });

		g.append('text').attr('x', function (d) { return labelW + Math.max(2, x(d.v)) + 7; })
			.attr('y', rowH * 0.62)
			.text(function (d) { return fmt(d.v); })
			.attr('font-family', "'Share Tech Mono', monospace").attr('font-size', 11)
			.attr('fill', C.dim).attr('opacity', 0)
			.transition().delay(function (d, i) { return 400 + i * 55; }).duration(300).attr('opacity', 1);
	}

	/* =========================================================
	   2. Kolom vertikal (tahun, hari, wilayah)
	   ========================================================= */
	function barsV(host, rows, opt) {
		opt = opt || {};
		var h = opt.fill ? Math.max(160, Math.round((host.clientHeight || 0) - 4)) : (opt.height || 190);
		var s = svgIn(host, h);
		var svg = s.svg, w = s.w;
		var lbl = function (d) { return opt.label ? opt.label(d) : d.k; };
		var padB = opt.rotate ? 42 : 26, padT = 18;
		var max = d3.max(rows, function (d) { return d.v; }) || 1;
		var band = d3.scaleBand().domain(rows.map(function (d) { return d.k; }))
			.range([0, w]).padding(rows.length > 10 ? 0.22 : 0.34);
		var y = d3.scaleLinear().domain([0, max]).range([h - padB, padT]);

		svg.append('line').attr('x1', 0).attr('x2', w).attr('y1', h - padB).attr('y2', h - padB)
			.attr('stroke', C.line);

		var g = svg.selectAll('g.c').data(rows).enter().append('g').attr('class', 'c');

		g.append('rect')
			.attr('x', function (d) { return band(d.k); })
			.attr('width', band.bandwidth())
			.attr('y', h - padB)
			.attr('height', 0)
			.attr('fill', function (d, i) { return opt.mono ? C.rs : PAL[i % PAL.length]; })
			.attr('opacity', 0.92)
			.on('mousemove', function (ev, d) {
				tipShow(ev, '<b>' + d.k + '</b><br><span class="mono">' + fmt(d.v) + ' laporan</span>');
			})
			.on('mouseleave', tipHide)
			.transition().duration(RM ? 0 : 800).delay(function (d, i) { return i * 32; })
			.attr('y', function (d) { return y(d.v); })
			.attr('height', function (d) { return h - padB - y(d.v); });

		axisFont(g.append('text')
			.attr('x', function (d) { return band(d.k) + band.bandwidth() / 2; })
			.attr('y', h - padB + 14)
			.attr('text-anchor', opt.rotate ? 'end' : 'middle')
			.attr('transform', opt.rotate
				? function (d) { return 'rotate(-45 ' + (band(d.k) + band.bandwidth() / 2) + ' ' + (h - padB + 14) + ')'; }
				: null)
			.text(function (d) { return lbl(d); }));

		if (opt.values !== false) {
			g.append('text')
				.attr('x', function (d) { return band(d.k) + band.bandwidth() / 2; })
				.attr('y', function (d) { return y(d.v) - 5; })
				.attr('text-anchor', 'middle')
				.attr('font-family', "'Share Tech Mono', monospace").attr('font-size', 10)
				.attr('fill', C.tx)
				.text(function (d) { return d.v; })
				.attr('opacity', 0)
				.transition().delay(function (d, i) { return 500 + i * 32; }).duration(280).attr('opacity', 1);
		}
	}

	/* =========================================================
	   3. Donut (region Surabaya) + legenda
	   ========================================================= */
	function donut(host, legendHost, rows) {
		var total = rows.reduce(function (a, b) { return a + b.v; }, 0);
		var w = Math.max(host.clientWidth || 180, 130);
		host.innerHTML = '';
		var size = Math.min(w, 210), r = size / 2 - 4, ir = r * 0.62;
		var svg = d3.select(host).append('svg')
			.attr('viewBox', '0 0 ' + size + ' ' + size)
			.style('width', size + 'px').style('height', size + 'px')
			.attr('role', 'img');
		var g = svg.append('g').attr('transform', 'translate(' + size / 2 + ',' + size / 2 + ')');

		var pie = d3.pie().value(function (d) { return d.v; }).sort(null).padAngle(0.02);
		var arc = d3.arc().innerRadius(ir).outerRadius(r).cornerRadius(2);
		var arcs = pie(rows);

		var paths = g.selectAll('path').data(arcs).enter().append('path')
			.attr('fill', function (d, i) { return PAL[i % PAL.length]; })
			.attr('opacity', 0.9)
			.on('mousemove', function (ev, d) {
				tipShow(ev, '<b>Surabaya ' + d.data.k + '</b><br><span class="mono">' + fmt(d.data.v) +
					' laporan &middot; ' + pct(d.data.v, total) + '%</span>');
			})
			.on('mouseleave', tipHide);

		if (RM) {
			paths.attr('d', arc);
		} else {
			paths.transition().duration(900).delay(function (d, i) { return i * 90; })
				.attrTween('d', function (d) {
					var i0 = { startAngle: d.startAngle, endAngle: d.startAngle };
					var i1 = d3.interpolate(i0, d);
					return function (t) { return arc(i1(t)); };
				});
		}

		g.append('text').attr('class', 'an-donut-mid').attr('text-anchor', 'middle')
			.attr('y', -2).attr('font-size', Math.round(size * 0.16))
			.text('0').attr('data-count', total);
		g.append('text').attr('class', 'an-donut-lbl').attr('text-anchor', 'middle')
			.attr('y', size * 0.12).text('laporan');

		/* angka tengah: dianimasikan di sini (bukan di render) supaya tetap benar
		   setiap kali chart digambar ulang saat lebar layar berubah. */
		var midTxt = host.querySelector('.an-donut-mid');
		if (midTxt) {
			if (window.anime && !RM) {
				var o = { n: 0 };
				anime({
					targets: o, n: total, round: 1, duration: 1300, easing: 'easeOutExpo',
					update: function () { midTxt.textContent = fmt(o.n); }
				});
			} else {
				midTxt.textContent = fmt(total);
			}
		}

		// legenda
		legendHost.innerHTML = '';
		rows.forEach(function (d, i) {
			var li = document.createElement('li');
			li.innerHTML = '<span class="sw" style="background:' + PAL[i % PAL.length] + '"></span>' +
				'<span class="nm">' + d.k + '</span>' +
				'<span class="vl">' + fmt(d.v) + ' &middot; ' + pct(d.v, total) + '%</span>';
			legendHost.appendChild(li);
		});
	}

	/* =========================================================
	   4. Jam rawan — bar radial 24 jam
	   ========================================================= */
	function radialHours(host, hours) {
		var w = Math.max(host.clientWidth || 320, 260);
		var size = Math.min(w, 320), cx = size / 2, cy = size / 2;
		var rOut = size / 2 - 6, rIn = rOut * 0.46;
		host.innerHTML = '';
		var svg = d3.select(host).append('svg')
			.attr('viewBox', '0 0 ' + size + ' ' + size)
			.style('width', '100%').style('max-width', size + 'px')
			.style('height', 'auto').style('margin', '0 auto').style('display', 'block');

		var max = d3.max(hours, function (d) { return d.v; }) || 1;
		var scale = d3.scaleLinear().domain([0, max]).range([rIn + 6, rOut]);
		var peak = hours.reduce(function (a, b) { return b.v > a.v ? b : a; }, hours[0]);

		var g = svg.append('g').attr('transform', 'translate(' + cx + ',' + cy + ')');
		g.selectAll('circle').data([rIn, rOut]).enter().append('circle')
			.attr('r', function (d) { return d; }).attr('fill', 'none')
			.attr('stroke', C.line).attr('stroke-width', 1);

		var arc = d3.arc().innerRadius(rIn).padRadius(rIn).cornerRadius(2);
		var pie = d3.pie().value(1).sort(null).padAngle(0.01);

		g.selectAll('path.h').data(pie(hours)).enter().append('path')
			.attr('class', 'h')
			.attr('fill', function (d) { return d.data.k === peak.k ? C.xm : C.rs; })
			.attr('opacity', function (d) { return d.data.k === peak.k ? 1 : 0.45; })
			.on('mousemove', function (ev, d) {
				tipShow(ev, '<b>Pukul ' + ('0' + d.data.k).slice(-2) + ':00–' +
					('0' + d.data.k).slice(-2) + ':59</b><br><span class="mono">' + fmt(d.data.v) + ' laporan</span>');
			})
			.on('mouseleave', tipHide)
			.transition().duration(RM ? 0 : 800)
			.delay(function (d) { return d.data.k * 26; })
			.attrTween('d', function (d) {
				var a0 = { startAngle: d.startAngle, endAngle: d.startAngle };
				var a1 = d3.interpolate(a0, {
					startAngle: d.startAngle, endAngle: d.endAngle,
					innerRadius: rIn, outerRadius: scale(d.data.v)
				});
				return function (t) {
					var a = a1(t);
					return arc(Object.assign({}, d, {
						endAngle: a.endAngle, innerRadius: rIn, outerRadius: a.outerRadius
					}));
				};
			});

		// label jam tiap 3 jam
		[0, 3, 6, 9, 12, 15, 18, 21].forEach(function (h) {
			var ang = (h / 24) * Math.PI * 2 - Math.PI / 2;
			var rr = rOut + 1;
			g.append('text')
				.attr('x', Math.cos(ang) * rr).attr('y', Math.sin(ang) * rr + 3)
				.attr('text-anchor', 'middle')
				.attr('font-family', "'Share Tech Mono', monospace").attr('font-size', 9.5)
				.attr('fill', C.dim)
				.text(('0' + h).slice(-2));
		});

		var mid = g.append('text').attr('text-anchor', 'middle').attr('y', 0)
			.attr('font-family', "'Orbitron', sans-serif").attr('font-weight', 700)
			.attr('font-size', 20).attr('fill', '#fff')
			.text(('0' + peak.k).slice(-2) + '.00');
		g.append('text').attr('text-anchor', 'middle').attr('y', 15)
			.attr('class', 'an-donut-lbl').text('jam rawan');
		return { peak: peak, mid: mid };
	}

	/* =========================================================
	   5. Area tren bulanan
	   ========================================================= */
	function trend(host, months) {
		var h = 210, padL = 30, padB = 24, padT = 16, padR = 8;
		var s = svgIn(host, h);
		var svg = s.svg, w = s.w;
		var max = d3.max(months, function (d) { return d.v; }) || 1;
		var x = d3.scalePoint().domain(months.map(function (d) { return d.k; }))
			.range([padL, w - padR]).padding(0.3);
		var y = d3.scaleLinear().domain([0, max * 1.12]).range([h - padB, padT]);

		[0, 0.5, 1].forEach(function (t) {
			var val = Math.round(max * t);
			svg.append('line').attr('x1', padL).attr('x2', w - padR)
				.attr('y1', y(val)).attr('y2', y(val))
				.attr('stroke', C.line2).attr('stroke-dasharray', '2 4');
			axisFont(svg.append('text').attr('x', 2).attr('y', y(val) + 3).text(val));
		});

		var area = d3.area().x(function (d) { return x(d.k); })
			.y0(h - padB).y1(function (d) { return y(d.v); }).curve(d3.curveMonotoneX);
		var line = d3.line().x(function (d) { return x(d.k); })
			.y(function (d) { return y(d.v); }).curve(d3.curveMonotoneX);

		var grad = svg.append('defs').append('linearGradient')
			.attr('id', 'an-grad-trend').attr('x1', 0).attr('y1', 0).attr('x2', 0).attr('y2', 1);
		grad.append('stop').attr('offset', '0%').attr('stop-color', C.xm).attr('stop-opacity', 0.4);
		grad.append('stop').attr('offset', '100%').attr('stop-color', C.xm).attr('stop-opacity', 0.02);

		svg.append('path').datum(months).attr('fill', 'url(#an-grad-trend)').attr('d', area).attr('opacity', 0)
			.transition().duration(RM ? 0 : 700).attr('opacity', 1);

		var p = svg.append('path').datum(months)
			.attr('fill', 'none').attr('stroke', C.xm).attr('stroke-width', 2)
			.attr('stroke-linejoin', 'round')
			.attr('d', line);

		if (!RM) {
			var L = p.node().getTotalLength();
			p.attr('stroke-dasharray', L + ' ' + L).attr('stroke-dashoffset', L)
				.transition().duration(1100).ease(d3.easeCubicOut).attr('stroke-dashoffset', 0);
		}

		svg.selectAll('circle.pt').data(months).enter().append('circle')
			.attr('class', 'pt').attr('cx', function (d) { return x(d.k); })
			.attr('cy', function (d) { return y(d.v); }).attr('r', 0)
			.attr('fill', '#04080d').attr('stroke', C.xm).attr('stroke-width', 2)
			.on('mousemove', function (ev, d) {
				tipShow(ev, '<b>' + d.label + '</b><br><span class="mono">' + fmt(d.v) + ' laporan</span>');
			})
			.on('mouseleave', tipHide)
			.transition().delay(function (d, i) { return 300 + i * 60; }).duration(300)
			.attr('r', 3.6);

		months.forEach(function (d, i) {
			if (months.length > 9 && i % 2) return;
			axisFont(svg.append('text').attr('x', x(d.k)).attr('y', h - padB + 15)
				.attr('text-anchor', 'middle').text(d.short));
		});
	}

	/* =========================================================
	   6. Daftar + bar mini (lokasi)
	   ========================================================= */
	function listBars(host, rows) {
		var max = rows[0] ? rows[0].v : 1;
		host.innerHTML = '';
		var ul = document.createElement('ul');
		ul.className = 'an-list';
		rows.forEach(function (d, i) {
			var li = document.createElement('li');
			li.innerHTML = '<div class="row"><span class="nm">' + d.k + '</span>' +
				'<span class="vl">' + fmt(d.v) + '</span></div>' +
				'<div class="bar"><i></i></div>';
			ul.appendChild(li);
			var bar = li.querySelector('.bar i');
			bar.style.background = PAL[i % PAL.length];
			setTimeout(function () {
				bar.style.transition = 'width .7s cubic-bezier(.2,.7,.2,1)';
				bar.style.width = Math.round((d.v / max) * 100) + '%';
			}, RM ? 0 : 60 * i);
		});
		host.appendChild(ul);
	}

	/* =========================================================
	   7. Peta sebaran (MapLibre)
	   ========================================================= */
	function scatterMap(host, items) {
		if (!window.maplibregl || !window.KH_MAP_STYLE) {
			host.innerHTML = '<div class="an-map-ph">peta tidak tersedia</div>';
			return;
		}
		var geojson = {
			type: 'FeatureCollection',
			features: items.filter(function (x) { return x.lat && x.lng; }).map(function (x) {
				return {
					type: 'Feature',
					geometry: { type: 'Point', coordinates: [x.lng, x.lat] },
					properties: {
						plat: x.plat || '—', lokasi: x.lokasi || '—',
						tgl: x.tanggal || '—', url: x.url || '#',
						arsip: x.arsip ? 1 : 0, model: (x.merk || '') + ' ' + (x.model || '')
					}
				};
			})
		};
		var map = new maplibregl.Map({
			container: host.id,
			style: window.KH_MAP_STYLE,
			center: [112.70, -7.29],
			zoom: 10.2,
			attributionControl: false
		});
		window.KH_AN_MAP = map;
		map.on('load', function () {
			map.addSource('an-pts', { type: 'geojson', data: geojson });
			map.addLayer({
				id: 'an-halo', type: 'circle', source: 'an-pts',
				paint: {
					'circle-radius': ['interpolate', ['linear'], ['zoom'], 8, 4, 13, 12],
					'circle-color': ['case', ['==', ['get', 'arsip'], 1], '#2fb4ff', '#35f0c8'],
					'circle-opacity': 0.18, 'circle-blur': 0.4
				}
			});
			map.addLayer({
				id: 'an-dot', type: 'circle', source: 'an-pts',
				paint: {
					'circle-radius': ['interpolate', ['linear'], ['zoom'], 8, 1.8, 13, 5],
					'circle-color': ['case', ['==', ['get', 'arsip'], 1], '#2fb4ff', '#35f0c8'],
					'circle-stroke-width': 0.6,
					'circle-stroke-color': '#04080d'
				}
			});
			map.on('mousemove', 'an-dot', function (e) {
				map.getCanvas().style.cursor = 'pointer';
				var p = e.features[0].properties;
				tipShow(e.originalEvent, '<b>' + p.plat + '</b><br>' + p.model +
					'<br><span class="mono">' + p.lokasi + '<br>' + p.tgl + '</span>');
			});
			map.on('mouseleave', 'an-dot', function () {
				map.getCanvas().style.cursor = '';
				tipHide();
			});
			map.on('click', 'an-dot', function (e) {
				var p = e.features[0].properties;
				if (p.url && p.url !== '#') window.open(p.url, '_blank', 'noopener');
			});
		});
	}

	/* =========================================================
	   KPI + angka animasi
	   ========================================================= */
	function setKpi(key, value, sub) {
		var box = document.querySelector('[data-kpi="' + key + '"]');
		if (!box) return;
		var vEl = box.querySelector('.an-kpi-v');
		var sEl = box.querySelector('.an-kpi-s');
		if (typeof value === 'number') {
			var o = { n: 0 };
			if (RM || !window.anime) { vEl.textContent = fmt(value); }
			else {
				anime({
					targets: o, n: value, round: 1, duration: 1200, easing: 'easeOutExpo',
					update: function () { vEl.textContent = fmt(o.n); }
				});
			}
		} else {
			vEl.textContent = value;
			vEl.setAttribute('data-c', value);
		}
		if (sub) sEl.textContent = sub;
	}

	/* =========================================================
	   MAIN
	   ========================================================= */
	function render(items) {
		var total = items.length;
		var aktif = items.filter(function (x) { return !x.arsip; }).length;

		// --- agregasi
		var famRows = sorted(countBy(items, function (x) { return family(x.model); }), 9);
		var wilRows = sorted(countBy(items, function (x) { return x.wilayah; }), 7);

		var hours = d3.range(24).map(function (h) { return { k: h, v: 0 }; });
		var jamKosong = 0;
		items.forEach(function (x) {
			var h = hourOf(x.jam);
			if (h === null) { jamKosong++; return; }
			hours[h].v++;
		});
		var peak = hours.reduce(function (a, b) { return b.v > a.v ? b : a; }, hours[0]);
		var peakV = peak.v;
		var peakJam = hours.filter(function (h) { return h.v === peakV; })
			.map(function (h) { return ('0' + h.k).slice(-2) + '.00'; });

		var months = sorted(countBy(items, function (x) { return (x.tanggal || '').slice(0, 7); }), null)
			.filter(function (d) { return /^\d{4}-\d{2}$/.test(d.k); })
			.sort(function (a, b) { return a.k.localeCompare(b.k); })
			.map(function (d) {
				var nm = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
				var p = d.k.split('-');
				return { k: d.k, v: d.v, short: nm[parseInt(p[1], 10) - 1] + " '" + p[0].slice(2) };
			});

		var years = sorted(countBy(items, function (x) {
			return /^\d{4}$/.test((x.tahun || '').trim()) ? (x.tahun || '').trim() : null;
		}), null).filter(function (d) { return d.k !== 'Tidak diketahui'; })
			.sort(function (a, b) { return a.k.localeCompare(b.k); })
			.map(function (d) { return { k: d.k, v: d.v, s: "'" + d.k.slice(2) }; });
		var thnKosong = items.filter(function (x) { return !/^\d{4}$/.test((x.tahun || '').trim()); }).length;

		var platRows = sorted(countBy(items, function (x) { return (x.plat || '').split(' ')[0]; }), 7);
		var hariNama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
		var hari = hariNama.map(function (n) { return { k: n.slice(0, 3), v: 0, full: n }; });
		items.forEach(function (x) {
			var d = new Date((x.tanggal || '') + 'T00:00:00');
			if (!isNaN(d)) hari[d.getDay()].v++;
		});
		hari = [hari[1], hari[2], hari[3], hari[4], hari[5], hari[6], hari[0]]; // Sen..Min

		var sby = items.filter(function (x) { return x.wilayah === 'Surabaya' && x.lat && x.lng; });
		var reg = countBy(sby, function (x) { var k = nearestKec(x.lat, x.lng); return k ? k.reg : null; });
		var regRows = ['Timur', 'Selatan', 'Barat', 'Pusat', 'Utara']
			.map(function (r) { return { k: r, v: reg.get(r) || 0 }; })
			.sort(function (a, b) { return b.v - a.v; });

		var kec = countBy(sby, function (x) { var k = nearestKec(x.lat, x.lng); return k ? k.name : null; });
		var kecRows = sorted(kec, 8);

		var lokasiRows = sorted(countBy(items, function (x) {
			var s = (x.lokasi || '').trim();
			return s ? s.replace(/\s+/g, ' ') : null;
		}), 8);

		// --- KPI
		setKpi('total', total);
		setKpi('tampil', aktif, Math.round((aktif / total) * 100) + '% dari total');
		setKpi('model', famRows[0].k, fmt(famRows[0].v) + ' laporan · ' + pct(famRows[0].v, total) + '%');
		setKpi('jam', peakJam.join(' / '), fmt(peakV) + ' laporan' + (peakJam.length > 1 ? ' · seri' : ' di jam itu'));
		setKpi('wilayah', wilRows[0].k, fmt(wilRows[0].v) + ' laporan · ' + pct(wilRows[0].v, total) + '%');
		var bulanSpan = Math.max(1, months.length);
		setKpi('laju', Math.round(total / bulanSpan), 'dari ' + bulanSpan + ' bulan data');

		// --- judul & lead
		var first = months[0], last = months[months.length - 1];
		el('an-n').textContent = fmt(total);
		el('an-dari').textContent = first ? first.short : '—';
		el('an-sampai').textContent = last ? last.short : '—';

		// --- chart
		var hWil = window.innerWidth >= 1180 ? 296 : (window.innerWidth >= 900 ? 240 : 210);
		barsH(el('fig-model'), famRows, { total: total });
		barsV(el('fig-wilayah'), wilRows, { height: hWil });
		donut(el('fig-region'), el('leg-region'), regRows);
		radialHours(el('fig-jam'), hours);
		trend(el('fig-tren'), months);
		barsV(el('fig-tahun'), years, { height: 200, label: function (d) { return d.s; } });
		barsH(el('fig-plat'), platRows, { total: total, mono: true });
		barsV(el('fig-hari'), hari, { height: 170 });
		barsH(el('fig-kec'), kecRows, { total: sby.length });
		listBars(el('fig-lokasi'), lokasiRows);

		var adaWilayah = items.filter(function (x) { return x.wilayah; }).length;
		el('note-wilayah').textContent = (total - adaWilayah ? fmt(total - adaWilayah) + ' laporan tanpa wilayah. ' : '') +
			fmt(adaWilayah) + ' laporan punya wilayah' + (wilRows.length < countBy(items, function (x) { return x.wilayah; }).size ? ' (7 teratas ditampilkan)' : '');

		el('note-jam').textContent = (jamKosong ? fmt(jamKosong) + ' laporan tanpa jam kejadian tidak dihitung. ' : '') +
			'Puncak: ' + ('0' + peak.k).slice(-2) + '.00–' + ('0' + peak.k).slice(-2) + '.59';
		el('note-tahun').textContent = (thnKosong ? fmt(thnKosong) + ' laporan tanpa tahun kendaraan. ' : '') +
			'Semua data: ' + fmt(years.reduce(function (a, b) { return a + b.v; }, 0)) + ' laporan';

		// --- peta (baru digambar saat masuk layar)
		var petaBox = el('fig-peta');
		if (petaBox && 'IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (es) {
				es.forEach(function (e) {
					if (e.isIntersecting) { io.disconnect(); scatterMap(petaBox, items); }
				});
			}, { rootMargin: '200px' });
			io.observe(petaBox);
		} else if (petaBox) {
			scatterMap(petaBox, items);
		}

		// --- animasi donut tengah sudah diurus di dalam donut()

		// --- reveal kartu (GSAP)
		if (window.gsap && !RM) {
			gsap.registerPlugin(window.ScrollTrigger || {});
			gsap.utils.toArray('[data-anim]').forEach(function (card, i) {
				gsap.from(card, {
					opacity: 0, y: 18, duration: .7, ease: 'power2.out', delay: Math.min(i * .05, .5),
					scrollTrigger: window.ScrollTrigger ? { trigger: card, start: 'top 92%', once: true } : undefined
				});
			});
		}

		// --- responsif: gambar ulang chart SVG saat lebar berubah
		var t = null;
		window.addEventListener('resize', function () {
			clearTimeout(t);
			t = setTimeout(function () {
				barsH(el('fig-model'), famRows, { total: total });
				barsV(el('fig-wilayah'), wilRows, { height: window.innerWidth >= 1180 ? 296 : (window.innerWidth >= 900 ? 240 : 210) });
				donut(el('fig-region'), el('leg-region'), regRows);
				radialHours(el('fig-jam'), hours);
				trend(el('fig-tren'), months);
				barsV(el('fig-tahun'), years, { height: 200, label: function (d) { return d.s; } });
				barsH(el('fig-plat'), platRows, { total: total, mono: true });
				barsV(el('fig-hari'), hari, { height: 170 });
				barsH(el('fig-kec'), kecRows, { total: sby.length });
			}, 260);
		});
	}

	function gagal(msg) {
		var g = el('an-grid');
		if (g) g.innerHTML = '<div class="an-err">DATA GAGAL DIMUAT — ' + msg + '</div>';
	}

	document.addEventListener('DOMContentLoaded', function () {
		var url = (AN.endpoint || '/wp-json/kh/v1/peta') + '?limit=1000&arsip=1';
		fetch(url, { credentials: 'same-origin' })
			.then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function (d) {
				var items = (d.items || []).slice();
				if (!items.length) return gagal('tidak ada laporan');
				render(items);
			})
			.catch(function (e) { gagal(e.message || 'galat jaringan'); });
	});
})();
