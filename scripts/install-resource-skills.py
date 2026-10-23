#!/usr/bin/env python3
"""Installiert die öffentlichen Ressourcen-/Plugin-Builder-Skills, niemals Projektdaten."""

import argparse
from pathlib import Path
import shutil
import sys


FILES = {
    'mgd-system-resources': ('SKILL.md', 'LICENSE'),
    'mgd-plugin-builder': ('SKILL.md', 'LICENSE', 'agents/openai.yaml',
        'references/ressourcen-updater.md', 'scripts/check_plugin_ready.py'),
}


def install(source, destinations, skill):
    """Prüft alle Ziele vorab; vorhandene Installationen werden nicht überschrieben."""
    source = Path(source)
    if source.is_symlink():
        raise ValueError("Skill-Quelle darf kein symbolischer Link sein.")
    # Systempfade wie /var -> /private/var sind erlaubt; Links im Bundle nicht.
    source = source.resolve()
    destinations = [Path(path).absolute() for path in destinations]
    if len({str(path) for path in destinations}) != len(destinations):
        raise ValueError('Installationsziele müssen verschieden sein.')
    # Die feste Liste verhindert, dass spätere Testdaten oder lokale Secrets mitreisen.
    for name in FILES[skill]:
        path = source / name
        if not path.is_file() or any(p.is_symlink() for p in [path, *path.parents] if p != source and source in p.parents):
            raise ValueError('Skill-Quelle fehlt oder enthält einen symbolischen Link.')
    for destination in destinations:
        # Keine Installation durch verlinkte Skill-Ordner, auch nicht bei leerem Ziel.
        if any(parent.is_symlink() for parent in destination.parents):
            raise ValueError('Zielpfad enthält einen symbolischen Link.')
        # Auch defekte Symlinks zählen als vorhandene, zu bewahrende Installation.
        if destination.exists() or destination.is_symlink():
            raise FileExistsError('Ziel existiert bereits: ' + str(destination))
    for destination in destinations:
        destination.mkdir(parents=True, exist_ok=False)
        for name in FILES[skill]:
            target = destination / name
            target.parent.mkdir(parents=True, exist_ok=True)
            # Exklusives Anlegen vermeidet ein Überschreiben bei parallelen Änderungen.
            with (source / name).open('rb') as src, target.open('xb') as dst:
                shutil.copyfileobj(src, dst)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--agent', choices=['codex', 'codex-legacy', 'claude', 'both'], default='both')
    parser.add_argument('--skill', choices=list(FILES), required=True)
    parser.add_argument('--project', type=Path, help='Vorhandenes Projekt statt persönlicher Installation')
    args = parser.parse_args()
    base = args.project.resolve() if args.project else Path.home()
    if not base.is_dir():
        parser.error('Projekt-/Benutzerordner muss bereits existieren.')
    folders = {'codex': '.agents', 'codex-legacy': '.codex', 'claude': '.claude'}
    agents = ['codex', 'claude'] if args.agent == 'both' else [args.agent]
    destinations = [base / folders[agent] / 'skills' / args.skill for agent in agents]
    source = Path(__file__).resolve().parents[1] / 'platform' / 'skills' / args.skill
    try:
        install(source, destinations, args.skill)
    except (OSError, ValueError) as error:
        # Hier werden nur öffentliche Skill-Dateien verarbeitet, keine Credentials.
        print('Installation abgebrochen: ' + str(error), file=sys.stderr)
        print('Bei einem E/A-Fehler mögliche Teilinstallation prüfen; nichts automatisch gelöscht.', file=sys.stderr)
        return 1
    for destination in destinations:
        print('Installiert: ' + str(destination))
    return 0


if __name__ == '__main__':
    sys.exit(main())
