<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations;

use Closure;
use MGD\Backup\BackupException;
use MGD\Backup\Destinations\Ftp\CurlFtpClient;
use MGD\Backup\Destinations\Ftp\FtpClient;
use MGD\Backup\Paths;
use MGD\Backup\Secrets;

/**
 * Baut den Treiber zu einem Ziel (Zeile aus backup_destinations). Im Builder-Repository gibt es die Typen "local" und
 * "ftp" (FTPS/FTP ueber ext-curl). Weitere Treiber lassen sich ueber register() nachruesten (Marketplace-Module); ihre
 * Typkennung muss ^[a-z0-9]{2,8}$ erfuellen.
 */
class DriverFactory
{
    public const BUILT_IN = ['local', 'ftp'];

    /** @var array<string,Closure(array<string,mixed>):DestinationDriver> */
    private array $custom = [];

    /**
     * @param (Closure(array<string,mixed>,string):FtpClient)|null $ftpClient Test-Hook: baut den FTP-Client (Ziel, Passwort)
     * @param array<string,mixed> $options allow_private_hosts, upload_chunk_bytes, http_timeout, ftp_allowed_ports
     */
    public function __construct(
        private readonly Paths $paths,
        private readonly ?Secrets $secrets,
        private readonly array $options = [],
        private readonly ?Closure $ftpClient = null,
    ) {
    }

    /** @param Closure(array<string,mixed>):DestinationDriver $builder */
    public function register(string $type, Closure $builder): void
    {
        if (preg_match('/^[a-z0-9]{2,8}$/D', $type) !== 1 || in_array($type, self::BUILT_IN, true)) {
            throw new BackupException('destination_unsupported');
        }

        $this->custom[$type] = $builder;
    }

    /** @return list<string> alle bekannten Typen (eingebaute und nachgeruestete) */
    public function types(): array
    {
        return [...self::BUILT_IN, ...array_keys($this->custom)];
    }

    /** @param array<string,mixed> $destination Zeile aus backup_destinations */
    public function make(array $destination): DestinationDriver
    {
        $type = (string) ($destination['dest_type'] ?? '');

        if ($type === 'local') {
            return new LocalDriver($this->paths);
        }

        if ($type === 'ftp') {
            return $this->ftp($destination);
        }

        if (isset($this->custom[$type])) {
            return ($this->custom[$type])($destination);
        }

        throw new BackupException('destination_unsupported');
    }

    /** Entschluesselte Zugangsdaten eines Ziels (leeres Array, wenn keine gespeichert sind). */
    public function secretOf(array $destination): array
    {
        $enc = (string) ($destination['secret_enc'] ?? '');

        if ($enc === '' || $this->secrets === null) {
            return [];
        }

        $data = json_decode($this->secrets->decrypt($enc), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string,mixed> $destination */
    private function ftp(array $destination): DestinationDriver
    {
        $config = json_decode((string) ($destination['config_json'] ?? ''), true);
        $config = is_array($config) ? $config : [];
        $password = (string) ($this->secretOf($destination)['password'] ?? '');

        if ($password === '') {
            throw new BackupException('auth_failed'); // Passwort fehlt (z. B. nach einer Zieländerung verworfen)
        }

        $client = $this->ftpClient !== null
            ? ($this->ftpClient)($destination, $password)
            : $this->curlClient($config, $password);

        return new FtpDriver((string) ($config['base_path'] ?? '/'), $client, max(65536, (int) ($this->options['upload_chunk_bytes'] ?? 8388608)));
    }

    /** @param array<string,mixed> $config */
    private function curlClient(array $config, string $password): FtpClient
    {
        $mode = ($config['mode'] ?? 'explicit') === 'implicit' ? CurlFtpClient::MODE_IMPLICIT : CurlFtpClient::MODE_EXPLICIT;
        $ports = array_map('intval', (array) ($this->options['ftp_allowed_ports'] ?? CurlFtpClient::ALLOWED_PORTS));

        return new CurlFtpClient(
            (string) ($config['host'] ?? ''),
            (int) ($config['port'] ?? ($mode === CurlFtpClient::MODE_IMPLICIT ? 990 : 21)),
            $mode,
            (string) ($config['username'] ?? ''),
            $password,
            new HostPolicy(($this->options['allow_private_hosts'] ?? false) === true),
            max(5, (int) ($this->options['http_timeout'] ?? 60)),
            $ports,
        );
    }
}
