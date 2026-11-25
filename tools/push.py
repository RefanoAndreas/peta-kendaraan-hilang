#!/usr/bin/env python3
"""Push file plugin ke public_html hilang.refanoandreas.com lewat TUS (Hostinger).

    python push.py kendaraan-hilang-core.php inc/instagram.php assets/ui.css
    python push.py wp-content/uploads/og/foo.jpg     # path public_html lengkap
"""
import json
import os
import subprocess
import sys

BASE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.environ.get("KH_ROOT") or os.path.dirname(BASE)          # folder proyek lokal
PLUG = os.environ.get("KH_PLUGIN_DIR") or os.path.join(ROOT, "plugin", "kendaraan-hilang-core")
PLUG_REL = "wp-content/plugins/kendaraan-hilang-core/"


def load():
    with open(os.path.join(BASE, "tus.json"), encoding="utf-8") as f:
        return json.load(f)


def local_path(arg):
    if arg.startswith("wp-content/"):
        return os.path.join(ROOT, arg.replace("/", os.sep))
    return os.path.join(PLUG, arg.replace("/", os.sep))


def rel_path(arg):
    return arg if arg.startswith("wp-content/") else PLUG_REL + arg


def push(cfg, arg):
    lokal = local_path(arg)
    rel = rel_path(arg)
    if not os.path.isfile(lokal):
        print("LEWATI (tidak ada):", lokal)
        return False

    ukuran = os.path.getsize(lokal)
    target = f"{cfg['url'].rstrip('/')}/{rel}?override=true"
    H = ["-H", f"X-Auth: {cfg['auth_key']}",
         "-H", f"X-Auth-Rest: {cfg['rest_auth_key']}",
         "-H", "Tus-Resumable: 1.0.0"]

    buat = subprocess.run(["curl", "-sS", "-i", "-X", "POST", target] + H +
                          ["-H", f"Upload-Length: {ukuran}", "-H", "Upload-Offset: 0"],
                          capture_output=True, text=True, timeout=120)
    baris = buat.stdout.splitlines()[0] if buat.stdout else ""
    if "201" not in baris:
        print(f"GAGAL buat upload {rel}\n{buat.stdout[:500]}\n{buat.stderr[:300]}")
        return False

    kirim = subprocess.run(["curl", "-sS", "-i", "-X", "PATCH", target] + H +
                           ["-H", "Content-Type: application/offset+octet-stream",
                            "-H", "Upload-Offset: 0", "--data-binary", f"@{lokal}"],
                           capture_output=True, text=True, timeout=300)
    if "204" not in kirim.stdout[:200]:
        print(f"GAGAL kirim {rel}\n{kirim.stdout[:500]}\n{kirim.stderr[:300]}")
        return False

    print(f"OK   {rel}  ({ukuran} byte)")
    return True


def main():
    args = sys.argv[1:]
    if not args:
        print(__doc__)
        return 2
    cfg = load()
    gagal = [a for a in args if not push(cfg, a)]
    if gagal:
        print("\nGAGAL:", ", ".join(gagal))
        return 1
    print("\nSemua file terkirim.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
