import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

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
