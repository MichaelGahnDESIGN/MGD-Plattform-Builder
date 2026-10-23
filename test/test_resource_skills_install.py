"""Installation mit isolierten Verzeichnissen; keine echten Zugangsdaten oder Logins."""

import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('install_resource_skills', ROOT / 'scripts/install-resource-skills.py')
installer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installer)
SOURCE = ROOT / 'platform/skills/mgd-plugin-builder'
SKILL = 'mgd-plugin-builder'


class ResourceSkillInstallationTest(unittest.TestCase):
    def test_complete_bundle_for_both_agents(self):
        with tempfile.TemporaryDirectory() as directory:
            targets = [Path(directory).resolve() / agent / 'skills/code-kommentare' for agent in ['.agents', '.claude']]
            installer.install(SOURCE, targets, SKILL)
            for target in targets:
                self.assertEqual(sorted(str(p.relative_to(target)) for p in target.rglob('*') if p.is_file()), sorted(installer.FILES[SKILL]))
                for name in installer.FILES[SKILL]:
                    self.assertEqual((SOURCE / name).read_bytes(), (target / name).read_bytes())

    def test_existing_installation_prevents_all_writes(self):
        with tempfile.TemporaryDirectory() as directory:
            first, second = Path(directory).resolve() / 'first', Path(directory).resolve() / 'second'
            second.mkdir()
            (second / 'SKILL.md').write_text('Eigene Anpassung', encoding='utf-8')
            with self.assertRaises(FileExistsError):
                installer.install(SOURCE, [first, second], SKILL)
            self.assertFalse(first.exists())
            self.assertEqual((second / 'SKILL.md').read_text(encoding='utf-8'), 'Eigene Anpassung')

    def test_only_allowlisted_files_are_copied(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory).resolve() / 'source'
            installer.install(SOURCE, [source], SKILL)
            (source / '.env').write_text('SYNTHETIC_ONLY=not-a-credential', encoding='utf-8')
            target = Path(directory).resolve() / 'target'
            installer.install(source, [target], SKILL)
            self.assertFalse((target / '.env').exists())

    def test_symlink_inside_bundle_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory).resolve() / 'source'
            installer.install(SOURCE, [source], SKILL)
            license_path = source / 'LICENSE'
            license_path.unlink()
            try:
                license_path.symlink_to(SOURCE / 'LICENSE')
            except OSError:
                self.skipTest('Symlinks nicht verfügbar')
            target = Path(directory).resolve() / 'target'
            with self.assertRaises(ValueError):
                installer.install(source, [target], SKILL)
            self.assertFalse(target.exists())

    def test_missing_source_does_not_create_destination(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory).resolve() / 'target'
            with self.assertRaises(ValueError):
                installer.install(Path(directory).resolve() / 'missing', [target], SKILL)
            self.assertFalse(target.exists())

    def test_dangling_destination_symlink_is_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory).resolve() / 'target'
            try:
                target.symlink_to(Path(directory).resolve() / 'missing', target_is_directory=True)
            except OSError:
                self.skipTest('Symlinks nicht verfügbar')
            with self.assertRaises(FileExistsError):
                installer.install(SOURCE, [target], SKILL)
            self.assertTrue(target.is_symlink())

    def test_symlinked_destination_parent_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory).resolve()
            outside = base / 'outside'
            outside.mkdir()
            link = base / 'skills'
            link.symlink_to(outside, target_is_directory=True)
            with self.assertRaises(ValueError):
                installer.install(SOURCE, [link / 'code-kommentare'], SKILL)
            self.assertEqual(list(outside.iterdir()), [])


if __name__ == '__main__':
    unittest.main()
