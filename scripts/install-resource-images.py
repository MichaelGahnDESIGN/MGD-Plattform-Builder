#!/usr/bin/env python3
"""Separater Image-Scanner aus geprüften Herstellerarchiven; keine Systeminstallation."""
import argparse
import hashlib
import io
from pathlib import Path
import tarfile
import tempfile
import urllib.request

VERSION = '0.76.1'
ASSETS = {
    'darwin-arm64': ('macOS-ARM64', '11a9b6cd2752011b54f01ea4bd0dbfa9f0c41dc6538d5a3f9d23955f471bf4e5', '5c03401caf9d3cc04d9e0c7f3abd102e97518e8237465cce702162b605535e8d'),
    'linux-amd64': ('Linux-64bit', 'c7cbe3137fb8a48a17574bede3dd6beb16742d1e9772929940fc90082c39d9c0', 'd1dfca6ae10a1ad4540bb94e64f0510077c47008920a9b554986b39dca573d83'),
}

class VendorRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        from urllib.parse import urlparse
        target = urlparse(newurl)
        if target.scheme != 'https' or target.hostname not in {'github.com', 'release-assets.githubusercontent.com', 'objects.githubusercontent.com'}:
            raise RuntimeError('Herstellerziel nicht bestätigt')
        return super().redirect_request(req, fp, code, msg, headers, newurl)

def install(directory, platform):
    root = Path(directory).absolute()
    root.mkdir(mode=0o700, parents=True, exist_ok=True)
    if root.is_symlink():
        raise RuntimeError('Verweis als Laufzeitordner unzulässig')
    asset, archive_hash, binary_hash = ASSETS[platform]
    dest = root / 'trivy'
    if dest.exists():
        if dest.is_symlink() or hashlib.sha256(dest.read_bytes()).hexdigest() != binary_hash:
            raise RuntimeError('Vorhandene Laufzeit stimmt nicht mit dem Hersteller überein')
        return
    url = f'https://github.com/aquasecurity/trivy/releases/download/v{VERSION}/trivy_{VERSION}_{asset}.tar.gz'
    with urllib.request.build_opener(VendorRedirect()).open(url, timeout=45) as response:
        data = response.read(80 * 1024 * 1024 + 1)
    if len(data) > 80 * 1024 * 1024 or hashlib.sha256(data).hexdigest() != archive_hash:
        raise RuntimeError('Herstellerarchiv nicht bestätigt')
    with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
        entry = archive.getmember('trivy')
        if not entry.isfile() or entry.size > 320 * 1024 * 1024:
            raise RuntimeError('Ungültige Scannerdatei')
        binary = archive.extractfile(entry).read()
    if hashlib.sha256(binary).hexdigest() != binary_hash:
        raise RuntimeError('Scanner-Prüfsumme falsch')
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(dir=root, delete=False) as out:
            temporary = Path(out.name)
            temporary.chmod(0o600)
            out.write(binary)
        temporary.chmod(0o700)
        temporary.replace(dest)
    finally:
        if temporary and temporary.exists():
            temporary.unlink()
    print('Image-Scanner mit bestätigter SHA-256 installiert: ' + VERSION)

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--directory', required=True)
    parser.add_argument('--platform', required=True, choices=ASSETS)
    args = parser.parse_args()
    install(args.directory, args.platform)
