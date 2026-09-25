<?php

declare(strict_types=1);

/*
 * Lizenz-, Label- und Modulprüfungen ohne Datenbank. Wird von tests/smoke.php eingebunden.
 */

use MGD\Starter\Core\License\LicenseGrant;
use MGD\Starter\Core\License\LicenseIntegrity;
use MGD\Starter\Core\License\LicenseKey;
use MGD\Starter\Core\License\PoweredBy;
use MGD\Starter\Core\Module\ModuleManifest;

/** Erzeugt einen Testschlüssel mit einem Test-Schlüsselpaar (nicht dem echten Lizenzgeber-Schlüssel). */
$signLicense = static function (array $payload, string $secretKey): string {
    $body = LicenseKey::PREFIX . '.' . LicenseKey::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

    return $body . '.' . LicenseKey::base64UrlEncode(sodium_crypto_sign_detached($body, $secretKey));
};

$testPair = sodium_crypto_sign_keypair();
$testKeys = new LicenseKey(base64_encode(sodium_crypto_sign_publickey($testPair)));
$whitelabelPayload = [
    'type' => 'whitelabel',
    'project_id' => 'smoke-project',
    'domains' => ['example.org', '*.example.net'],
    'licensee' => 'Smoke GmbH',
    'issued_at' => '2026-09-25',
];
$validKey = $signLicense($whitelabelPayload, sodium_crypto_sign_secretkey($testPair));
$grant = $testKeys->parse($validKey);

check($grant !== null && $grant->type === LicenseGrant::TYPE_WHITELABEL && $grant->licensee === 'Smoke GmbH', 'Signierter Whitelabel-Schlüssel wird akzeptiert');
check($grant !== null && $grant->coversHost('example.org') && $grant->coversHost('shop.example.net') && !$grant->coversHost('example.net') && !$grant->coversHost('evil.org'), 'Domainbindung inkl. Wildcard');
check((new LicenseKey())->parse($validKey) === null, 'Fremd signierter Schlüssel wird mit echtem öffentlichen Schlüssel abgelehnt');

$tampered = explode('.', $validKey);
$tampered[1] = LicenseKey::base64UrlEncode(json_encode(['domains' => ['evil.org']] + $whitelabelPayload, JSON_THROW_ON_ERROR));
check($testKeys->parse(implode('.', $tampered)) === null, 'Manipulierter Schlüssel wird abgelehnt');
check($testKeys->parse('MGD1.abc') === null && $testKeys->parse('') === null, 'Fehlerhafte Schlüssel werden abgelehnt');

$expired = $testKeys->parse($signLicense(['expires_at' => '2000-01-01'] + $whitelabelPayload, sodium_crypto_sign_secretkey($testPair)));
check($expired !== null && $expired->isExpired(), 'Ablaufdatum wird erkannt');

$moduleWithoutId = $testKeys->parse($signLicense(['type' => 'module'] + $whitelabelPayload, sodium_crypto_sign_secretkey($testPair)));
check($moduleWithoutId === null, 'Modul-Schlüssel ohne module_id wird abgelehnt');

$label = (new PoweredBy(false, 'right'))->render('public_footer');
check(str_contains($label, 'powered by:') && str_contains($label, 'href="https://michael-gahn.de"') && str_contains($label, 'target="_blank"')
    && str_contains($label, 'alt="Michael Gahn DESIGN"') && str_contains($label, 'mgd-powered-by--right'), 'Label enthält Text, Logo, Link (neuer Tab) und Ausrichtung');
check((new PoweredBy(true))->render('public_footer') === '', 'Label entfällt nur mit Whitelabel-Lizenz');
check(str_contains((new PoweredBy(false, 'invalid'))->render('login'), 'mgd-powered-by--center'), 'Ungültige Ausrichtung fällt auf Mitte zurück');

$integrity = new LicenseIntegrity($root);
check($integrity->isIntact(), 'Lizenztext, Label und Logos sind unverändert: ' . implode(', ', $integrity->violations()));

$moduleDir = sys_get_temp_dir() . '/mgd-module-' . bin2hex(random_bytes(4)) . '/smoke-module';
mkdir($moduleDir . '/migrations', 0775, true);
file_put_contents($moduleDir . '/module.php', "<?php\nreturn static function (): void {};\n");
file_put_contents($moduleDir . '/module.json', json_encode([
    'id' => 'smoke-module', 'name' => 'Smoke', 'version' => '1.0.0', 'license' => 'paid', 'migrations' => 'migrations',
    'menu' => [['href' => '/admin/smoke', 'label' => 'Smoke', 'role' => 'editor']],
], JSON_THROW_ON_ERROR));
$manifest = ModuleManifest::fromDirectory($moduleDir);
check($manifest->paid && $manifest->menu[0]['href'] === '/admin/smoke' && $manifest->migrations !== null, 'Modul-Manifest wird gelesen');

file_put_contents($moduleDir . '/module.json', json_encode(['id' => 'smoke-module', 'name' => 'x', 'version' => '1.0.0', 'entry' => '../../etc/passwd'], JSON_THROW_ON_ERROR));
$escaped = false;
try {
    ModuleManifest::fromDirectory($moduleDir);
} catch (InvalidArgumentException) {
    $escaped = true;
}
check($escaped, 'Modul-Einstiegsdatei außerhalb des Modulordners wird abgelehnt');
