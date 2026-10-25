"""Prüft MGD-Paketmetadaten und lokale Pflichtdateien ohne externe Zugriffe."""
from pathlib import Path
import argparse
import json
import sys
from zipfile import ZipFile


def check(root, archive=None):
    root = Path(root).resolve()
    data = json.loads((root / 'mgd-plugin.json').read_text(encoding='utf-8'))
    if data.get('schemaVersion') != 1:
        raise ValueError('Unbekannte Manifestversion')
    if data.get('platform') not in {'wordpress', 'shopware', 'jtl-shop', 'shopify'}:
        raise ValueError('Unbekannte Plattform')
    channel = data.get('channel')
    if channel not in {'github', 'wordpress-org', 'shopify'}:
        raise ValueError('Unbekannter Vertriebskanal')
    if channel == 'wordpress-org' and (data['platform'] != 'wordpress' or data.get('externalCatalog') is not False):
        raise ValueError('WordPress.org braucht eine getrennte Ausgabe ohne externen Katalog')
    checked = {}
    for key in ('icon', 'cardBackground', 'readme', 'changelog', 'documentation'):
        relative = Path(data[key])
        if relative.is_absolute() or '..' in relative.parts:
            raise ValueError('Pfad außerhalb des Projekts: ' + key)
        file = (root / relative).resolve()
        if not file.is_relative_to(root) or not file.is_file() or not file.stat().st_size:
            raise ValueError('Fehlende oder unerlaubte Datei: ' + key)
        checked[key] = file
    image = checked['cardBackground']
    if image.suffix.lower() != '.webp' or image.stat().st_size > 80000:
        raise ValueError('Kartenmotiv muss WebP und höchstens 80 KB sein')
    content = image.read_bytes()
    if content[:4] != b'RIFF' or content[8:12] != b'WEBP':
        raise ValueError('Ungültiges WebP-Bild')
    if archive:
        prefix = data['packageRoot']
        runtime = Path(data['runtime'])
        if not prefix or '/' in prefix or prefix in {'.', '..'} or runtime.is_absolute() or '..' in runtime.parts:
            raise ValueError('Ungültige Paketwurzel')
        with ZipFile(archive) as package:
            if package.testzip() is not None:
                raise ValueError('Beschädigte ZIP-Datei')
            # WordPress.org verwaltet Verzeichnisbilder außerhalb des installierbaren ZIPs.
            package_assets = () if channel == 'wordpress-org' else ('icon', 'cardBackground')
            for key in package_assets:
                relative = Path(data[key]).relative_to(runtime)
                name = prefix + '/' + relative.as_posix()
                if package.read(name) != checked[key].read_bytes():
                    raise ValueError('Abweichendes Paket-Asset: ' + key)
    return data['platform'] + '/' + channel + ': Pflichtdateien und lokale Bildmotive vorhanden'


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('root', nargs='?', default='.')
    parser.add_argument('--zip', dest='archive')
    args = parser.parse_args()
    try:
        print('PASS ' + check(args.root, args.archive))
    except (ValueError, KeyError, OSError) as error:
        print('FAIL ' + str(error), file=sys.stderr)
        sys.exit(1)
