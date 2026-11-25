#!/usr/bin/env python3
"""Panggil MCP Hostinger langsung lewat JSON-RPC (dipakai kalau tool mcp__* tidak terdaftar).

Pakai:
    python hostinger_mcp.py <operation> '<params json>'      # -> cetak hasil JSON
    python hostinger_mcp.py uploads                         # -> tulis tus.json
"""
import json
import os
import sys
import urllib.request
import urllib.error

TOKEN_JSON = os.environ.get("KH_MCP_TOKEN_JSON") or os.path.join(
    os.environ.get("LOCALAPPDATA") or os.path.expanduser("~"),
    "hermes", "profiles", "dev-partner", "mcp-tokens", "hostinger.json",
)
URL = "https://mcp.hostinger.com"   # dari config.yaml mcp_servers.hostinger.url
BASE = os.path.dirname(os.path.abspath(__file__))   # tus.json di folder tools/ (scratch ke-prune 24 jam)


def _local_env():
    """tools/local.env (tidak ikut git) — KEY=VALUE per baris."""
    d = {}
    path = os.path.join(BASE, "local.env")
    if os.path.isfile(path):
        with open(path, encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                d[k.strip()] = v.strip()
    return d


def setting(key):
    return os.environ.get(key) or _local_env().get(key, "")


def token():
    with open(TOKEN_JSON, encoding="utf-8") as f:
        return json.load(f)["access_token"]


def endpoint():
    with open(TOKEN_JSON, encoding="utf-8") as f:
        d = json.load(f)
    return d.get("mcp_url") or d.get("endpoint") or URL


def rpc(payload, session=None):
    body = json.dumps(payload).encode()
    req = urllib.request.Request(endpoint(), data=body, method="POST")
    req.add_header("Authorization", "Bearer " + token())
    req.add_header("Content-Type", "application/json")
    req.add_header("Accept", "application/json, text/event-stream")
    req.add_header("User-Agent", "Hermes-Agent/1.0")
    if session:
        req.add_header("Mcp-Session-Id", session)
    try:
        with urllib.request.urlopen(req, timeout=90) as r:
            raw = r.read().decode("utf-8", "replace")
            sid = r.headers.get("Mcp-Session-Id")
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", "replace")
        raise SystemExit(f"HTTP {e.code}: {raw[:800]}")

    if not raw.strip():
        return {}, sid

    # dukung SSE: baris "data: {...}"
    if raw.lstrip().startswith("event:") or raw.lstrip().startswith("data:"):
        for line in raw.splitlines():
            if line.startswith("data:"):
                raw = line[5:].strip()
                break

    try:
        return json.loads(raw), sid
    except json.JSONDecodeError:
        raise SystemExit(f"Respons bukan JSON: {raw[:500]}")


def pakai(operation, params):
    init, sid = rpc({
        "jsonrpc": "2.0", "id": 1, "method": "initialize",
        "params": {
            "protocolVersion": "2025-06-18",
            "capabilities": {},
            "clientInfo": {"name": "hermes-cli", "version": "1"},
        },
    })
    rpc({"jsonrpc": "2.0", "method": "notifications/initialized", "params": {}}, sid)

    hasil, _ = rpc({
        "jsonrpc": "2.0", "id": 2, "method": "tools/call",
        "params": {"name": "execute", "arguments": {"operation": operation, "params": params}},
    }, sid)

    if "error" in hasil:
        raise SystemExit("MCP error: " + json.dumps(hasil["error"])[:800])

    teks = ""
    for c in hasil.get("result", {}).get("content", []):
        if c.get("type") == "text":
            teks += c.get("text", "")
    if not teks:
        return hasil.get("result", {})
    try:
        return json.loads(teks)
    except json.JSONDecodeError:
        return teks


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        return 2

    op = sys.argv[1]
    if op == "uploads":
        user, domain = setting("KH_HOSTINGER_USER"), setting("KH_HOSTINGER_DOMAIN")
        if not (user and domain):
            print("Set KH_HOSTINGER_USER & KH_HOSTINGER_DOMAIN "
                  "(env var atau tools/local.env)")
            return 2
        data = pakai("hosting_files_generate-upload-url",
                     {"username": user, "domain": domain})
        if not isinstance(data, dict) or "url" not in data:
            print("GAGAL:", json.dumps(data)[:600])
            return 1
        with open(os.path.join(BASE, "tus.json"), "w", encoding="utf-8") as f:
            json.dump({"url": data["url"], "auth_key": data["auth_key"],
                       "rest_auth_key": data["rest_auth_key"]}, f, indent=2)
        print("tus.json ditulis (url:", data["url"][:48] + "...)")
        return 0

    params = json.loads(sys.argv[2]) if len(sys.argv) > 2 else {}
    print(json.dumps(pakai(op, params), indent=2, ensure_ascii=False)[:4000])
    return 0


if __name__ == "__main__":
    sys.exit(main())
