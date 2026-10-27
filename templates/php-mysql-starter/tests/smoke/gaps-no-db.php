<?php

declare(strict_types=1);

/*
 * Teil von tests/smoke.php (ohne Datenbank): CSS-Sanitizer, Mailer, Medienprüfung,
 * GrapesJS-Eingaben, Update-Manifest, Passwortregeln. Nutzt check() aus smoke.php.
 */

use MGD\Starter\Cms\PageInput;
use MGD\Starter\Core\Auth\PasswordPolicy;
use MGD\Starter\Core\Auth\PasswordResets;
use MGD\Starter\Core\Mail\Mailer;
use MGD\Starter\Core\Mail\SmtpTransport;
use MGD\Starter\Core\Security\CssSanitizer;
use MGD\Starter\Core\Security\HtmlSanitizer;
use MGD\Starter\Core\Update\UpdateChecker;
use MGD\Starter\Core\Version\Version;
use MGD\Starter\Media\MediaValidator;

$expectInvalid = static function (callable $callback): bool {
    try {
        $callback();
    } catch (InvalidArgumentException | RuntimeException) {
        return true;
    }

    return false;
};

// CSS-Sanitizer
$css = (new CssSanitizer())->sanitize(
    '@import url("https://evil.test/x.css"); @charset "utf-8";'
    . 'body{margin:0} * { box-sizing: border-box; }'
    . '#hero{background:url(https://evil.test/a.png);color:red}'
    . '.logo{background-image:url("/uploads/2026/09/aa.png")}'
    . '.x{width:expression(alert(1));behavior:url(x.htc);-moz-binding:url(x);height:1px}'
    . '.y{background:url(javascript:alert(1))}'
    . '@media (max-width: 600px){.card, h2 > span{padding:4px}}'
    . '@font-face{font-family:X;src:url(https://fonts.example/x.woff2)}'
    . '@font-face{font-family:Y;src:url(/assets/fonts/y.woff2)}'
    . '.z{content:"</style><script>alert(1)</script>"}'
);
check(!str_contains($css, '@import') && !str_contains($css, '@charset') && !str_contains($css, 'evil.test'), 'CSS: @import, @charset und externe url() entfernt');
check(!str_contains($css, 'expression') && !str_contains($css, 'behavior') && !str_contains($css, 'binding') && !str_contains($css, 'javascript'), 'CSS: expression/behavior/-moz-binding/javascript: entfernt');
check(!str_contains($css, '<') && !str_contains($css, 'javascript') && str_contains($css, '.page-content .x{height:1px}'), 'CSS: "<" entfernt (kein </style>), sichere Deklarationen bleiben');
check(str_contains($css, '.page-content{margin:0}') && str_contains($css, '.page-content *{box-sizing:border-box}'), 'CSS: body und * werden auf .page-content begrenzt');
check(str_contains($css, '@media (max-width: 600px){.page-content .card,.page-content h2 > span{padding:4px}}'), 'CSS: Selektoren in @media begrenzt');
check(str_contains($css, 'url("/uploads/2026/09/aa.png")') && str_contains($css, 'font-family:Y') && !str_contains($css, 'font-family:X'), 'CSS: lokale url() erlaubt, @font-face mit externer Quelle verworfen');

// Mailer: Header-Injection (ohne Netzwerk)
check($expectInvalid(static fn () => Mailer::buildMessage('a@example.org', 'Site', "b@example.org\r\nBcc: x@evil.test", 'Hallo', 'x')), 'Mailer: Zeilenumbruch in Empfänger abgelehnt');
check($expectInvalid(static fn () => Mailer::buildMessage('a@example.org', 'Site', 'b@example.org', "Hallo\nBcc: x@evil.test", 'x')), 'Mailer: Zeilenumbruch im Betreff abgelehnt');
check($expectInvalid(static fn () => Mailer::buildMessage('a@example.org', "Site\r\nX-Evil: 1", 'b@example.org', 'Hallo', 'x')), 'Mailer: Zeilenumbruch im Absendernamen abgelehnt');
$message = Mailer::buildMessage('no-reply@example.org', 'Größe', 'user@example.org', 'Passwort zurücksetzen', "Zeile 1\n.Punkt");
check(str_contains($message['headers'], 'From: =?UTF-8?Q?') && str_contains($message['raw'], 'Subject: Passwort =?UTF-8?Q?zur=C3=BCcksetzen?=') && str_contains($message['body'], "Zeile 1\r\n.Punkt"), 'Mailer: Header UTF-8-kodiert');
check(str_contains(SmtpTransport::dotStuff("a\n.b"), "\r\n..b"), 'SMTP: Dot-Stuffing');
check(!(new Mailer(['transport' => 'disabled'], 'no-reply@example.org'))->isEnabled(), 'Mailer: Transport "disabled" ist inaktiv');

