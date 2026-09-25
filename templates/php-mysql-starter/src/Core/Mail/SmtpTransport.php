<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Mail;

use RuntimeException;

/**
 * Minimaler SMTP-Client: STARTTLS (tls) oder implizites TLS (ssl), AUTH LOGIN, feste Timeouts.
 * Zertifikate werden immer geprüft.
 */
final class SmtpTransport
{
    private const DEFAULT_TIMEOUT = 10;
    private const MAX_RESPONSE_LINES = 100;

    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
    ) {
        if (preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1 || $port < 1 || $port > 65535) {
            throw new RuntimeException('SMTP-Host oder -Port ist ungültig.');
        }

        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('SMTP-Verschlüsselung muss tls, ssl oder none sein.');
        }
    }

    public static function fromConfig(array $smtp): self
    {
        return new self(
            (string) ($smtp['host'] ?? ''),
            (int) ($smtp['port'] ?? 587),
            (string) ($smtp['encryption'] ?? 'tls'),
            (string) ($smtp['username'] ?? ''),
            (string) ($smtp['password'] ?? ''),
            max(1, min(60, (int) ($smtp['timeout'] ?? self::DEFAULT_TIMEOUT))),
        );
    }

    public function send(string $from, string $to, string $rawMessage): void
    {
        Mailer::assertAddress($from, 'Absender');
        Mailer::assertAddress($to, 'Empfänger');

        try {
            $this->connect();
            $this->expect(220);
            $this->hello();
            $this->authenticate();
            $this->command('MAIL FROM:<' . $from . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', 354);
            $this->write(self::dotStuff($rawMessage) . "\r\n.");
            $this->expect(250);
            $this->command('QUIT', 221);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }

            $this->socket = null;
        }
    }

    public static function dotStuff(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);

        return (string) preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $normalized));
    }

    private function connect(): void
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->host]]);
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($scheme . $this->host . ':' . $this->port, $errno, $error, $this->timeout, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            throw new RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $error);
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function hello(): void
    {
        $name = preg_replace('/[^A-Za-z0-9.-]/', '', (string) gethostname()) ?: 'localhost';
        $this->command('EHLO ' . $name, 250);

        if ($this->encryption !== 'tls') {
            return;
        }

        $this->command('STARTTLS', 220);

        if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) !== true) {
            throw new RuntimeException('STARTTLS fehlgeschlagen.');
        }

        $this->command('EHLO ' . $name, 250);
    }

    private function authenticate(): void
    {
        if ($this->username === '') {
            return;
        }

        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    /**
     * @param int|list<int> $expected
     */
    private function command(string $line, int|array $expected): void
    {
        $this->write($line);
        $this->expect($expected);
    }

    private function write(string $data): void
    {
        if (@fwrite($this->socket, $data . "\r\n") === false) {
            throw new RuntimeException('SMTP-Schreibfehler.');
        }
    }

    /**
     * @param int|list<int> $expected
     */
    private function expect(int|array $expected): void
    {
        $code = 0;

        for ($i = 0; $i < self::MAX_RESPONSE_LINES; $i++) {
            $line = fgets($this->socket, 1024);

            if ($line === false) {
                throw new RuntimeException('SMTP-Server antwortet nicht (Zeitüberschreitung).');
            }

            $code = (int) substr($line, 0, 3);

            if (($line[3] ?? ' ') !== '-') {
                break;
            }
        }

        if (!in_array($code, (array) $expected, true)) {
            throw new RuntimeException('Unerwartete SMTP-Antwort (' . $code . ').');
        }
    }
}
