<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Mail;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailFailed;
use Paxofi\CorporateWebsite\Application\Mail\MailTransport;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Application\Mail\OutboxSender;
use PHPUnit\Framework\TestCase;

/** One email that cannot be sent never stops the others, and every failure is recorded with its reason. */
final class OutboxSenderTest extends TestCase
{
    public function testAnUnexpectedErrorIsRecordedAndTheQueueCarriesOn(): void
    {
        $outbox = new class () implements Outbox {
            /** @var list<array{0: string, 1: string, 2: ?DateTimeImmutable}> */
            public array $failed = [];
            /** @var list<string> */
            public array $sent = [];

            public function add(Email $email): string
            {
                return 'x';
            }

            public function due(DateTimeImmutable $now, int $limit): array
            {
                return [
                    ['id' => 'a', 'attempts' => 0, 'email' => new Email(['broken@example.com'], 'One', 'Body')],
                    ['id' => 'b', 'attempts' => 0, 'email' => new Email(['refused@example.com'], 'Two', 'Body')],
                    ['id' => 'c', 'attempts' => 4, 'email' => new Email(['ok@example.com'], 'Three', 'Body')],
                ];
            }

            public function markSent(string $id, DateTimeImmutable $now): void
            {
                $this->sent[] = $id;
            }

            public function markFailed(string $id, string $error, ?DateTimeImmutable $nextAttempt): void
            {
                $this->failed[] = [$id, $error, $nextAttempt];
            }

            public function failedSince(DateTimeImmutable $since): int
            {
                return 0;
            }
        };
        $transport = new class () implements MailTransport {
            public function send(Email $email): void
            {
                if ($email->to === ['broken@example.com']) {
                    throw new \RuntimeException('stream_socket_client(): unable to connect');
                }
                if ($email->to === ['refused@example.com']) {
                    throw new MailFailed('Mail server replied 535 to AUTH: bad password');
                }
            }
        };
        $now = new DateTimeImmutable('2026-10-12 08:00:00', new DateTimeZone('UTC'));

        $result = (new OutboxSender($outbox, $transport, static fn (): DateTimeImmutable => $now, new NullLogger()))->run();

        self::assertSame(['sent' => 1, 'failed' => 2], $result);
        self::assertSame(['c'], $outbox->sent, 'the email after the broken ones is still sent');
        self::assertSame('a', $outbox->failed[0][0]);
        self::assertSame('RuntimeException: stream_socket_client(): unable to connect', $outbox->failed[0][1], 'the reason shows in last_error');
        self::assertSame('2026-10-12 08:01:00', $outbox->failed[0][2]?->format('Y-m-d H:i:s'), 'retried after a minute');
        self::assertSame(['b', 'Mail server replied 535 to AUTH: bad password'], [$outbox->failed[1][0], $outbox->failed[1][1]]);
    }
}
