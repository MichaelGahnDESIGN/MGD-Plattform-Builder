#!/usr/bin/env python3
"""Zugang-UI: lokale Mini-Weboberfläche zum sicheren Einrichten von Server-Zugängen.

Nur Python-Standardbibliothek. Lauscht ausschließlich auf 127.0.0.1, verlangt ein Zufalls-Token
und schreibt nur in Projektordner unterhalb von --root. Passwörter werden nie an den Browser zurückgegeben.
"""
import argparse
import json
import os
import re
import secrets
import subprocess
import sys
import webbrowser
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

HERE = Path(__file__).resolve().parent
ENV_NAME = ".env.access.local"
NOTE_NAME = ".access.local.md"
IGNORE_LINES = [".ssh/", ".access.local.md", ".env.access.local", "*.local.md", ".env", ".env.*", "*.pem", "*.key"]
HOST_RE = re.compile(r"^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$")
USER_RE = re.compile(r"^[A-Za-z0-9._-]{1,64}$")
SLUG_RE = re.compile(r"^[a-z0-9][a-z0-9_-]{0,40}$")
FPR_RE = re.compile(r"^SHA256:[A-Za-z0-9+/=]{20,60}$")
SSH_TIMEOUT = 25


class UserError(Exception):
    """Fehler mit Text, der dem Nutzer angezeigt werden darf."""


