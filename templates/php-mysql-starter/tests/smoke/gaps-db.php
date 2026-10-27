<?php

declare(strict_types=1);

/*
 * Teil von tests/smoke.php (mit Datenbank): Benutzerverwaltung, Passwort-Reset, Medien, GrapesJS-Revisionen.
 * Erwartet $app, $suffix, $editor und check() aus smoke.php. Nicht destruktiv: eigene Testdaten werden entfernt,
 * die Prüfung "letzter Admin" läuft in einer Transaktion, die zurückgerollt wird.
 */

use MGD\Starter\Core\Auth\PasswordResets;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\UserManager;

$failsWith = static function (callable $callback): bool {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
};

// Benutzerverwaltung
$users = $app->users();
$manager = new UserManager($users);
$adminEmail = 'smoke-admin-' . $suffix . '@example.test';
$created = $manager->create($adminEmail, 'Smoke Admin', Role::Admin);
$admin = $users->findActive($created['id']);
check($created['generated'] && strlen($created['password']) === 16 && $admin !== null && $admin->mustChangePassword, 'Neues Konto mit erzeugtem Einmal-Passwort und Pflichtwechsel');
check($failsWith(static fn () => $manager->changeOwnPassword($admin, 'falsch-' . $suffix, 'neues-passwort-' . $suffix, 'neues-passwort-' . $suffix)), 'Eigenes Passwort nur mit aktuellem Passwort');
$manager->changeOwnPassword($admin, $created['password'], 'neues-passwort-' . $suffix, 'neues-passwort-' . $suffix);
$admin = $users->findActive($created['id']);
check(!$admin->mustChangePassword && $users->findCredentials($adminEmail) !== null, 'Passwortwechsel entfernt Pflichtwechsel');

$member = $manager->create('smoke-member-' . $suffix . '@example.test', 'Smoke Member', Role::Moderator, 'startpasswort-' . $suffix);
$changed = $manager->update($admin, $member['id'], 'Smoke Member 2', 'smoke-member2-' . $suffix . '@example.test', Role::Editor, 'disabled');
$row = $users->find($member['id']);
check($changed === ['profile', 'role', 'status'] && $row['role'] === 'editor' && $row['status'] === 'disabled' && $users->findActive($member['id']) === null, 'Konto bearbeiten: Name, E-Mail, Rolle, Status');
check($users->list('smoke-member2-' . $suffix, 'editor', 'disabled')['total'] === 1, 'Kontoliste mit Suche und Filtern');
check($failsWith(static fn () => $manager->update($admin, $admin->id, 'Smoke Admin', $adminEmail, Role::Editor, 'active')), 'Eigene Rolle kann nicht geändert werden');
check($failsWith(static fn () => $manager->delete($admin, $admin->id)), 'Eigenes Konto kann nicht gelöscht werden');
$oneTime = $manager->resetPassword($member['id']);
check(strlen($oneTime) === 16 && (int) $users->find($member['id'])['must_change_password'] === 1, 'Admin-Reset setzt Einmal-Passwort');

$core = $app->databases->core();
$core->beginTransaction();

try {
    $core->prepare("UPDATE users SET status = 'disabled' WHERE role = 'admin' AND id <> :id")->execute(['id' => $admin->id]);
    $otherAdmin = $manager->create('smoke-admin2-' . $suffix . '@example.test', 'Smoke Admin 2', Role::Editor);
    $actor = new MGD\Starter\Core\Auth\User($otherAdmin['id'], 'x@example.test', 'Andere Person', Role::Admin);
    check($users->countActiveAdmins() === 1, 'Genau ein aktiver Admin (Transaktion)');
    check($failsWith(static fn () => $manager->update($actor, $admin->id, 'Smoke Admin', $adminEmail, Role::Editor, 'active')), 'Letzter Admin kann nicht herabgestuft werden');
    check($failsWith(static fn () => $manager->update($actor, $admin->id, 'Smoke Admin', $adminEmail, Role::Admin, 'disabled')), 'Letzter Admin kann nicht deaktiviert werden');
    check($failsWith(static fn () => $manager->delete($actor, $admin->id)), 'Letzter Admin kann nicht gelöscht werden');
} finally {
    $core->rollBack();
}

