#!/usr/bin/env python3
"""Bangun harness HTML dari templates/analitik.php supaya bisa diuji lokal tanpa WordPress.

Jalankan DARI ROOT PROYEK (bukan dari tools/):
  python tools/build_analitik_harness.py && python -m http.server 8099 --bind 127.0.0.1
  lalu buka http://127.0.0.1:8099/.harness/index.html

Pemakaian:
  python .harness/build.py        # tulis .harness/index.html
  python -m http.server 8099      # dari root proyek, lalu buka /harness/index.html
"""
import json
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))  # tools/ -> root proyek
PLUG = os.path.join(ROOT, "plugin", "kendaraan-hilang-core")
TPL = os.path.join(PLUG, "templates", "analitik.php")
OUT = os.path.join(ROOT, ".harness", "index.html")

html = open(TPL, encoding="utf-8").read()

# 0) buang blok pembuka PHP (docblock + guard ABSPATH)
html = re.sub(r"^\s*<\?php.*?\?>\s*", "", html, count=1, flags=re.S)

# 1) buang blok PHP multi-baris dulu (if/endif, dsb.)
html = re.sub(r"<\?php\s+if \( ! \$kh_publik \) : \?>.*?<\?php endif; \?>", "", html, flags=re.S)
html = re.sub(r"<\?php\s+if \( ! \$kh_publik \) : \?>", "", html)
html = re.sub(r"<\?php endif; \?>", "", html)

# 2) ganti pemanggilan PHP yang masih tersisa
html = re.sub(r"<\?php echo esc_url\( home_url\( '[^']*' \) \); \?>", "#", html)
html = re.sub(r"<\?php bloginfo\( 'charset' \); \?>", "UTF-8", html)
html = re.sub(r"<\?php language_attributes\(\); \?>", 'lang="id"', html)
html = re.sub(r"<\?php body_class\( '([^']*)' \); \?>", r'class="\1"', html)
html = re.sub(r"<\?php bloginfo\( 'name' \); \?>", "Peta Kendaraan Hilang", html)

# 3) sisipkan aset + data stub
css = f"""
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&family=Orbitron:wght@500;700;900&display=swap">
	<link rel="stylesheet" href="../plugin/kendaraan-hilang-core/assets/vendor/maplibre-gl.css">
	<link rel="stylesheet" href="../plugin/kendaraan-hilang-core/assets/analitik.css">
"""
js = """
	<script src="../plugin/kendaraan-hilang-core/assets/vendor/maplibre-gl.js"></script>
	<script src="../plugin/kendaraan-hilang-core/assets/map-style.js"></script>
	<script src="../plugin/kendaraan-hilang-core/assets/vendor/d3.min.js"></script>
	<script src="../plugin/kendaraan-hilang-core/assets/vendor/anime.min.js"></script>
	<script src="../plugin/kendaraan-hilang-core/assets/vendor/gsap.min.js"></script>
	<script src="../plugin/kendaraan-hilang-core/assets/vendor/ScrollTrigger.min.js"></script>
	<script>
	window.KH_AN = {
		endpoint: '/.harness/peta.json',
		home: '/', peta: '/', brand: 'Peta Kendaraan Hilang',
		kecamatan: __KEC__,
		publik: false
	};
	</script>
	<script src="../plugin/kendaraan-hilang-core/assets/analitik.js"></script>
"""

kec_php = os.path.join(PLUG, "inc", "data-kecamatan.php")
kec = {}
for m in re.finditer(r"'([^']+)'\s*=>\s*array\(\s*'([^']+)',\s*(-?[\d.]+),\s*(-?[\d.]+)\s*\)", open(kec_php, encoding="utf-8").read()):
    kec[m.group(1)] = [m.group(2), float(m.group(3)), float(m.group(4))]

html = html.replace("<?php wp_head(); ?>", css)
html = html.replace("<?php wp_footer(); ?>", js.replace("__KEC__", json.dumps(kec, ensure_ascii=False)))

# 4) sisa tag PHP yang tidak dikenal -> tandai supaya kelihatan
leftover = re.findall(r"<\?php.*?\?>", html, flags=re.S)
if leftover:
    print("PERINGATAN: masih ada PHP tersisa:")
    for x in leftover:
        print("   ", x[:80])

os.makedirs(os.path.dirname(OUT), exist_ok=True)
open(OUT, "w", encoding="utf-8").write(html)
print("harness ditulis:", OUT, len(html), "byte |", len(kec), "kecamatan")
