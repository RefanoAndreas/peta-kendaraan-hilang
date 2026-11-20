#!/usr/bin/env python3
"""Probe produksi situs kendaraan hilang lewat login cookie + REST v2 (tanpa SSH).

Kenapa ada: menjawab pertanyaan "post X masih ada?", "berapa publish vs tampil di peta?",
"versi plugin berapa?" tanpa menulis ulang skrip login tiap sesi. Kredensial dibaca dari
`wp-admin-credentials.txt` di folder proyek (JANGAN cetak isinya).

Catatan penting (jebakan yang sudah menggigit): jangan pakai `--creds` dari
`wp_admin_http.py` untuk file ini — parser di sana menerima baris "WP admin : https://…"
sebagai username sehingga login selalu gagal. Skrip ini mem-parse per nama key.

Pemakaian:
  python tools/kh_probe.py versi                 # versi aset & halaman hidup
  python tools/kh_probe.py posts 81,82,83        # cek keberadaan & status post
  python tools/kh_probe.py peta                  # hitung publish / tampil / arsip / tanpa koordinat
  python tools/kh_probe.py ids                   # daftar publish tanpa koordinat
"""
from __future__ import annotations

import collections
import json
import os
import re
import sys

import requests

BASE = os.environ.get("KH_BASE", "https://hilang.refanoandreas.com")
HERE = os.path.dirname(os.path.abspath(__file__))
CREDS = os.path.join(HERE, "..", "wp-admin-credentials.txt")


def creds():
    kv = {}
    for line in open(CREDS, encoding="utf-8", errors="replace"):
        if ":" in line:
            k, v = line.split(":", 1)
            kv[k.strip().lower()] = v.strip()
    return kv["username"], kv["password"]


def session():
    u, p = creds()
    s = requests.Session()
    s.headers["User-Agent"] = "Mozilla/5.0 (compatible; kh-probe)"
    s.get(BASE + "/wp-login.php", timeout=30)
    r = s.post(BASE + "/wp-login.php", data={
        "log": u, "pwd": p, "wp-submit": "Log In",
        "redirect_to": BASE + "/wp-admin/", "testcookie": "1",
    }, timeout=30)
    if "/wp-admin/" not in r.url:
        sys.exit("login gagal - cek wp-admin-credentials.txt")
    return s


def nonce(s):
    r = s.get(BASE + "/wp-admin/", timeout=30)
    m = re.search(r'wpApiSettings\s*=\s*\{[^}]*"nonce":"([a-f0-9]+)"', r.text)
    return m.group(1) if m else None


def semua_post(s, n, status="publish"):
    """Semua post satu status lewat REST v2 (context=edit wajib nonce)."""
    out, page = [], 1
    while True:
        r = s.get(f"{BASE}/wp-json/wp/v2/kendaraan",
                  params={"status": status, "context": "edit", "per_page": 100, "page": page,
                          "_fields": "id,date,status,title,link"},
                  headers={"X-WP-Nonce": n}, timeout=90)
        if r.status_code != 200:
            break
        d = r.json()
        if not isinstance(d, list) or not d:
            break
        out += d
        if len(d) < 100:
            break
        page += 1
    return out


def cmd_versi():
    r = requests.get(BASE + "/?cb=" + os.urandom(3).hex(), timeout=60)
    print("home HTTP", r.status_code, "bytes", len(r.text))
    for m in sorted(set(re.findall(r"kendaraan-hilang-core/assets/[a-z./-]*\?ver=[0-9.]+", r.text))):
        print("  ", m)
    print("tombol cluster #zc:", 'id="zc"' in r.text)


def cmd_posts(argv):
    ids = argv[0] if argv else ""
    s, n = session(), None
    n = nonce(s)
    r = s.get(f"{BASE}/wp-json/wp/v2/kendaraan",
              params={"include": ids, "status": "any", "context": "edit"},
              headers={"X-WP-Nonce": n}, timeout=60)
    print("REST include=", ids, "->", r.status_code, r.text[:200])
    for pid in [x for x in ids.split(",") if x.strip()]:
        rr = s.get(f"{BASE}/wp-admin/post.php?post={pid.strip()}&action=edit", timeout=60)
        print(f"  post {pid.strip()}: edit.php HTTP {rr.status_code}"
              + ("  -> TIDAK ADA" if rr.status_code == 404 else ""))


def cmd_peta():
    a = requests.get(BASE + "/wp-json/kh/v1/peta?limit=1000", timeout=120).json()["items"]
    b = requests.get(BASE + "/wp-json/kh/v1/peta?limit=1000&arsip=1", timeout=180).json()["items"]
    print("tampil di peta:", len(a), "| termasuk arsip:", len(b),
          "| arsip:", sum(1 for x in b if x["arsip"]),
          "| titik unik:", len({(round(x["lat"], 5), round(x["lng"], 5)) for x in a}))
    print("status:", dict(collections.Counter(x["status"] for x in a)),
          "verifikasi:", dict(collections.Counter(x["verifikasi"] for x in a)))
    s = session()
    pub = semua_post(s, nonce(s), "publish")
    tr = semua_post(s, nonce(s), "trash")
    print("publish:", len(pub), "| trash:", len(tr),
          "| publish tanpa koordinat:", len(pub) - len(b))
    for st in ("trash", "draft", "pending"):
        print(f"  id {st}:", [p["id"] for p in (tr if st == "trash" else semua_post(s, nonce(s), st))][:10])


def cmd_ids():
    s = session()
    pub = semua_post(s, nonce(s), "publish")
    b = requests.get(BASE + "/wp-json/kh/v1/peta?limit=1000&arsip=1", timeout=180).json()["items"]
    punya = {x["id"] for x in b}
    for p in sorted([p for p in pub if p["id"] not in punya], key=lambda x: x["id"]):
        print(f'{p["id"]:>5} | {p["date"][:10]} | {p["title"]["rendered"][:70]}')


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "versi"
    arg = sys.argv[2:]
    if cmd == "versi":
        cmd_versi()
    elif cmd == "posts":
        cmd_posts(arg)
    elif cmd == "peta":
        cmd_peta()
    elif cmd == "ids":
        cmd_ids()
    else:
        sys.exit(__doc__)
