<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use Paxofi\CorporateWebsite\Application\Careers\CertificateService;

/** Participation certificates and the public check (decision D-029), against a real MariaDB. */
final class CertificatesTest extends RecruitmentTestCase
{
    public function testACertificateIsIssuedDownloadedAndCheckedPublicly(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $id = $this->application('completed');
        $payload = ['started_on' => self::day('-20 days'), 'completed_on' => self::day('-1 day')];

        $detail = $this->data($this->call('GET', "/api/v1/admin/applications/{$id}", cookie: $hr));
        self::assertTrue($detail['certificate_eligible']);
        self::assertSame([], $detail['certificates']);

        $issued = $this->call('POST', "/api/v1/admin/applications/{$id}/certificates", $payload, $hr);
        self::assertSame(201, $issued->status(), $issued->body());
        $certificate = $this->data($issued)['certificates'][0];
        self::assertMatchesRegularExpression('/^PIF-[A-HJ-KM-NP-Z2-9]{4}-[A-HJ-KM-NP-Z2-9]{4}-[A-HJ-KM-NP-Z2-9]{4}$/', $certificate['code']);
        self::assertSame('valid', $certificate['status']);
        self::assertSame('https://careers.paxofi.com/verify?code=' . $certificate['code'], $certificate['verify_url']);
        self::assertSame(409, $this->call('POST', "/api/v1/admin/applications/{$id}/certificates", $payload, $hr)->status(), 'one valid certificate at a time');

        $pdf = $this->call('GET', "/api/v1/admin/certificates/{$certificate['id']}/pdf", cookie: $hr);
        self::assertSame(200, $pdf->status());
        self::assertSame('application/pdf', $pdf->header('content-type'));
        self::assertStringContainsString('attachment', (string) $pdf->header('content-disposition'));
        self::assertStringStartsWith('%PDF-1.4', $pdf->body());
        self::assertStringContainsString('(Ada Lovelace)', $pdf->body());
        self::assertStringContainsString($certificate['code'], $pdf->body());

        // Public check: no sign-in, any letter case.
        $public = $this->app()->handle(self::request('GET', '/api/v1/careers/certificates/' . strtolower($certificate['code'])));
        self::assertSame(200, $public->status(), $public->body());
        $shown = self::decode($public)['data'];
        $roleTitle = (string) self::scalar('SELECT o.title FROM job_applications a JOIN career_opportunities o ON o.id = a.opportunity_id WHERE a.id = ?', [$id]);
        self::assertSame([true, 'Ada Lovelace', $roleTitle, 'fellow', 'Cohort 1', $payload['completed_on']], [$shown['valid'], $shown['holder_name'], $shown['role_title'], $shown['engagement']['type'], $shown['cohort'], $shown['completed_on']]);
        self::assertSame('no-store', $public->header('cache-control'));
        self::assertStringNotContainsString('ada@example.com', $public->body(), 'only what is printed on the certificate');
        self::assertStringNotContainsString($id, $public->body());
    }

    public function testOnlyCompletedParticipantsGetCertificatesWithSensibleDates(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $active = $this->application('active');
        $completed = $this->application('alumni', 'grace@example.com', 'Grace Hopper');

        self::assertSame(409, $this->call('POST', "/api/v1/admin/applications/{$active}/certificates", ['completed_on' => self::day('-1 day')], $hr)->status(), 'still taking part');
        foreach ([[], ['completed_on' => '2999-01-01'], ['completed_on' => 'last week'], ['completed_on' => self::day('-60 days')], ['completed_on' => self::day('-1 day'), 'started_on' => self::day('-1 day + 2 days')], ['completed_on' => self::day('-1 day'), 'started_on' => 'soon']] as $payload) {
            $response = $this->call('POST', "/api/v1/admin/applications/{$completed}/certificates", $payload, $hr);
            self::assertSame(422, $response->status(), json_encode($payload) . ' ' . $response->body());
        }
        self::assertSame(201, $this->call('POST', "/api/v1/admin/applications/{$completed}/certificates", ['completed_on' => self::day('-1 day')], $hr)->status(), 'the start day is optional');
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM certificates WHERE application_id = ?', [$active]));
    }

