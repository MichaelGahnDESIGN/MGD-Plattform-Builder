<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Mail;

use InvalidArgumentException;
use RuntimeException;

/**
 * Minimaler Versand von Text-E-Mails über mail() oder SMTP (siehe SmtpTransport).
 * Konfiguration in config.php unter "mail". Header-Injection wird abgewiesen.
 */
final class Mailer
{
    public const TRANSPORTS = ['mail', 'smtp', 'disabled'];
    private const MAX_SUBJECT_LENGTH = 200;

    /**
     * @param array{transport?: string, smtp?: array<string, mixed>} $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $fromAddress,
        private readonly string $fromName = '',
    ) {
    }

    public function isEnabled(): bool
    {
        return in_array($this->transport(), ['mail', 'smtp'], true)
            && filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(string $to, string $subject, string $textBody): void
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('E-Mail-Versand ist nicht konfiguriert.');
        }

        $message = self::buildMessage($this->fromAddress, $this->fromName, $to, $subject, $textBody);

        if ($this->transport() === 'smtp') {
            SmtpTransport::fromConfig((array) ($this->config['smtp'] ?? []))->send($this->fromAddress, $to, $message['raw']);

            return;
        }

        if (!mail($to, $message['subject'], $message['body'], $message['headers'])) {
            throw new RuntimeException('mail() hat die Nachricht nicht angenommen.');
        }
    }

    /**
     * Baut Header und Body. Wirft bei ungültigen Adressen oder Zeilenumbrüchen in Header-Werten.
     *
     * @return array{subject: string, headers: string, body: string, raw: string}
     */
    public static function buildMessage(string $from, string $fromName, string $to, string $subject, string $textBody): array
    {
        self::assertAddress($from, 'Absender');
        self::assertAddress($to, 'Empfänger');
        self::assertHeaderValue($fromName, 'Absendername');
        self::assertHeaderValue($subject, 'Betreff');

        if ($subject === '' || mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            throw new InvalidArgumentException('Betreff fehlt oder ist zu lang.');
        }

        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'Q', "\r\n");
        $fromHeader = $fromName !== '' ? mb_encode_mimeheader($fromName, 'UTF-8', 'Q', "\r\n") . ' <' . $from . '>' : $from;
        $domain = substr((string) strrchr($from, '@'), 1);
        $headers = implode("\r\n", [
            'From: ' . $fromHeader,
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ]);
        $body = quoted_printable_encode(str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $textBody)));
        $raw = 'To: ' . $to . "\r\n" . 'Subject: ' . $encodedSubject . "\r\n" . $headers . "\r\n\r\n" . $body;

        return ['subject' => $encodedSubject, 'headers' => $headers, 'body' => $body, 'raw' => $raw];
    }

    public static function assertHeaderValue(string $value, string $label): void
    {
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException($label . ' darf keine Zeilenumbrüche enthalten.');
        }
    }

    public static function assertAddress(string $address, string $label): void
    {
        self::assertHeaderValue($address, $label);

        if (strlen($address) > 254 || filter_var($address, FILTER_VALIDATE_EMAIL) === false || preg_match('/[<>,;"\s]/', $address) === 1) {
            throw new InvalidArgumentException($label . ': ungültige E-Mail-Adresse.');
        }
    }

    private function transport(): string
    {
        $transport = (string) ($this->config['transport'] ?? 'disabled');

        return in_array($transport, self::TRANSPORTS, true) ? $transport : 'disabled';
    }
}
