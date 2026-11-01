<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations\Ftp;

use CurlHandle;
use MGD\Backup\BackupException;
use MGD\Backup\Destinations\HostPolicy;
use MGD\Backup\Failure;

/**
 * FTPS-Client auf ext-curl. KEIN ext-ftp: dort gibt es keine Kontextoptionen und keine sichere Zertifikatspruefung.
 *
 * Absicherung (alle in options() gebuendelt und ohne Verbindung testbar):
 *  - Klartext-FTP ist ausgeschlossen: die Modi sind explicit (ftp:// + CURLUSESSL_ALL, AUTH TLS) und implicit (ftps://);
 *    ein anderer Modus wirft schon im Konstruktor.
 *  - TLS ab 1.2, Peer- und Hostnamen-Pruefung, Passwort nur ueber CURLOPT_USERPWD (nie in der URL).
 *  - Der Host wird bei JEDER Anfrage ueber HostPolicy geprueft; die dabei geprueften Adressen werden per CURLOPT_RESOLVE
 *    festgeschrieben (kein zweiter DNS-Aufruf). CURLOPT_FTP_SKIP_PASV_IP verhindert, dass der Server den Datenkanal auf
 *    eine fremde Adresse umlenkt (FTP-Bounce/SSRF).
 *  - Nur die Protokolle FTP/FTPS, keine Weiterleitungen.
 *  - Fehler werden aus der CURLE_*-Nummer in Katalogcodes uebersetzt (Failure::fromCurl); curl-Texte, URL, Pfad und
 *    Passwort gelangen nie in eine Ausnahme.
 */
final class CurlFtpClient implements FtpClient
{
    public const MODE_EXPLICIT = 'explicit';

    public const MODE_IMPLICIT = 'implicit';

    public const ALLOWED_PORTS = [21, 990];

    private readonly string $host;

    /**
     * @param list<int> $allowedPorts
     */
    public function __construct(
        string $host,
        private readonly int $port,
        private readonly string $mode,
        private readonly string $username,
        private readonly string $password,
        private readonly HostPolicy $policy = new HostPolicy(),
        private readonly int $timeout = 60,
        private readonly array $allowedPorts = self::ALLOWED_PORTS,
    ) {
        if (!in_array($mode, [self::MODE_EXPLICIT, self::MODE_IMPLICIT], true) || !extension_loaded('curl')) {
            throw new BackupException('destination_unsupported');
        }

        // USERPWD trennt am ersten Doppelpunkt; Steuerzeichen haben im Benutzernamen nichts verloren.
        if ($username === '' || str_contains($username, ':') || preg_match('/[\x00-\x1f\x7f]/', $username) === 1) {
            throw new BackupException('auth_failed');
        }

        $this->host = HostPolicy::normalize($host);
    }

    // ------------------------------------------------------------------ Optionen

    /**
     * Alle curl-Optionen fuer eine Anfrage an $remotePath. Prueft dabei Port-Allowlist und HostPolicy (loest den Namen auf und
     * schreibt die geprueften Adressen per CURLOPT_RESOLVE fest). Baut selbst keine Verbindung auf.
     *
     * @return array<int,mixed>
     */
    public function options(string $remotePath = '/', bool $directory = true): array
    {
        if (!in_array($this->port, $this->allowedPorts, true)) {
            throw new BackupException('port_not_allowed');
        }

        $ips = $this->policy->resolveAllowed($this->host);

        if ($ips === null) {
            throw new BackupException('host_blocked');
        }

        $protocols = CURLPROTO_FTP | CURLPROTO_FTPS;
        $options = [
            CURLOPT_URL => $this->url($remotePath, $directory),
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_PROTOCOLS => $protocols,
            CURLOPT_REDIR_PROTOCOLS => $protocols,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2, // Mindestversion, keine Obergrenze
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FTP_SKIP_PASV_IP => true,
        ];

        // Ein IP-Literal braucht keine Namensaufloesung (HostPolicy hat es selbst geprueft).
        if (filter_var(trim($this->host, '[]'), FILTER_VALIDATE_IP) === false) {
            $addresses = array_map(static fn (string $ip): string => str_contains($ip, ':') ? '[' . $ip . ']' : $ip, $ips);
            $options[CURLOPT_RESOLVE] = [$this->host . ':' . $this->port . ':' . implode(',', $addresses)];
        }

        if ($this->mode === self::MODE_EXPLICIT) {
            $options[CURLOPT_FTPSSLAUTH] = CURLFTPAUTH_TLS;
        }

        return $options;
    }

    /** URL zu einem absoluten Pfad. "//pfad" = absolut ab Wurzel. Segmente sind prozentkodiert. */
    private function url(string $path, bool $directory): string
    {
        $encoded = implode('/', array_map('rawurlencode', self::segments($path)));
        $host = str_contains($this->host, ':') ? '[' . trim($this->host, '[]') . ']' : $this->host;
        $scheme = $this->mode === self::MODE_IMPLICIT ? 'ftps' : 'ftp';
        $url = $scheme . '://' . $host . ':' . $this->port . '//' . $encoded;

        return $directory && !str_ends_with($url, '/') ? $url . '/' : $url;
    }