// Passwort-Reset per Token
$resets = $app->passwordResets();
$ip = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
check($resets->request('unbekannt-' . $suffix . '@example.test', $ip) === null, 'Reset für unbekannte E-Mail liefert kein Token');
$reset = $resets->request($adminEmail, $ip);
$stored = $core->prepare('SELECT token_hash, TIMESTAMPDIFF(MINUTE, created_at, expires_at) AS ttl FROM password_resets WHERE user_id = :id AND used_at IS NULL');
$stored->execute(['id' => $admin->id]);
$tokenRow = $stored->fetch();
check($reset !== null && preg_match('/^[a-f0-9]{64}$/', $reset['token']) === 1, 'Reset-Token: 32 Zufallsbytes (hex)');
check($tokenRow['token_hash'] === hash('sha256', $reset['token']) && $tokenRow['token_hash'] !== $reset['token'] && (int) $tokenRow['ttl'] === PasswordResets::TOKEN_TTL_MINUTES, 'Reset-Token: nur Hash gespeichert, 60 Minuten gültig');
check($resets->consume($reset['token'], 'reset-passwort-' . $suffix, 'reset-passwort-' . $suffix) === $admin->id, 'Reset-Token setzt neues Passwort');
check(!$resets->isValid($reset['token']) && $failsWith(static fn () => $resets->consume($reset['token'], 'noch-ein-passwort-' . $suffix, 'noch-ein-passwort-' . $suffix)), 'Reset-Token ist nur einmal nutzbar');
$expired = $resets->request($adminEmail, $ip);
$core->prepare('UPDATE password_resets SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE token_hash = :hash')->execute(['hash' => hash('sha256', $expired['token'])]);
check(!$resets->isValid($expired['token']), 'Abgelaufenes Reset-Token ist ungültig');
check($resets->request($adminEmail, $ip) !== null, 'Dritte Reset-Anfrage pro Stunde ist erlaubt');
check($resets->request($adminEmail, $ip) === null, 'Reset-Anfragen werden pro E-Mail gedrosselt (max. 3 pro Stunde)');

// GrapesJS: Revisionen, Export/Import mit CSS und Projektdaten
$pages = $app->pages();
$input = $app->pageInput();
$designSlug = 'design-' . $suffix;
$project = '{"pages":[{"component":{"type":"wrapper"}}],"styles":[]}';
$designId = $pages->create($input->normalize(['slug' => $designSlug, 'title' => 'Design', 'status' => 'published', 'content_format' => 'grapesjs',
    'content_html' => '<div id="a1">A</div>', 'content_css' => '#a1{color:red}', 'content_source' => $project]), $editor);
$pages->update($designId, $input->normalize(['slug' => $designSlug, 'title' => 'Design', 'status' => 'published', 'content_format' => 'grapesjs',
    'content_html' => '<div id="a1">B</div>', 'content_css' => '#a1{color:blue}', 'content_source' => $project]), $editor, 'Design');
$revisions = $pages->revisions($designId);
$firstDesign = $pages->revision((int) end($revisions)['id']);
check($firstDesign['content_css'] === '.page-content #a1{color:red}' && $firstDesign['content_source'] === $project, 'GrapesJS-Revision enthält CSS und Projektdaten');
$pages->restoreRevision((int) $firstDesign['id'], $editor);
check($pages->find($designId)['content_css'] === '.page-content #a1{color:red}', 'GrapesJS-Revision wiederhergestellt inkl. CSS');
$designExport = $app->pageTransfer()->export([$designId], true);
check($designExport['pages'][0]['content_css'] === '.page-content #a1{color:red}' && $designExport['pages'][0]['revisions'][0]['content_css'] !== null, 'Export enthält CSS (Seite und Revisionen)');
$designExport['pages'][0]['slug'] = 'design-import-' . $suffix;
$designExport['pages'][0]['content_css'] = '@import url(https://evil.test/x.css); #a1{color:green}';
$app->pageTransfer()->import($designExport, $editor);
$imported = $pages->findBySlug('design-import-' . $suffix, false);
check($imported['content_format'] === 'grapesjs' && $imported['content_css'] === '.page-content #a1{color:green}' && $imported['content_source'] === $project, 'Import: GrapesJS-Seite mit bereinigtem CSS');

// Medien: Metadaten, Alt-Text, Verwendungsprüfung
$media = $app->media();
$mediaPath = '/uploads/2026/09/' . bin2hex(random_bytes(16)) . '.png';
$mediaId = $media->create(['path' => $mediaPath, 'original_name' => 'smoke.png', 'mime' => 'image/png', 'size_bytes' => 10, 'width' => 1, 'height' => 1], $admin->id);
$media->updateAlt($mediaId, 'Alt ' . $suffix);
check($media->find($mediaId)['alt_text'] === 'Alt ' . $suffix && in_array($mediaPath, $media->imagePaths(), true), 'Medien: Eintrag und Alternativtext');
check($media->references($mediaPath) === [], 'Medien: unbenutzte Datei hat keine Verweise');
$pages->update($designId, $input->normalize(['slug' => $designSlug, 'title' => 'Design', 'status' => 'published', 'content_format' => 'grapesjs',
    'content_html' => '<img src="' . $mediaPath . '" alt="">', 'content_css' => '', 'content_source' => '']), $editor, 'Bild');
$app->settings()->save('seo.og_image', $mediaPath, null);
$references = $media->references($mediaPath);
check(count($references) === 2, 'Medien: Verweise in Seite und Einstellungen gefunden');
$app->settings()->save('seo.og_image', '', null);
$media->delete($mediaId);
check($media->find($mediaId) === null, 'Medien: Eintrag gelöscht');

// Aufräumen
foreach ([$admin->id, $member['id']] as $cleanupId) {
    $users->delete($cleanupId);
}

check($users->find($admin->id) === null, 'Testkonten entfernt');
