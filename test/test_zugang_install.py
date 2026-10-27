"""Installation mit isolierten Verzeichnissen; keine echten Zugangsdaten oder Logins."""

import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('install_zugang', ROOT / 'scripts/install-zugang.py')
installer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installer)
SOURCE = ROOT / 'platform/skills/zugang'


class ZugangInstallationTest(unittest.TestCase):
    def test_complete_bundle_for_both_agents(self):
        with tempfile.TemporaryDirectory() as directory:
            targets = [Path(directory) / agent / 'skills/zugang' for agent in ['.agents', '.claude']]
            installer.install(SOURCE, targets)
            for target in targets:
                self.assertEqual(sorted(str(p.relative_to(target)) for p in target.rglob('*') if p.is_file()), sorted(installer.FILES))
                for name in installer.FILES:
                    self.assertEqual((SOURCE / name).read_bytes(), (target / name).read_bytes())

    def test_existing_installation_prevents_all_writes(self):
        with tempfile.TemporaryDirectory() as directory:
            first, second = Path(directory) / 'first', Path(directory) / 'second'
            second.mkdir()
            (second / 'SKILL.md').write_text('Eigene Anpassung', encoding='utf-8')
            with self.assertRaises(FileExistsError):
                installer.install(SOURCE, [first, second])
            self.assertFalse(first.exists())
            self.assertEqual((second / 'SKILL.md').read_text(encoding='utf-8'), 'Eigene Anpassung')

    def test_only_allowlisted_files_are_copied(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / 'source'
            installer.install(SOURCE, [source])
            (source / '.env').write_text('SYNTHETIC_ONLY=not-a-credential', encoding='utf-8')
            target = Path(directory) / 'target'
            installer.install(source, [target])
            self.assertFalse((target / '.env').exists())

    def test_symlink_inside_bundle_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / 'source'
            installer.install(SOURCE, [source])
            license_path = source / 'LICENSE'
            license_path.unlink()
            try:
                license_path.symlink_to(SOURCE / 'LICENSE')
            except OSError:
                self.skipTest('Symlinks nicht verfügbar')
            target = Path(directory) / 'target'
            with self.assertRaises(ValueError):
                installer.install(source, [target])
            self.assertFalse(target.exists())

    def test_missing_source_does_not_create_destination(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory) / 'target'
            with self.assertRaises(ValueError):
                installer.install(Path(directory) / 'missing', [target])
            self.assertFalse(target.exists())

    def test_dangling_destination_symlink_is_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory) / 'target'
            try:
                target.symlink_to(Path(directory) / 'missing', target_is_directory=True)
            except OSError:
                self.skipTest('Symlinks nicht verfügbar')
            with self.assertRaises(FileExistsError):
                installer.install(SOURCE, [target])
            self.assertTrue(target.is_symlink())


if __name__ == '__main__':
    unittest.main()
