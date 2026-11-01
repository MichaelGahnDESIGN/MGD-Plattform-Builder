<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use RuntimeException;

/**
 * Fehler im Backup-Format (MGDB1 und zugehoerige Klartextformate).
 *
 * Die Meldung ist immer nur der feste Fehlercode, nie Dateiinhalt, Schluessel, Pfad oder eine Bibliotheksmeldung.
 * Vorherige Ausnahmen werden bewusst nicht verkettet.
 */
final class BackupFormatException extends RuntimeException
{
    public const BAD_MAGIC = 'bad_magic';

    public const UNSUPPORTED_VERSION = 'unsupported_version';

    public const BAD_HEADER = 'bad_header';

    public const TRUNCATED = 'truncated';

    public const TRAILING_DATA = 'trailing_data';

    public const CORRUPT_BLOCK = 'corrupt_block';

    public const NOT_A_RECIPIENT = 'not_a_recipient';

    public const KEY_UNSEAL_FAILED = 'key_unseal_failed';

    public const BAD_KEY = 'bad_key';

    public const RUN_MISMATCH = 'run_mismatch';

    public const OPEN_FAILED = 'open_failed';

    public const WRITE_FAILED = 'write_failed';

    public const BAD_ARGUMENT = 'bad_argument';

    public const ALREADY_FINISHED = 'already_finished';

    public const ALREADY_CONSUMED = 'already_consumed';

    public const RECIPIENT_KEY_FORMAT = 'recipient_key_format';

    public const RECIPIENT_KEY_CHECKSUM = 'recipient_key_checksum';

    public const KEY_FILE_FORMAT = 'key_file_format';

    public const KEY_FILE_PERMISSIONS = 'key_file_permissions';

    public const PASSPHRASE_REQUIRED = 'passphrase_required';

    public const BAD_PASSPHRASE = 'bad_passphrase';

    public const BAD_MANIFEST = 'bad_manifest';

    public const BAD_SIGNATURE = 'bad_signature';

    public const SIGNER_MISMATCH = 'signer_mismatch';

    public const BAD_DB_STREAM = 'bad_db_stream';

    public const BAD_FILE_RECORD = 'bad_file_record';

    public const BAD_PATH = 'bad_path';

    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
