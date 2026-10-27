#!/usr/bin/env python3
"""Separate OSV-Laufzeit aus fest geprüften Herstellerdateien; keine Paketmanager."""
import argparse
import hashlib
import os
from pathlib import Path
import tempfile
import urllib.request

VERSION = "2.7.0"
ASSETS = {
    "darwin-arm64": ("osv-scanner_darwin_arm64", "d4926ef8da134332543be276dff157e4c170f99ad91fc48e89448a8816bdf70b"),
    "linux-amd64": ("osv-scanner_linux_amd64", "160c6253e2922e9e0a6a9771c5c4c40a4b3c6090b34a3cdd5e80c96cc2e4218b"),
}


class VendorRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        from urllib.parse import urlparse
        target = urlparse(newurl)
        if target.scheme != "https" or target.hostname not in {"github.com", "release-assets.githubusercontent.com", "objects.githubusercontent.com"}:
            raise RuntimeError("Downloadziel nicht freigegeben")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def install(directory, platform):
    root = Path(directory).absolute()
    root.mkdir(parents=True, exist_ok=True, mode=0o700)
    if root.is_symlink():
        raise RuntimeError("Laufzeitordner ist ein Verweis")
    asset, expected = ASSETS[platform]
    destination = root / asset
    if destination.exists():
        if destination.is_symlink() or hashlib.sha256(destination.read_bytes()).hexdigest() != expected:
            raise RuntimeError("Vorhandene Datei stimmt nicht mit dem Herstellerstand überein")
        print("Geprüfte Scanner-Version bereits vorhanden: " + VERSION)
        return
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(dir=root, delete=False) as output:
            temporary = Path(output.name)
            os.chmod(temporary, 0o600)
            digest = hashlib.sha256()
            size = 0
            opener = urllib.request.build_opener(VendorRedirect())
            with opener.open("https://github.com/google/osv-scanner/releases/download/v" + VERSION + "/" + asset, timeout=30) as source:
                while chunk := source.read(1024 * 1024):
                    size += len(chunk)
                    if size > 80 * 1024 * 1024:
                        raise RuntimeError("Scanner-Datei zu groß")
                    output.write(chunk)
                    digest.update(chunk)
            if digest.hexdigest() != expected:
                raise RuntimeError("Hersteller-Prüfsumme falsch")
        os.chmod(temporary, 0o700)
        temporary.replace(destination)
        print("OSV-Scanner " + VERSION + " mit geprüfter SHA-256 installiert")
    finally:
        if temporary and temporary.exists():
            temporary.unlink()


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--directory", required=True)
    parser.add_argument("--platform", required=True, choices=ASSETS)
    options = parser.parse_args()
    install(options.directory, options.platform)
