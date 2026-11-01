<?php

declare(strict_types=1);

use MGD\Backup\Format\BackupFormat;
use MGD\Backup\Format\BackupFormatException;
use MGD\Backup\Format\DbStreamReader;
use MGD\Backup\Format\DbStreamWriter;
use MGD\Backup\Format\FileRecordAssembler;
use MGD\Backup\Format\FileRecordReader;
use MGD\Backup\Format\FileRecordWriter;
use MGD\Backup\Format\ManifestCodec;
use MGD\Backup\Format\PartReader;
use MGD\Backup\Format\PartWriter;
use MGD\Backup\Format\RecipientKey;

// --- Format MGDB1: Round-Trip, Manipulation, Empfaenger ------------------------------------------------------
$dir = T::tempDir();
$a = T::keyPair();
$b = T::keyPair();
$other = T::keyPair();
$uuid = random_bytes(16);
$payload = random_bytes(200000) . 'ENDE';

$write = static function (string $path, array $recipients, string $uuid, int $seq = 1, string $data = '') use ($payload): void {
    $writer = PartWriter::open($path, BackupFormat::KIND_KEYS, $uuid, $seq, $recipients);
    $writer->write($data === '' ? $payload : $data);
    $writer->finish();
};
$read = static function (string $path, string $secret, ?string $uuid = null): string {
    $reader = PartReader::open($path, $secret, $uuid);
    $out = '';

    foreach ($reader->chunks() as $chunk) {
        $out .= $chunk;
    }

    return $out;
};

$file = $dir . '/part.mgdb';
$write($file, [RecipientKey::parse($a['text'])], $uuid);
T::check($read($file, $a['secret'], $uuid) === $payload, 'MGDB1: Round-Trip liefert den Klartext');
T::check(!str_contains((string) file_get_contents($file), 'ENDE') && !str_contains((string) file_get_contents($file), substr($payload, 0, 40)), 'MGDB1: kein Klartext in der Teildatei');
T::throws(static fn () => $read($file, $other['secret']), BackupFormatException::class, 'MGDB1: falscher Schluessel wird abgelehnt', BackupFormatException::NOT_A_RECIPIENT);
T::throws(static fn () => $read($file, $a['secret'], random_bytes(16)), BackupFormatException::class, 'MGDB1: falsche Run-UUID wird abgelehnt', BackupFormatException::RUN_MISMATCH);

$two = $dir . '/two.mgdb';
$write($two, [RecipientKey::parse($a['text']), RecipientKey::parse($b['text'])], $uuid);
T::check($read($two, $a['secret']) === $payload && $read($two, $b['secret']) === $payload, 'MGDB1: zwei Empfaenger koennen beide lesen');

// Gekipptes Byte an Stellen in Kopf, versiegeltem Schluessel, Stream-Header und Datenblock.
$bytes = (string) file_get_contents($file);
$size = strlen($bytes);
foreach ([3 => 'Kennung', 8 => 'Run-UUID im Kopf', 40 => 'versiegelter Schluessel', 120 => 'Stream-Header/Block', (int) ($size / 2) => 'Datenblock Mitte', $size - 3 => 'letzter Block'] as $position => $label) {
    $broken = $bytes;
    $broken[$position] = chr(ord($broken[$position]) ^ 0x01);
    file_put_contents($dir . '/flip.mgdb', $broken);
    $failed = false;

    try {
        $read($dir . '/flip.mgdb', $a['secret']);
    } catch (BackupFormatException) {
        $failed = true;
    }

    T::check($failed, 'MGDB1: gekipptes Byte (' . $label . ') wird erkannt');
}

// Abschneiden an mehreren Positionen (auch direkt vor dem letzten Block) und angehaengte Bytes.
foreach ([$size - 1, $size - 20, (int) ($size * 0.6), 60, 10] as $cut) {
    file_put_contents($dir . '/cut.mgdb', substr($bytes, 0, $cut));
    $failed = false;

    try {
        $read($dir . '/cut.mgdb', $a['secret']);
    } catch (BackupFormatException) {
        $failed = true;
    }

    T::check($failed, 'MGDB1: Abschneiden bei ' . $cut . ' von ' . $size . ' Byte wird erkannt');
}