    /**
     * Prueft einen absoluten Pfad und liefert seine Segmente. Verboten: relative Pfade, '.', '..', Steuerzeichen,
     * Anfuehrungszeichen, Rueckwaertsschraegstrich (Befehlseinschleusung ueber QUOTE, Pfadtricks).
     *
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        if ($path === '' || $path[0] !== '/' || preg_match('/[\x00-\x1f\x7f"\\\\]/', $path) === 1) {
            throw new BackupException('path_invalid');
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new BackupException('path_invalid');
            }
        }

        return $segments;
    }

    private static function normalizePath(string $path): string
    {
        return '/' . implode('/', self::segments($path));
    }

    // ------------------------------------------------------------------ Schnittstelle

    public function connect(): void
    {
        $this->exec('/', true, [CURLOPT_NOBODY => true]);
    }

    public function ensureDir(string $path): void
    {
        $path = self::normalizePath($path);

        if ($path === '/' || $this->dirExists($path)) {
            return;
        }

        $current = '';

        foreach (self::segments($path) as $segment) {
            $current .= '/' . $segment;

            if (!$this->dirExists($current)) {
                // Schlaegt MKD fehl (z. B. existiert schon), entscheidet die Pruefung danach.
                $this->exec('/', true, [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['MKD ' . $current]], [21]);
            }
        }

        if (!$this->dirExists($path)) {
            throw new BackupException('upload_failed');
        }
    }

    public function uploadChunk(string $localPath, string $remotePath, int $offset, int $maxBytes): int
    {
        $remotePath = self::normalizePath($remotePath);
        $total = @filesize($localPath);

        if ($total === false || $offset < 0 || $offset > $total || $maxBytes < 0) {
            throw new BackupException('io_error');
        }

        $length = min($maxBytes, $total - $offset);
        $handle = @fopen($localPath, 'rb');

        if ($handle === false) {
            throw new BackupException('io_error');
        }

        try {
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                throw new BackupException('io_error');
            }

            $remaining = $length;
            $this->exec($remotePath, false, [
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILESIZE => $length,
                CURLOPT_FTPAPPEND => $offset > 0,
                // Nur den Abschnitt senden, nicht den Rest der Datei.
                CURLOPT_READFUNCTION => static function ($ch, $stream, int $max) use ($handle, &$remaining): string {
                    if ($remaining <= 0) {
                        return '';
                    }

                    $data = fread($handle, min($max, $remaining));

                    if ($data === false) {
                        return '';
                    }

                    $remaining -= strlen($data);

                    return $data;
                },
            ]);

            if ($remaining !== 0) {
                throw new BackupException('io_error'); // Datei kuerzer als erwartet
            }
        } finally {
            fclose($handle);
        }

        return $offset + $length;
    }

    public function size(string $remotePath): ?int
    {
        [$ch, $errno] = $this->exec(self::normalizePath($remotePath), false, [CURLOPT_NOBODY => true], [9, 19, 78]);

        if ($errno !== 0) {
            return null; // Datei (oder ihr Verzeichnis) existiert nicht
        }

        $size = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T);

        if ($size < 0) {
            throw new BackupException('verify_failed'); // Server meldet keine Groesse: nicht pruefbar
        }

        return $size;
    }

    public function delete(string $remotePath): void
    {
        $remotePath = self::normalizePath($remotePath);
        [, $errno] = $this->exec('/', true, [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['DELE ' . $remotePath]], [21]);

        if ($errno !== 0 && $this->existsFile($remotePath)) {
            throw new BackupException('remote_delete_failed');
        }
    }

    public function deleteDir(string $path): void
    {
        $path = self::normalizePath($path);

        if ($path === '/') {
            throw new BackupException('path_invalid');
        }

        [, $errno] = $this->exec('/', true, [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['RMD ' . $path]], [21]);

        if ($errno !== 0 && $this->dirExists($path)) {
            throw new BackupException('remote_delete_failed');
        }
    }

    // ------------------------------------------------------------------ Hilfen

    private function existsFile(string $path): bool
    {
        try {
            return $this->size($path) !== null;
        } catch (BackupException) {
            return true; // nicht pruefbar: wie "noch da" behandeln
        }
    }

    private function dirExists(string $path): bool
    {
        [, $errno] = $this->exec($path, true, [CURLOPT_NOBODY => true], [9, 19, 21, 78]);

        return $errno === 0;
    }

    /**
     * Fuehrt eine Anfrage aus. Fehler werden als BackupException mit Katalogcode geworfen; die in $soft genannten CURLE-Nummern
     * liefert die Methode stattdessen als Ergebnis zurueck (der Aufrufer wertet sie aus).
     *
     * @param array<int,mixed> $extra
     * @param list<int> $soft
     * @return array{0:CurlHandle,1:int} Handle und CURLE-Nummer (0 = Erfolg)
     */
    private function exec(string $path, bool $directory, array $extra, array $soft = []): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, $extra + $this->options($path, $directory));
        curl_exec($ch);
        $errno = curl_errno($ch);

        if ($errno === 0 || in_array($errno, $soft, true)) {
            return [$ch, $errno];
        }

        if ($errno === 28 && (int) curl_getinfo($ch, CURLINFO_CONNECT_TIME_T) === 0) {
            $errno = 7; // Zeitueberschreitung schon beim Verbindungsaufbau: nicht erreichbar
        }

        throw new BackupException(Failure::fromCurl($errno));
    }
}