def slugify(name: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", name.lower()).strip("-")[:40]
    return slug or "projekt"


def project_dir(root: Path, raw: str) -> Path:
    path = Path(raw).expanduser().resolve()
    if root != path and root not in path.parents:
        raise UserError(f"Der Ordner muss unterhalb von {root} liegen.")
    if not path.is_dir():
        raise UserError("Projektordner existiert nicht.")
    return path


def validate(fields: dict) -> dict:
    slug = fields.get("slug") or slugify(fields.get("name", ""))
    host, user = fields.get("host", "").strip(), fields.get("user", "").strip()
    port = str(fields.get("port") or "22").strip()
    fpr = fields.get("fingerprint", "").strip()
    if not SLUG_RE.match(slug):
        raise UserError("Kurzname: nur a-z, 0-9, - und _.")
    if not HOST_RE.match(host):
        raise UserError("Host ist ungültig.")
    if not USER_RE.match(user):
        raise UserError("SSH-User ist ungültig.")
    if not (port.isdigit() and 1 <= int(port) <= 65535):
        raise UserError("Port ist ungültig.")
    if fpr and not FPR_RE.match(fpr):
        raise UserError("Fingerprint muss mit SHA256: beginnen.")
    return {**fields, "slug": slug, "host": host, "user": user, "port": port, "fingerprint": fpr}


def after_in(lines: list, label: str) -> str:
    for i, ln in enumerate(lines):
        if ln.lower().startswith(label.lower()) and not (label == "Datenbank" and ln.lower().startswith("datenbank-host")):
            return next((x for x in lines[i + 1:i + 4] if x), "")
    return ""


def parse_markdown(text: str) -> dict:
    """Vorschlag aus einer Zugangs-.md (lokal, nur an den eigenen Browser)."""
    lines = [ln.strip() for ln in text.splitlines()]

    def after(label: str, start: int = 0) -> str:
        for i in range(start, len(lines)):
            if lines[i].lower().startswith(label.lower()):
                for nxt in lines[i + 1:i + 4]:
                    if nxt:
                        return nxt
        return ""

    found = {"host": after("Host Name"), "user": after("SSH-Login")}
    for i, ln in enumerate(lines):
        if ln.upper() == "ED25519":
            m = next((x for x in lines[i + 1:i + 3] if x.startswith("SHA256:")), "")
            found["fingerprint"] = re.sub(r"^SHA256:\s*", "SHA256:", m)
    dom = re.search(r"^(\S+\.\w+)\s+zeigt in Ordner\s+(\S+)", text, re.M)
    if dom:
        found["domain"], found["webroot_hint"] = dom.group(1), dom.group(2)
    admin = re.search(r"https?://\S+/admin/?", text)
    if admin:
        found["admin_url"] = admin.group(0)
    sec = re.search(r"^#+\s*MySQL\s*$(.*?)(?=^#+\s|\Z)", text, re.M | re.S | re.I)
    if sec:
        block = [ln.strip() for ln in sec.group(1).splitlines()]
        found["db_user"] = after_in(block, "Login")
        found["db_name"] = after_in(block, "Datenbank")
        found["db_host_hint"] = after_in(block, "Datenbank-Host")
        found["db_pass"] = after_in(block, "Passwort")
    return {k: v for k, v in found.items() if v}


def read_env(path: Path) -> dict:
    result = {}
    if path.exists():
        for ln in path.read_text(encoding="utf-8").splitlines():
            if "=" in ln and not ln.startswith("#"):
                key, _, val = ln.partition("=")
                result[key.strip()] = val.strip().strip('"')
    return result


def write_private(path: Path, content: str) -> None:
    old = os.umask(0o077)
    try:
        path.write_text(content, encoding="utf-8")
        path.chmod(0o600)
    finally:
        os.umask(old)


def update_gitignore(directory: Path, extra: list) -> list:
    target = directory / ".gitignore"
    existing = target.read_text(encoding="utf-8").splitlines() if target.exists() else []
    missing = [ln for ln in IGNORE_LINES + extra if ln and ln not in existing]
    if missing:
        body = "\n".join(existing + ([""] if existing and existing[-1] else []) + ["# Zugänge (zugang-ui)"] + missing) + "\n"
        target.write_text(body, encoding="utf-8")
    return missing


def build_env(f: dict, keyfile: str) -> str:
    rows = [("PROJECT", f["slug"]), ("CMS", f.get("cms", "")), ("DOMAIN", f.get("domain", "")),
            ("SSH_HOST", f["host"]), ("SSH_PORT", f["port"]), ("SSH_USER", f["user"]),
            ("SSH_KEY", keyfile), ("SSH_FINGERPRINT_ED25519", f["fingerprint"]),
            ("WEBROOT", f.get("webroot", "")), ("DB_HOST", f.get("db_host", "")), ("DB_NAME", f.get("db_name", "")),
            ("DB_USER", f.get("db_user", "")), ("DB_PASS", f.get("db_pass", "")),
            ("ADMIN_URL", f.get("admin_url", "")), ("ADMIN_USER", f.get("admin_user", "")),
            ("ADMIN_PASS", f.get("admin_pass", ""))]
    return "# Vertraulich. Nicht committen. Erzeugt von zugang-ui.\n" + "\n".join(f'{k}="{v}"' for k, v in rows if v) + "\n"


def build_note(f: dict) -> str:
    return (f"# {f.get('name') or f['slug']} – Zugänge (vertraulich, ohne Passwörter)\n\n"
            f"- CMS: {f.get('cms', '-')}  |  Domain: {f.get('domain', '-')}\n- Webroot: {f.get('webroot', '-')}\n"
            f"- SSH: `ssh {f['slug']}` (Alias) bzw. {f['user']}@{f['host']}:{f['port']}\n"
            f"- Werte stehen in `{ENV_NAME}` (chmod 600, in .gitignore).\n\n## Regeln\n"
            "- Nur lesen, bis ausdrücklich freigegeben. Vor Schreibzugriffen Backup.\n"
            "- Keine Zugangsdaten in Chat, Logs, Commits oder Memory ausgeben.\n")


def run(cmd: list, timeout: int = SSH_TIMEOUT) -> tuple:
    try:
        p = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, check=False)
        return p.returncode, (p.stdout + p.stderr).strip()
    except subprocess.TimeoutExpired:
        return 124, "Zeitüberschreitung"
    except FileNotFoundError as exc:
        return 127, str(exc)