file_put_contents($dir . '/append.mgdb', $bytes . 'x');
T::throws(static fn () => $read($dir . '/append.mgdb', $a['secret']), BackupFormatException::class, 'MGDB1: angehaengte Bytes werden erkannt');

// Tausch von Teilen zwischen zwei Laeufen: Datenbloecke an fremden Kopf kleben.
$uuid2 = random_bytes(16);
$swap1 = $dir . '/s1.mgdb';
$swap2 = $dir . '/s2.mgdb';
$write($swap1, [RecipientKey::parse($a['text'])], $uuid, 1, 'kleiner Inhalt eins');
$write($swap2, [RecipientKey::parse($a['text'])], $uuid2, 1, 'kleiner Inhalt zwei');
$headLength = PartReader::readHeader($swap1)['head_bytes'];
file_put_contents($dir . '/mixed.mgdb', substr((string) file_get_contents($swap1), 0, $headLength) . substr((string) file_get_contents($swap2), $headLength));
T::throws(static fn () => $read($dir . '/mixed.mgdb', $a['secret']), BackupFormatException::class, 'MGDB1: Bloecke eines anderen Laufs unter fremdem Kopf werden abgelehnt');

$versionBroken = $bytes;
$versionBroken[5] = chr(2);
file_put_contents($dir . '/version.mgdb', $versionBroken);
T::throws(static fn () => PartReader::open($dir . '/version.mgdb', $a['secret']), BackupFormatException::class, 'MGDB1: unbekannte Version wird abgelehnt', BackupFormatException::UNSUPPORTED_VERSION);

T::throws(static fn () => RecipientKey::parse('mgdbk-pk1:' . str_repeat('A', 43) . ':00000000'), BackupFormatException::class, 'Empfaengerschluessel: falsche Pruefsumme wird abgelehnt');
T::check(RecipientKey::parse($a['text'])->fingerprint() === $a['fp'], 'Empfaengerschluessel: Textform und Fingerabdruck stimmen');

// --- Manifest ------------------------------------------------------------------------------------------------
$sign = sodium_crypto_sign_keypair();
$signPublic = sodium_crypto_sign_publickey($sign);
$manifestFields = [
    'run_uuid' => '12345678-1234-4234-8234-123456789abc', 'kind' => 'db', 'trigger' => 'manual',
    'created_at' => '2026-10-27T10:00:00Z', 'finished_at' => '2026-10-27T10:01:00Z',
    'app' => ['version' => '1', 'php' => PHP_VERSION, 'db_driver' => 'sqlite', 'db_server' => 'x'],
    'schema' => ['migrations' => ['core_001_core']],
    'db' => ['consistency' => 'snapshot', 'tables' => [['name' => 'pages', 'rows' => 3]], 'excluded' => ['backup_runs']],
    'files' => ['areas' => ['uploads' => ['count' => 1, 'bytes' => 2], 'media-2' => ['count' => 0, 'bytes' => 0]], 'skipped_missing' => 0],
    'keys' => ['included' => false, 'app_key_id' => null, 'uploads_key_id' => null, 'previous_ids' => []],
    'recipients' => [$a['fp']], 'parts' => [],
    'signer' => ['alg' => 'ed25519', 'public_key' => base64_encode($signPublic), 'fp' => ManifestCodec::signerFingerprint($signPublic)],
];
$json = ManifestCodec::build($manifestFields);
$pair = ManifestCodec::sign($json, sodium_crypto_sign_secretkey($sign));
T::check(ManifestCodec::verify($pair, $signPublic)['kind'] === 'db', 'Manifest: Signatur und Struktur gueltig');
$tampered = $pair;
$tampered['manifest'] = base64_encode(str_replace('"rows":3', '"rows":4', $json));
T::throws(static fn () => ManifestCodec::verify($tampered, $signPublic), BackupFormatException::class, 'Manifest: veraenderter Inhalt bricht die Signatur', BackupFormatException::BAD_SIGNATURE);
$foreign = sodium_crypto_sign_publickey(sodium_crypto_sign_keypair());
T::throws(static fn () => ManifestCodec::verify($pair, $foreign), BackupFormatException::class, 'Manifest: falscher Signer wird abgelehnt');
$bad = $manifestFields;
$bad['files']['areas']['../x'] = ['count' => 0, 'bytes' => 0];
T::throws(static fn () => ManifestCodec::build($bad), BackupFormatException::class, 'Manifest: unsicherer Bereichsname wird abgelehnt', BackupFormatException::BAD_MANIFEST);

