<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Mail;

use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailFailed;
use Paxofi\CorporateWebsite\Infrastructure\Mail\SmtpTransport;
use PHPUnit\Framework\TestCase;

/** The SMTP client (D-016) against a scripted local server (plain TCP; production uses TLS). */
final class SmtpTransportTest extends TestCase
{
    public function testSendsAnAuthenticatedUtf8Message(): void
    {
        [$transcript, $process] = $this->server();
        $this->transport()->send(new Email(['ops@paxofi.com', 'ceo@paxofi.com'], 'New enquiry from Zoë', "Hello,\n.\nA line with a dot.", 'zoe@example.com'));
        proc_close($process);
        $log = (string) file_get_contents($transcript);

        self::assertStringContainsString('C: AUTH LOGIN', $log);
        self::assertStringContainsString('C: ' . base64_encode('no-reply@paxofi.com'), $log);
        self::assertStringContainsString('C: MAIL FROM:<no-reply@paxofi.com>', $log);
        self::assertStringContainsString('C: RCPT TO:<ops@paxofi.com>', $log);
        self::assertStringContainsString('C: RCPT TO:<ceo@paxofi.com>', $log);
        self::assertStringContainsString('Subject: =?UTF-8?B?' . base64_encode('New enquiry from Zoë') . '?=', $log);
        self::assertStringContainsString('Reply-To: zoe@example.com', $log);
        self::assertStringContainsString('From: Paxofi Technologies <no-reply@paxofi.com>', $log);
        [, $body] = explode("\r\n\r\n", substr($log, strpos($log, 'DATA:') + 6, strpos($log, 'C: QUIT') - strpos($log, 'DATA:') - 6), 2);
        self::assertSame("Hello,\n.\nA line with a dot.", base64_decode(str_replace("\r\n", '', $body)), 'the body arrives intact');
        self::assertStringContainsString('C: QUIT', $log);
    }

    public function testARefusalBecomesAFailureWithoutThePassword(): void
    {
        [, $process] = $this->server('reject-rcpt');
        try {
            $this->transport()->send(new Email(['ops@paxofi.com'], 'Hi', 'Body'));
            self::fail('Expected MailFailed');
        } catch (MailFailed $exception) {
            self::assertStringContainsString('550', $exception->getMessage());
            self::assertStringNotContainsString('s3cret', $exception->getMessage());
        } finally {
            proc_terminate($process);
        }
    }

    public function testAnUnreachableServerFailsQuickly(): void
    {
        $this->expectException(MailFailed::class);
        (new SmtpTransport('127.0.0.1', 1, 'none', null, null, 'no-reply@paxofi.com', 'Paxofi', 2))->send(new Email(['ops@paxofi.com'], 'Hi', 'Body'));
    }

    private int $port = 0;

    private function transport(): SmtpTransport
    {
        return new SmtpTransport('127.0.0.1', $this->port, 'none', 'no-reply@paxofi.com', 's3cret', 'no-reply@paxofi.com', 'Paxofi Technologies', 5);
    }

    /** @return array{string, resource} */
    private function server(string $mode = ''): array
    {
        $this->port = random_int(20000, 40000);
        $transcript = tempnam(sys_get_temp_dir(), 'smtp');
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/Support/fake-smtp-server.php', (string) $this->port, $transcript, $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        self::assertSame("ready\n", fgets($pipes[1]));

        return [$transcript, $process];
    }
}
