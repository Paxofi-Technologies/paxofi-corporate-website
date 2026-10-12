<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Mail;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Admin\Role;
use Paxofi\CorporateWebsite\Application\Admin\StaffUser;
use Paxofi\CorporateWebsite\Application\Mail\MailDiagnostics;
use Paxofi\CorporateWebsite\Application\Mail\MailSettingsStore;
use Paxofi\CorporateWebsite\Application\Mail\OutboxStats;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Paxofi\CorporateWebsite\Infrastructure\Security\OpenSslSecretEncryption;
use Paxofi\CorporateWebsite\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

/** The mailbox password saved in the staff area is encrypted, readable back with the key, and never shown (D-031). */
final class MailDiagnosticsUnitTest extends TestCase
{
    private const KEY = 'an-encryption-key-of-at-least-thirty-two-characters';

    public function testThePasswordIsStoredEncryptedAndNeverReturned(): void
    {
        $store = new class () implements MailSettingsStore {
            /** @var array<string, string> */
            public array $values = [];

            public function all(): array
            {
                return $this->values;
            }

            public function set(string $key, ?string $value, ?string $userId): void
            {
                if ($value === null) {
                    unset($this->values[$key]);
                } else {
                    $this->values[$key] = $value;
                }
            }
        };
        $stats = new class () implements OutboxStats {
            public function summary(DateTimeImmutable $now): array
            {
                return ['pending' => 0, 'failed' => 0, 'sent_last_day' => 0, 'oldest_pending_at' => null, 'last_error' => null, 'last_error_kind' => null, 'last_sent_at' => null];
            }
        };
        $secrets = new OpenSslSecretEncryption(self::KEY, 'mail-password/v1');
        $state = static fn (): array => ['transport' => null, 'values' => ['password' => $store->values['password'] ?? ''], 'sources' => ['password' => isset($store->values['password']) ? 'staff' : 'none'], 'problem' => null];
        $diagnostics = new MailDiagnostics($state, static function (): void {
        }, $store, $secrets, $stats, static fn (): array => ['sent' => 0, 'failed' => 0], new RecordingAuditRecorder(), static fn (): DateTimeImmutable => new DateTimeImmutable('2026-10-12 10:00:00'));
        $staff = new AuthenticatedStaff(new StaffUser('u1', 'founder@paxofi.com', 'Founder', StaffUser::STATUS_ACTIVE, Role::Administrator, ['users.manage']), 'session');

        $result = $diagnostics->save(['password' => 'Mailbox pass 99'], $staff, new RequestContext('r1'));

        self::assertStringStartsWith('v1.', $store->values['password']);
        self::assertStringNotContainsString('Mailbox pass 99', $store->values['password']);
        self::assertSame('Mailbox pass 99', $secrets->decrypt($store->values['password']), 'the website can read it back with the key');
        self::assertNull((new OpenSslSecretEncryption(self::KEY))->decrypt($store->values['password']), 'a key for two-factor secrets cannot read it');
        self::assertStringNotContainsString('Mailbox pass 99', (string) json_encode($result));
        self::assertNull($result['fields']['password']['value']);

        $diagnostics->save(['clear_password' => true], $staff, new RequestContext('r2'));
        self::assertArrayNotHasKey('password', $store->values);
    }
}