// --- DB-Strom -----------------------------------------------------------------------------------------------
$chunks = [];
$stream = new DbStreamWriter(static function (string $c) use (&$chunks): void {
    $chunks[] = $c;
});
$stream->beginTable('pages', ['id', 'title', 'bin']);
$stream->row([1, "Ümläut ✓", "\xff\xfe\x00"]);
$stream->row([2, null, 'x']);
$stream->endTable();
$stream->finish();
$events = iterator_to_array((new DbStreamReader($chunks))->events(), false);
T::check($events[1]['values'] === ['1', 'Ümläut ✓', "\xff\xfe\x00"] && $events[2]['values'][1] === null && $events[3]['rows'] === 2, 'DB-Strom: Werte inkl. UTF-8, Binaerdaten und NULL laufen verlustfrei');

// --- Datei-Datensaetze ---------------------------------------------------------------------------------------
$records = '';
$writer = new FileRecordWriter(static function (string $c) use (&$records): void {
    $records .= $c;
});
$writer->segment('uploads/sub/b.bin', 6, 0, 'abc');
$writer->segment('uploads/sub/b.bin', 6, 3, 'def');
$writer->end('uploads/sub/b.bin', hash('sha256', 'abcdef', true));
T::throws(static fn () => $writer->segment('uploads/../config.php', 1, 0, 'x'), BackupFormatException::class, 'Datei-Datensatz: ../ wird beim Schreiben abgelehnt', BackupFormatException::BAD_PATH);
T::throws(static fn () => $writer->segment('/etc/passwd', 1, 0, 'x'), BackupFormatException::class, 'Datei-Datensatz: absoluter Pfad wird abgelehnt', BackupFormatException::BAD_PATH);
$assembler = new FileRecordAssembler(null);

foreach ((new FileRecordReader([$records]))->records() as $record) {
    $assembler->accept($record);
}

T::check($assembler->finish()['areas']['uploads']['bytes'] === 6, 'Datei-Datensatz: Segmente ueber mehrere Records werden zusammengesetzt und geprueft');
// Manipulierter Datensatz mit ../ im Pfad (Zip-Slip) beim Lesen.
$evil = chr(1) . pack('n', 17) . 'uploads/../../etc/' . pack('J', 1) . pack('J', 0) . pack('N', 1) . 'x';
T::throws(static function () use ($evil): void {
    foreach ((new FileRecordReader([$evil]))->records() as $record) {
    }
}, BackupFormatException::class, 'Datei-Datensatz: ../ im gelesenen Pfad wird abgelehnt (Zip-Slip)', BackupFormatException::BAD_PATH);

T::check(BackupFormat::isSafeFilePath('uploads/2026/bild.jpg') && !BackupFormat::isSafeFilePath('uploads/./x') && !BackupFormat::isSafeFilePath('uploads/a b') && !BackupFormat::isSafeFilePath('Uploads/x'), 'Pfadregel: erlaubt Zeichensatz, verbietet ., .., Leerzeichen und Grossbuchstaben im Bereich');
T::check(BackupFormat::isSafeFilePath('uploads/1/2/0123abcd-0123-0123-0123-0123456789ab'), 'Pfadregel: Hub-Pfade bleiben gueltig (Obermenge)');