// Medien: MIME-Spoofing, erlaubte Typen, GD-Neukodierung
$validator = new MediaValidator(1_000_000);
$fake = tempnam(sys_get_temp_dir(), 'mgd-media-');
file_put_contents($fake, "<?php echo 'x'; ?>");
check($expectInvalid(static fn () => $validator->validate($fake, 'bild.png')), 'Medien: PHP-Inhalt als .png abgelehnt');
check($expectInvalid(static fn () => $validator->validate($fake, 'bild.svg')) && $expectInvalid(static fn () => $validator->validate($fake, 'seite.html')), 'Medien: SVG und HTML nicht erlaubt');

if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
    $image = imagecreatetruecolor(4, 3);
    imagepng($image, $fake);
    $info = $validator->validate($fake, '../../Mein Bild.PNG');
    check($info['mime'] === 'image/png' && $info['width'] === 4 && $info['original_name'] === 'Mein Bild.PNG', 'Medien: echtes PNG akzeptiert, Name bereinigt');
    check($expectInvalid(static fn () => $validator->validate($fake, 'bild.jpg')), 'Medien: PNG mit Endung .jpg abgelehnt');
    file_put_contents($fake, 'TRAILING-METADATA', FILE_APPEND);
    check(MediaValidator::reencode($fake, 'image/png') && !str_contains((string) file_get_contents($fake), 'TRAILING-METADATA'), 'Medien: GD-Neukodierung entfernt Anhängsel');
} else {
    echo "skip Medien: GD nicht verfügbar\n";
}

@unlink($fake);

// GrapesJS-Eingaben
$pageInput = new PageInput(new HtmlSanitizer(), maxProjectBytes: 1000);
$grapes = $pageInput->normalize([
    'slug' => 'design', 'title' => 'Design', 'content_format' => 'grapesjs',
    'content_html' => '<div id="i3k" class="hero" style="color:red" onclick="x()">Hallo</div>',
    'content_css' => '#i3k{color:blue}', 'content_source' => '{"pages":[{"component":{}}]}',
]);
check($grapes['content_format'] === 'grapesjs' && $grapes['content_css'] === '.page-content #i3k{color:blue}', 'GrapesJS: CSS bereinigt und begrenzt');
check(!str_contains($grapes['content_html'], 'style=') && !str_contains($grapes['content_html'], 'onclick') && str_contains($grapes['content_html'], 'id="i3k"'), 'GrapesJS: HTML ohne style/on*, IDs bleiben');
check($expectInvalid(static fn () => $pageInput->normalize(['slug' => 'd', 'title' => 'D', 'content_format' => 'grapesjs', 'content_source' => '{kaputt'])), 'GrapesJS: ungültiges Projekt-JSON abgelehnt');
check($expectInvalid(static fn () => $pageInput->normalize(['slug' => 'd', 'title' => 'D', 'content_format' => 'grapesjs', 'content_source' => '{"a":"' . str_repeat('x', 2000) . '"}'])), 'GrapesJS: zu große Projektdaten abgelehnt');

// Update-Manifest (ohne Netzwerk)
$current = new Version('1.0.0', MGD\Starter\Core\Version\VersionStatus::from('stable'));
check(UpdateChecker::buildUrl('https://updates.example.org/manifest.json?product=cms#x', 'beta') === 'https://updates.example.org/manifest.json?product=cms&channel=beta', 'Updater: channel-Parameter an bestehende Query angehängt');
$flat = UpdateChecker::parseManifest(['version' => '1.1.0', 'status' => 'stable', 'notes_url' => 'http://insecure.example'], 'stable', $current);
check($flat['newer'] && $flat['version'] === '1.1.0' && $flat['notes_url'] === '' && $flat['channel'] === 'stable', 'Updater: flaches Manifest');
$channels = ['channels' => ['stable' => ['version' => '1.0.0', 'status' => 'stable'], 'beta' => ['version' => '1.2.0', 'status' => 'beta']]];
$beta = UpdateChecker::parseManifest($channels, 'beta', $current);
$lts = UpdateChecker::parseManifest($channels, 'lts', $current);
check($beta['version'] === '1.2.0' && $beta['newer'] && $lts['channel'] === 'stable' && !$lts['newer'], 'Updater: Kanal-Manifest mit Fallback auf stable');
check($expectInvalid(static fn () => UpdateChecker::parseManifest(['channels' => ['beta' => ['version' => '1.2.0', 'status' => 'beta']]], 'alpha', $current)), 'Updater: fehlender Kanal ohne stable ist ein Fehler');
check($expectInvalid(static fn () => (new UpdateChecker())->check('https://updates.example.org/m.json', $current, 'nightly')), 'Updater: unbekannter Kanal abgelehnt');

// Passwortregeln
$generated = PasswordPolicy::generate();
check(strlen($generated) === 16 && $generated !== PasswordPolicy::generate(), 'Einmal-Passwörter sind zufällig und 16 Zeichen lang');
check($expectInvalid(static fn () => PasswordPolicy::assertValid('kurz')), 'Zu kurzes Passwort abgelehnt');
check(PasswordResets::hashToken(str_repeat('a', 64)) === hash('sha256', str_repeat('a', 64)), 'Reset-Token wird als SHA-256 gespeichert');
