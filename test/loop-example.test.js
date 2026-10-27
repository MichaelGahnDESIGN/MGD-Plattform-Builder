import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const read = (file) => readFileSync(new URL(file, import.meta.url), 'utf8');

test('Loop-Beispiel nennt alle Felder der Zustandsvorlage', () => {
  const template = read('../platform/loop/STATE.example.md');
  const front = template.split('---')[1];
  const keys = [...front.matchAll(/^([a-z_]+):/gm)].map((m) => m[1]);
  assert.ok(keys.length >= 10, 'Zustandsvorlage hat zu wenige Felder');
  const example = read('../platform/loop/EXAMPLE.md');
  for (const key of keys) assert.ok(example.includes(`${key}:`), `Beispiel ohne Feld: ${key}`);
});

test('Loop-Beispiel ist aus LOOP.md und Wiki erreichbar', () => {
  assert.ok(read('../platform/LOOP.md').includes('loop/EXAMPLE.md'));
  assert.ok(read('../wiki/29-Loop-System.md').includes('platform/loop/EXAMPLE.md'));
});

test('Prüfskript im Beispiel unterscheidet "nichts gefunden" von "Suche fehlgeschlagen"', () => {
  const example = read('../platform/loop/EXAMPLE.md');
  const script = example.match(/```bash\n(#!\/usr\/bin\/env bash\n# exit 0 only[\s\S]*?)```/)[1];
  const dir = mkdtempSync(join(tmpdir(), 'loop-check-'));
  try {
    writeFileSync(join(dir, 'check.sh'), script, { mode: 0o755 });
    // src/ fehlt > Fehler (2), nicht "0 übrig"
    const missing = spawnSync('bash', ['check.sh'], { cwd: dir, encoding: 'utf8' });
    assert.equal(missing.status, 2, 'fehlendes src/ muss Exit 2 liefern');
    // Treffer vorhanden > noch nicht fertig (1)
    mkdirSync(join(dir, 'src'));
    writeFileSync(join(dir, 'src', 'a.js'), 'apiV1(x)\n');
    const open = spawnSync('bash', ['check.sh'], { cwd: dir, encoding: 'utf8' });
    assert.equal(open.status, 1, 'offene Treffer müssen Exit 1 liefern');
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});