    public function testRevokedAndUnknownCodesAreHandledWithoutLeakingDetails(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $id = $this->application('completed');
        $certificate = $this->data($this->call('POST', "/api/v1/admin/applications/{$id}/certificates", ['completed_on' => self::day('-1 day')], $hr))['certificates'][0];

        self::assertSame(403, $this->call('POST', "/api/v1/admin/certificates/{$certificate['id']}/revoke", ['reason' => 'Issued in error, see HR file.'], $hr)->status(), 'only administrators revoke');
        self::assertSame(422, $this->call('POST', "/api/v1/admin/certificates/{$certificate['id']}/revoke", ['reason' => 'no'], $admin)->status());
        $revoked = $this->call('POST', "/api/v1/admin/certificates/{$certificate['id']}/revoke", ['reason' => 'Issued in error, see HR file.'], $admin);
        self::assertSame(200, $revoked->status(), $revoked->body());
        self::assertSame(409, $this->call('POST', "/api/v1/admin/certificates/{$certificate['id']}/revoke", ['reason' => 'Issued in error, see HR file.'], $admin)->status());

        $public = self::decode($this->app()->handle(self::request('GET', '/api/v1/careers/certificates/' . $certificate['code'])));
        self::assertSame(['revoked', false], [$public['data']['status'], $public['data']['valid']]);
        self::assertArrayNotHasKey('holder_name', $public['data'], 'a revoked certificate shows no personal details');
        self::assertStringNotContainsString('Issued in error', json_encode($public, JSON_THROW_ON_ERROR), 'nor the reason');

        $unknown = $this->app()->handle(self::request('GET', '/api/v1/careers/certificates/PIF-AAAA-BBBB-CCCC'));
        $malformed = $this->app()->handle(self::request('GET', '/api/v1/careers/certificates/not-a-code'));
        self::assertSame([404, 404], [$unknown->status(), $malformed->status()]);
        self::assertSame(self::decode($unknown)['error']['message'], self::decode($malformed)['error']['message'], 'the same answer for both');

        self::assertSame(201, $this->call('POST', "/api/v1/admin/applications/{$id}/certificates", ['completed_on' => self::day('-1 day')], $hr)->status(), 'a replacement can be issued after a revocation');

        $new = (string) self::scalar("SELECT id FROM certificates WHERE status = 'valid'");
        self::assertSame(403, $this->call('DELETE', "/api/v1/admin/certificates/{$new}", cookie: $hr)->status());
        self::assertSame(200, $this->call('DELETE', "/api/v1/admin/certificates/{$new}", cookie: $admin)->status());
        self::assertSame(1, (int) self::scalar('SELECT COUNT(*) FROM certificates'));
        self::assertSame(4, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'certificate.%'"), 'issued twice, revoked once, deleted once');
    }

    public function testCodesAreNormalisedAndTheCertificateOutlivesItsApplication(): void
    {
        self::assertSame('PIF-K7M2-9QXA-R4TD', CertificateService::normalise(' pif k7m2-9qxa r4td '));
        self::assertSame('PIF-K7M2-9QXA-R4TD', CertificateService::normalise('PIFK7M29QXAR4TD'));
        foreach (['', 'PIF-K7M2', 'PIF-K7M2-9QXA-R4T0', 'XYZ-K7M2-9QXA-R4TD', "PIF-K7M2-9QXA-R4TD'; DROP TABLE certificates"] as $bad) {
            self::assertNull(CertificateService::normalise($bad), $bad);
        }

        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $id = $this->application('completed');
        $certificate = $this->data($this->call('POST', "/api/v1/admin/applications/{$id}/certificates", ['completed_on' => self::day('-1 day')], $hr))['certificates'][0];
        self::$pdo->exec("DELETE FROM job_applications WHERE id = '{$id}'");

        $public = $this->app()->handle(self::request('GET', '/api/v1/careers/certificates/' . $certificate['code']));
        self::assertSame(200, $public->status(), 'still verifiable after the application is deleted');
        self::assertSame('Ada Lovelace', self::decode($public)['data']['holder_name']);
    }
}
