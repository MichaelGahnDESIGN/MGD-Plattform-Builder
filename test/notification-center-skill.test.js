import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('Notification-Skill enthält die verpflichtenden Sicherheits- und Adapterabschnitte', () => {
  const source = readFileSync(new URL('../platform/skills/notification-center/SKILL.md', import.meta.url), 'utf8');
  for (const term of ['NotificationRecipient', 'source_key', 'serverseitig', 'Shopify', 'JTL-Shop', 'Shopware', 'WordPress', 'Datenschutz-Webhooks', 'Tastaturbedienung']) assert.ok(source.includes(term), `Fehlt: ${term}`);
});