def do_setup(root: Path, body: dict) -> dict:
    directory = project_dir(root, body.get("dir", ""))
    f = validate(body)
    ssh_dir = directory / ".ssh"
    ssh_dir.mkdir(mode=0o700, exist_ok=True)
    key = ssh_dir / f"id_ed25519_{f['slug']}"
    created = []
    if not key.exists():
        code, out = run(["ssh-keygen", "-t", "ed25519", "-N", "", "-C", f"claude-{f['slug']}", "-f", str(key), "-q"])
        if code != 0:
            raise UserError(f"Schlüssel konnte nicht erzeugt werden: {out}")
        created.append(str(key.relative_to(directory)))
    key.chmod(0o600)
    extra = [Path(body["source_md"]).name] if body.get("source_md") else []
    added = update_gitignore(directory, extra)
    env_path = directory / ENV_NAME
    if body.get("overwrite_env") or not env_path.exists():
        write_private(env_path, build_env(f, str(key)))
        created.append(ENV_NAME)
    write_private(directory / NOTE_NAME, build_note(f))
    return {"pubkey": key.with_suffix(".pub").read_text().strip(), "created": created,
            "gitignore_added": added, "env_kept": env_path.exists() and ENV_NAME not in created, "slug": f["slug"]}


def do_test(root: Path, body: dict) -> list:
    directory = project_dir(root, body.get("dir", ""))
    env = read_env(directory / ENV_NAME)
    if not env.get("SSH_HOST"):
        raise UserError(f"{ENV_NAME} fehlt. Erst Schritt 2 und 3 ausführen.")
    host, port, user, key = env["SSH_HOST"], env.get("SSH_PORT", "22"), env["SSH_USER"], env["SSH_KEY"]
    steps = []
    code, out = run(["ssh-keyscan", "-t", "ed25519", "-p", port, host])
    if code == 0 and out:
        scan = subprocess.run(["ssh-keygen", "-lf", "-"], input=out, capture_output=True, text=True, check=False)
        found = scan.stdout.split()[1] if scan.stdout.split() else ""
        want = env.get("SSH_FINGERPRINT_ED25519", "")
        status = "ok" if want and found == want else ("warn" if not want else "fail")
        steps.append({"name": "Host-Fingerprint", "status": status,
                      "text": found + ("  (stimmt überein)" if status == "ok" else "  (kein Vergleichswert hinterlegt)" if status == "warn" else f"  (erwartet {want})")})
        if status == "fail":
            return steps
    else:
        steps.append({"name": "Host-Fingerprint", "status": "fail", "text": out or "Host nicht erreichbar"})
        return steps
    cmd = ["ssh", "-i", key, "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=accept-new",
           "-o", "ConnectTimeout=15", "-p", port, f"{user}@{host}", "echo OK; pwd; ls /www 2>/dev/null | head -5"]
    code, out = run(cmd)
    ok = code == 0 and out.startswith("OK")
    steps.append({"name": "SSH-Login mit Schlüssel", "status": "ok" if ok else "fail",
                  "text": out if ok else (out or "fehlgeschlagen") + "\n→ Ist der öffentliche Schlüssel beim Hoster eingetragen?"})
    return steps


def do_rules(root: Path, directory_raw: str) -> dict:
    directory = project_dir(root, directory_raw)
    env = read_env(directory / ENV_NAME)
    if not env.get("SSH_HOST"):
        raise UserError(f"{ENV_NAME} fehlt.")
    slug = env.get("PROJECT", "projekt")
    ssh_conf = (f"# zugang-ui:{slug}\nHost {slug}\n  HostName {env['SSH_HOST']}\n  User {env['SSH_USER']}\n  Port {env.get('SSH_PORT', '22')}\n"
                f"  IdentityFile {env['SSH_KEY']}\n  IdentitiesOnly yes\n  BatchMode yes\n")
    perms = {"permissions": {"allow": [f"Bash(ssh {slug}:*)", f"Bash(scp:*)", f"Bash(rsync:*)", "Bash(git status:*)", "Bash(git diff:*)", "Bash(git log:*)"]}}
    return {"ssh_config": ssh_conf, "settings": json.dumps(perms, indent=2), "alias": slug}


def do_sshconfig(body: dict, root: Path) -> dict:
    data = do_rules(root, body.get("dir", ""))
    cfg = Path.home() / ".ssh" / "config"
    cfg.parent.mkdir(mode=0o700, exist_ok=True)
    text = cfg.read_text(encoding="utf-8") if cfg.exists() else ""
    marker = f"# zugang-ui:{data['alias']}"
    if marker in text:
        return {"changed": False, "text": f"Alias „{data['alias']}“ ist schon eingetragen."}
    cfg.write_text(text + ("\n" if text and not text.endswith("\n") else "") + "\n" + data["ssh_config"], encoding="utf-8")
    cfg.chmod(0o600)
    return {"changed": True, "text": f"Alias „{data['alias']}“ in ~/.ssh/config eingetragen."}


