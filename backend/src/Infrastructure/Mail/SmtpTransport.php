<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Mail;

use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailFailed;
use Paxofi\CorporateWebsite\Application\Mail\MailTransport;

/**
 * Minimal SMTP client (D-016) for the company mailbox on the hosting
 * account: implicit TLS (port 465, "ssl"), STARTTLS (587, "tls") or plain
 * (tests only), AUTH LOGIN, plain-text UTF-8 messages. Certificates are
 * verified; the password never appears in errors or logs.
 */
final class SmtpTransport implements MailTransport
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly int $timeoutSeconds = 15,
        private readonly bool $verifyPeer = true,
    ) {
    }

    public function send(Email $email): void
    {
        try {
            $this->connect();
            $this->expect(220);
            $this->hello();
            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', 220);
                if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) !== true) {
                    throw new MailFailed('STARTTLS negotiation failed.');
                }
                $this->hello();
            }
            if ($this->username !== null && $this->username !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($this->username), 334);
                $this->command(base64_encode((string) $this->password), 235, 'AUTH (password hidden)');
            }
            $this->command('MAIL FROM:<' . $this->fromAddress . '>', 250);
            foreach ($email->to as $recipient) {
                $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
            }
            $this->command('DATA', 354);
            $this->write(MessageFormatter::format($email, $this->fromAddress, $this->fromName) . "\r\n.\r\n");
            $this->expect(250);
            $this->command('QUIT', [221, 250], ignoreFailure: true);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function connect(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => [
            'verify_peer' => $this->verifyPeer,
            'verify_peer_name' => $this->verifyPeer,
            'peer_name' => $this->host,
            'SNI_enabled' => true,
        ]]);
        $socket = @stream_socket_client("{$scheme}://{$this->host}:{$this->port}", $errno, $error, $this->timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new MailFailed("Could not connect to the mail server {$this->host}:{$this->port} ({$error}).");
        }
        stream_set_timeout($socket, $this->timeoutSeconds);
        $this->socket = $socket;
    }

    private function hello(): void
    {
        $name = gethostname() ?: 'localhost';
        $this->command('EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', $name), 250);
    }

    /** @param int|list<int> $expected */
    private function command(string $line, int|array $expected, ?string $shown = null, bool $ignoreFailure = false): void
    {
        $this->write($line . "\r\n");
        try {
            $this->expect($expected, $shown ?? $line);
        } catch (MailFailed $exception) {
            if (!$ignoreFailure) {
                throw $exception;
            }
        }
    }

    private function write(string $data): void
    {
        $length = strlen($data);
        for ($written = 0; $written < $length; $written += $chunk) {
            $chunk = @fwrite($this->socket, substr($data, $written, 8192));
            if ($chunk === false || $chunk === 0) {
                throw new MailFailed('The connection to the mail server was lost.');
            }
        }
    }

    /** @param int|list<int> $expected */
    private function expect(int|array $expected, string $after = 'connect'): void
    {
        $reply = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            $verb = strtok($after, ' ') ?: $after;
            if (str_starts_with($after, 'AUTH')) {
                $verb = 'AUTH';
            }
            throw new MailFailed(sprintf('Mail server replied %s to %s: %s', $code === 0 ? 'nothing' : (string) $code, $verb, Email::oneLine(substr($reply, 4, 200))));
        }
    }
}
