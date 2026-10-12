<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

/** The talent pool (decision D-029), against a real MariaDB. */
final class TalentPoolTest extends RecruitmentTestCase
{
    /** @return array<string, string> */
    private static function consent(): array
    {
        return ['consent_given_on' => self::day('-2 days'), 'consent_method' => 'email_reply'];
    }

    public function testACandidateWhoWasNotSelectedJoinsWithRecordedConsentAndLapsesAfterTwoYears(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $id = $this->application('rejected');

        $detail = $this->data($this->call('GET', "/api/v1/admin/applications/{$id}", cookie: $hr));
        self::assertTrue($detail['talent_pool_eligible']);
        self::assertNull($detail['talent_pool']);

        $added = $this->call('POST', "/api/v1/admin/applications/{$id}/talent-pool", self::consent() + ['note' => 'Keen on design roles.'], $hr);
        self::assertSame(201, $added->status(), $added->body());
        $entry = $this->data($added)['talent_pool'];
        self::assertSame((new \DateTimeImmutable(self::day('-2 days')))->modify('+24 months')->format('Y-m-d'), $entry['consent_expires_on'], 'consent lasts 24 months');

        $list = self::decode($this->call('GET', '/api/v1/admin/talent-pool', cookie: $hr));
        self::assertSame(1, $list['meta']['total']);
        $row = $list['data'][0];
        self::assertSame(['Ada Lovelace', 'ada@example.com', 'fellow', 'The candidate replied to our email', 'Keen on design roles.'], [$row['full_name'], $row['email'], $row['engagement_type'], $row['consent_method_label'], $row['note']]);
        self::assertArrayNotHasKey('cv_reference', $row, 'the CV is never copied');
        self::assertSame(1, self::decode($this->call('GET', '/api/v1/admin/talent-pool?q=lovelace', cookie: $hr))['meta']['total']);
        self::assertSame(0, self::decode($this->call('GET', '/api/v1/admin/talent-pool?q=nobody', cookie: $hr))['meta']['total']);
        self::assertSame(0, self::decode($this->call('GET', '/api/v1/admin/talent-pool?engagement=intern', cookie: $hr))['meta']['total']);

        self::assertSame(409, $this->call('POST', "/api/v1/admin/applications/{$id}/talent-pool", self::consent(), $hr)->status(), 'once only');
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'talent_pool.added'"));
    }

    public function testConsentIsRequiredAndOnlyForCandidatesWhoDidNotJoin(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $rejected = $this->application('rejected');
        $active = $this->application('active', 'grace@example.com', 'Grace Hopper');

        self::assertSame(409, $this->call('POST', "/api/v1/admin/applications/{$active}/talent-pool", self::consent(), $hr)->status(), 'a participant is not in the pool');
        foreach ([[], ['consent_given_on' => self::day('-2 days')], ['consent_given_on' => '2999-01-01', 'consent_method' => 'email_reply'], ['consent_given_on' => self::day('-60 days'), 'consent_method' => 'email_reply'], ['consent_given_on' => 'yesterday', 'consent_method' => 'written'], ['consent_given_on' => self::day('-2 days'), 'consent_method' => 'other']] as $payload) {
            $response = $this->call('POST', "/api/v1/admin/applications/{$rejected}/talent-pool", $payload, $hr);
            self::assertSame(422, $response->status(), json_encode($payload) . ' ' . $response->body());
        }
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM talent_pool'));
        self::assertSame(201, $this->call('POST', "/api/v1/admin/applications/{$rejected}/talent-pool", ['consent_given_on' => self::day('-2 days'), 'consent_method' => 'other', 'note' => 'Agreed on a call with HR.'], $hr)->status());
    }

    public function testWithdrawingConsentDeletesTheEntryAndErasingTheApplicationDoes(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $bd = $this->staff($admin, 'bd@paxofi.com', 'Business Dev', 'business_development');
        $first = $this->application('rejected');
        $second = $this->application('declined', 'grace@example.com', 'Grace Hopper');
        $this->call('POST', "/api/v1/admin/applications/{$first}/talent-pool", self::consent(), $hr);
        $this->call('POST', "/api/v1/admin/applications/{$second}/talent-pool", self::consent(), $hr);
        $entry = (string) self::scalar("SELECT id FROM talent_pool WHERE email = 'ada@example.com'");

        self::assertSame(403, $this->call('GET', '/api/v1/admin/talent-pool', cookie: $bd)->status());
        self::assertSame(404, $this->call('DELETE', '/api/v1/admin/talent-pool/00000000-0000-4000-8000-000000000000', cookie: $hr)->status());
        self::assertSame(200, $this->call('DELETE', "/api/v1/admin/talent-pool/{$entry}", cookie: $hr)->status());
        self::assertSame(['grace@example.com'], self::$pdo->query('SELECT email FROM talent_pool')->fetchAll(\PDO::FETCH_COLUMN), 'nothing of the first person is kept');
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'talent_pool.withdrawn'"));

        self::assertSame(200, $this->call('DELETE', "/api/v1/admin/applications/{$second}", cookie: $hr)->status());
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM talent_pool'), 'erasing the application erases the pool entry too');
    }

    public function testAnEntryOutlivesItsApplicationWhenRetentionDeletesIt(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $id = $this->application('rejected');
        $this->call('POST', "/api/v1/admin/applications/{$id}/talent-pool", self::consent(), $hr);

        self::$pdo->exec("DELETE FROM job_applications WHERE id = '{$id}'");

        self::assertSame(1, (int) self::scalar('SELECT COUNT(*) FROM talent_pool'));
        self::assertNull(self::scalar('SELECT application_id FROM talent_pool'), 'the link is cleared, the consented details stay');
    }
}