def make_handler(root: Path, token: str, port: int):
    allowed_hosts = {f"127.0.0.1:{port}", f"localhost:{port}"}

    class Handler(BaseHTTPRequestHandler):
        server_version = "zugang-ui"

        def log_message(self, *_args):
            return

        def _send(self, code: int, payload, ctype: str = "application/json") -> None:
            data = payload if isinstance(payload, bytes) else json.dumps(payload).encode()
            self.send_response(code)
            self.send_header("Content-Type", ctype + "; charset=utf-8")
            self.send_header("Content-Length", str(len(data)))
            self.send_header("Cache-Control", "no-store")
            self.send_header("X-Content-Type-Options", "nosniff")
            self.send_header("Content-Security-Policy", "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'")
            self.end_headers()
            self.wfile.write(data)

        def _authorized(self) -> bool:
            if self.headers.get("Host") not in allowed_hosts:
                return False
            supplied = self.headers.get("X-Token") or parse_qs(urlparse(self.path).query).get("t", [""])[0]
            return secrets.compare_digest(supplied, token)

        def do_GET(self):
            url = urlparse(self.path)
            if not self._authorized():
                return self._send(403, {"error": "Nicht erlaubt."})
            try:
                if url.path == "/":
                    return self._send(200, (HERE / "index.html").read_bytes(), "text/html")
                if url.path == "/api/info":
                    return self._send(200, {"root": str(root), "token_ok": True})
                if url.path == "/api/rules":
                    return self._send(200, do_rules(root, parse_qs(url.query).get("dir", [""])[0]))
            except UserError as exc:
                return self._send(400, {"error": str(exc)})
            return self._send(404, {"error": "Nicht gefunden."})

        def do_POST(self):
            if not self._authorized() or self.headers.get("Content-Type", "").split(";")[0] != "application/json":
                return self._send(403, {"error": "Nicht erlaubt."})
            try:
                length = min(int(self.headers.get("Content-Length", "0")), 200_000)
                body = json.loads(self.rfile.read(length) or b"{}")
                route = urlparse(self.path).path
                if route == "/api/parse":
                    src = Path(body.get("path", "")).expanduser().resolve()
                    if root not in src.parents or src.suffix.lower() != ".md" or not src.is_file():
                        raise UserError("Quelldatei muss eine .md unterhalb des Wurzelordners sein.")
                    return self._send(200, parse_markdown(src.read_text(encoding="utf-8")))
                if route == "/api/setup":
                    return self._send(200, do_setup(root, body))
                if route == "/api/test":
                    return self._send(200, {"steps": do_test(root, body)})
                if route == "/api/sshconfig":
                    return self._send(200, do_sshconfig(body, root))
            except UserError as exc:
                return self._send(400, {"error": str(exc)})
            except (ValueError, json.JSONDecodeError):
                return self._send(400, {"error": "Ungültige Eingabe."})
            return self._send(404, {"error": "Nicht gefunden."})

    return Handler


def main() -> int:
    ap = argparse.ArgumentParser(description="Zugang-UI (lokal)")
    ap.add_argument("--root", default=str(HERE.parent.parent), help="Wurzelordner, unterhalb dessen Projekte liegen dürfen")
    ap.add_argument("--port", type=int, default=8765)
    ap.add_argument("--no-open", action="store_true")
    args = ap.parse_args()
    root, token = Path(args.root).expanduser().resolve(), secrets.token_urlsafe(24)
    server = ThreadingHTTPServer(("127.0.0.1", args.port), make_handler(root, token, args.port))
    url = f"http://127.0.0.1:{args.port}/?t={token}"
    print(f"Zugang-UI läuft: {url}\nWurzelordner: {root}\nBeenden mit Strg+C", flush=True)
    if not args.no_open:
        webbrowser.open(url)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nBeendet.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
