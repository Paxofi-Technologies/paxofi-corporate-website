<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use Paxofi\CorporateWebsite\Tests\Support\MemoryMailTransport;

/** careers.paxofi.com and the staff Recruitment section (decisions D-018, D-019), against a real MariaDB. */
final class CareersTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const CAREERS = 'https://careers.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const PASSWORD = 'Temporary pass phrase 7';
    private const PDF = "%PDF-1.7\n1 0 obj << >> endobj\n%%EOF\n";

    private static string $storage;
    private MemoryMailTransport $mail;

    protected function setUp(): void
    {
        foreach (['job_applications', 'application_uploads', 'email_attachments', 'email_outbox', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
        self::$pdo->exec("DELETE FROM career_opportunities WHERE id NOT LIKE '7e1f0c00-%'");
        self::$pdo->exec("UPDATE career_opportunities SET lifecycle_state = 'published' WHERE id LIKE '7e1f0c00-%'");
        $this->mail = new MemoryMailTransport();
        self::$storage = sys_get_temp_dir() . '/paxofi-careers-test-' . bin2hex(random_bytes(4));
        mkdir(self::$storage);
    }

    protected function tearDown(): void
    {
        foreach ([self::$storage . '/applications', self::$storage] as $folder) {
            foreach (glob($folder . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            @rmdir($folder);
        }
    }

    public function testTheSevenFellowshipRolesArePublishedInOrder(): void
    {
        $roles = self::decode($this->app()->handle(self::request('GET', '/api/v1/careers/roles')))['data'];

        self::assertSame(['PM', 'PC', 'GD', 'SE', 'FD', 'HR', 'UX'], array_column($roles, 'code'));
        self::assertSame('project-manager-fellow', $roles[0]['slug']);
        self::assertNotSame([], $roles[0]['responsibilities']);
        self::assertArrayNotHasKey('id', $roles[0], 'internal ids stay private');

        $one = $this->app()->handle(self::request('GET', '/api/v1/careers/roles/software-engineer-fellow'));
        self::assertSame(200, $one->status());
        self::assertSame('Software Engineer Fellow', self::decode($one)['data']['title']);
        self::assertSame(404, $this->app()->handle(self::request('GET', '/api/v1/careers/roles/no-such-role'))->status());
    }

    public function testAnApplicationWithACvIsStoredAcknowledgedAndAlerted(): void
    {
        $app = $this->app();
        $upload = $app->handle($this->cvRequest(self::PDF, 'My CV (2026).pdf'));
        self::assertSame(201, $upload->status(), $upload->body());
        $cv = self::decode($upload)['data'];
        self::assertSame(['filename' => 'My CV 2026.pdf', 'size_bytes' => strlen(self::PDF), 'format' => 'PDF'], array_diff_key($cv, ['token' => 1]));

        $response = $app->handle($this->apply('software-engineer-fellow', ['cv_token' => $cv['token']]));
        self::assertSame(201, $response->status(), $response->body());
        $receipt = self::decode($response)['data'];
        self::assertMatchesRegularExpression('/^PIF-[A-HJ-KM-NP-Z2-9]{6}$/', $receipt['reference']);
        self::assertSame('Software Engineer Fellow', $receipt['role']);

        $row = self::$pdo->query('SELECT * FROM job_applications')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('ada@example.com', $row['email']);
        self::assertSame('applied', $row['stage']);
        self::assertSame('My CV 2026.pdf', $row['cv_filename']);
        self::assertSame(self::PDF, file_get_contents(self::$storage . '/applications/' . $row['cv_reference']), 'kept in the private applications folder');
        self::assertFileExists(self::$storage . '/applications/.htaccess');
        self::assertNotNull(self::scalar('SELECT claimed_at FROM application_uploads'));

        $app->afterResponse();
        self::assertCount(2, $this->mail->sent);
        [$acknowledgement, $alert] = $this->mail->sent[0]->to === ['ada@example.com'] ? $this->mail->sent : array_reverse($this->mail->sent);
        self::assertSame(['ada@example.com'], $acknowledgement->to);
        self::assertSame('hr@paxofi.com', $acknowledgement->replyTo);
        self::assertStringContainsString($receipt['reference'], $acknowledgement->subject . $acknowledgement->text);
        self::assertStringNotContainsString('[', $acknowledgement->text, 'every placeholder is filled');
        self::assertSame(['recruitment@paxofi.com'], $alert->to);
        self::assertStringContainsString('https://corporate.paxofi.com/admin/recruitment/' . $row['id'], $alert->text);
        self::assertStringNotContainsString('ada@example.com', $alert->text, 'the alert carries no contact details');

        $again = $app->handle($this->apply('software-engineer-fellow', ['cv_token' => $cv['token']], ip: '198.51.100.20'));
        self::assertSame(422, $again->status(), 'one open application per role');
        $other = $app->handle($this->apply('program-coordinator-fellow', ['cv_token' => $cv['token']], ip: '198.51.100.21'));
        self::assertSame(422, $other->status(), 'a CV upload is claimed once');
        self::assertSame('cv', array_key_first(self::decode($other)['error']['details']['fields']));
    }

    public function testCvsMustBePdfOrWordAndUpToFiveMegabytes(): void
    {
        $app = $this->app();
        foreach (['image' => ["\x89PNG\r\n\x1a\n" . str_repeat("\0", 40), 'cv.pdf'], 'script' => ['<?php echo 1;', 'cv.pdf'], 'text' => ['My CV as text', 'cv.txt']] as $case => [$bytes, $name]) {
            $response = $app->handle($this->cvRequest($bytes, $name));
            self::assertSame(422, $response->status(), $case);
        }
        self::assertSame(422, $app->handle($this->cvRequest('%PDF-' . str_repeat('x', 5 * 1024 * 1024), 'big.pdf'))->status());
        self::assertSame(422, $app->handle(self::request('POST', '/api/v1/careers/cv?filename=cv.pdf', ['origin' => self::CAREERS, 'content-type' => 'text/plain'], self::PDF))->status(), 'only the preflighted content type');
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM application_uploads'));

        self::assertSame(503, $this->app(storage: false)->handle($this->cvRequest(self::PDF, 'cv.pdf'))->status(), 'uploads off until MEDIA_STORAGE_PATH is set');
    }

    public function testApplicationsNeedEvidenceConsentAndAnOpenRole(): void
    {
        $app = $this->app();
        $missing = $app->handle($this->apply('software-engineer-fellow', ['linkedin_url' => null, 'privacy_consent' => false]));
        self::assertSame(422, $missing->status());
        self::assertEqualsCanonicalizing(['cv', 'privacy_consent'], array_keys(self::decode($missing)['error']['details']['fields']));

        self::assertSame(201, $app->handle($this->apply('software-engineer-fellow', []))->status(), 'a LinkedIn link is enough');

        self::$pdo->exec("UPDATE career_opportunities SET lifecycle_state = 'closed' WHERE slug = 'program-coordinator-fellow'");
        self::assertSame(404, $app->handle($this->apply('program-coordinator-fellow', [], email: 'grace@example.com'))->status());

        $bot = $app->handle($this->apply('software-engineer-fellow', ['website' => 'https://spam.example'], email: 'bot@example.com'));
        self::assertSame(201, $bot->status(), 'bots get the same answer');
        self::assertSame(0, (int) self::scalar("SELECT COUNT(*) FROM job_applications WHERE email = 'bot@example.com'"));
    }

    public function testHumanResourcesStaffRunThePipelineAndNothingElse(): void
    {
        $app = $this->app();
        $cv = self::decode($app->handle($this->cvRequest(self::PDF, 'cv.pdf')))['data'];
        $app->handle($this->apply('software-engineer-fellow', ['cv_token' => $cv['token']]));
        $id = (string) self::scalar('SELECT id FROM job_applications');

        $admin = $this->setupAdmin();
        $created = $this->call('POST', '/api/v1/admin/users', ['email' => 'hr@paxofi.com', 'display_name' => 'HR Officer', 'role' => 'human_resources', 'password' => self::PASSWORD], $admin);
        self::assertSame(201, $created->status(), $created->body());
        $this->call('POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::PASSWORD], $admin);
        $hr = $this->signIn('hr@paxofi.com');
        $bd = $this->signIn('bd@paxofi.com');

        $list = $this->call('GET', '/api/v1/admin/applications', cookie: $hr);
        self::assertSame(200, $list->status(), $list->body());
        self::assertSame(['applied' => 1], self::decode($list)['meta']['counts']);
        self::assertSame(403, $this->call('GET', '/api/v1/admin/applications', cookie: $bd)->status(), 'business development cannot see candidates');
        self::assertSame(403, $this->call('GET', '/api/v1/admin/enquiries', cookie: $hr)->status(), 'HR cannot see sales enquiries');
        self::assertSame(403, $this->call('GET', '/api/v1/admin/users', cookie: $hr)->status());

        self::assertSame(200, $this->call('PATCH', "/api/v1/admin/applications/{$id}", ['stage' => 'screening'], $hr)->status());
        $scores = ['capability' => 4, 'evidence' => 3, 'communication' => 4, 'reliability' => 3, 'learning' => 4, 'collaboration' => 3, 'readiness' => 3];
        $scored = self::decode($this->call('POST', "/api/v1/admin/applications/{$id}/scores", ['gate' => 'evidence', 'scores' => $scores], $hr))['data'];
        self::assertSame(24, $scored['evidence']['total']);
        self::assertTrue($scored['evidence']['passed'], 'pass mark 21 of 35');
        self::assertSame(422, $this->call('POST', "/api/v1/admin/applications/{$id}/scores", ['gate' => 'evidence', 'scores' => ['capability' => 9] + $scores], $hr)->status());
        self::assertSame(200, $this->call('POST', "/api/v1/admin/applications/{$id}/notes", ['body' => 'Strong GitHub portfolio.'], $hr)->status());

        $detail = self::decode($this->call('GET', "/api/v1/admin/applications/{$id}", cookie: $hr))['data'];
        self::assertSame('screening', $detail['stage']);
        self::assertSame(['stage', 'score', 'note'], array_reverse(array_column($detail['notes'], 'kind')));
        $template = array_values(array_filter($detail['email_templates'], static fn (array $t): bool => $t['key'] === 'assessment_invitation'))[0];
        self::assertSame(422, $this->call('POST', "/api/v1/admin/applications/{$id}/emails", ['template' => 'assessment_invitation', 'subject' => $template['subject'], 'body' => $template['body']], $hr)->status(), 'placeholders must be filled in first');
        $body = (string) preg_replace('/\[[^\]]+\]/', '10 October', $template['body']);
        self::assertSame(200, $this->call('POST', "/api/v1/admin/applications/{$id}/emails", ['template' => 'assessment_invitation', 'subject' => $template['subject'], 'body' => $body], $hr)->status());

        $download = $this->call('GET', "/api/v1/admin/applications/{$id}/cv", cookie: $hr);
        self::assertSame(200, $download->status());
        self::assertSame(self::PDF, $download->body());
        self::assertSame('no-store', $download->header('cache-control'));
        self::assertStringStartsWith('attachment; filename="CV-', (string) $download->header('content-disposition'));
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'application.cv_downloaded'"));

        $reference = (string) self::scalar('SELECT cv_reference FROM job_applications');
        self::assertSame(200, $this->call('DELETE', "/api/v1/admin/applications/{$id}", cookie: $hr)->status());
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM job_applications'));
        self::assertFileDoesNotExist(self::$storage . '/applications/' . $reference, 'erasing removes the CV');
    }

    public function testTheSelectionEmailCarriesTheAgreement(): void
    {
        $app = $this->app();
        $app->handle($this->apply('software-engineer-fellow', []));
        $id = (string) self::scalar('SELECT id FROM job_applications');
        $admin = $this->setupAdmin();
        $app->afterResponse();
        $this->mail->sent = [];
        $email = ['template' => 'selection', 'subject' => 'You have been selected', 'body' => "Hello Ada,\n\nAttached is the PIF Participant Agreement. Please return it signed by 20 October."];

        $missing = $this->call('POST', "/api/v1/admin/applications/{$id}/emails", $email, $admin);
        self::assertSame(422, $missing->status());
        self::assertStringContainsString('Attach the PIF Participant Agreement', self::decode($missing)['error']['details']['fields']['attachment']);
        $wrong = $this->call('POST', "/api/v1/admin/applications/{$id}/emails", $email + ['attachment' => ['filename' => 'agreement.pdf', 'content_base64' => base64_encode('<?php echo 1;')]], $admin);
        self::assertSame(422, $wrong->status(), 'checked by content, not by name');

        $agreement = self::PDF . str_repeat('%', 5000);
        $sent = $this->call('POST', "/api/v1/admin/applications/{$id}/emails", $email + ['attachment' => ['filename' => 'PIF Participant Agreement (Ada).pdf', 'content_base64' => base64_encode($agreement)]], $admin);
        self::assertSame(200, $sent->status(), $sent->body());
        self::assertSame(1, (int) self::scalar('SELECT COUNT(*) FROM email_attachments'));
        $this->app()->outboxSender()->run();
        self::assertCount(1, $this->mail->sent);
        self::assertSame('PIF Participant Agreement Ada.pdf', $this->mail->sent[0]->attachments[0]->filename);
        self::assertSame($agreement, $this->mail->sent[0]->attachments[0]->content, 'the bytes survive the database round trip');
        self::assertStringContainsString('Attached: PIF Participant Agreement Ada.pdf', self::decode($sent)['data']['notes'][0]['body']);

        $message = \Paxofi\CorporateWebsite\Infrastructure\Mail\MessageFormatter::format($this->mail->sent[0], 'no-reply@paxofi.com', 'Paxofi');
        self::assertMatchesRegularExpression('/^Content-Type: multipart\/mixed; boundary="(=_paxofi_[0-9a-f]+)"/m', $message);
        self::assertStringContainsString('Content-Disposition: attachment; filename="PIF Participant Agreement Ada.pdf"', $message);
        self::assertStringContainsString(chunk_split(base64_encode($agreement), 76, "\r\n"), $message . "\r\n");

        self::$pdo->exec('DELETE FROM email_outbox');
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM email_attachments'), 'attachments go with their email');
    }

    public function testTheReportShowsChannelsCampaignsAndReviewTimes(): void
    {
        $app = $this->app();
        $app->handle($this->apply('software-engineer-fellow', ['source' => 'linkedin', 'utm_source' => 'LinkedIn', 'utm_medium' => 'social', 'utm_campaign' => 'pif-2026']));
        $app->handle($this->apply('hr-officer-fellow', ['source' => 'made-up', 'utm_campaign' => '<script>'], email: 'grace@example.com', ip: '198.51.100.30'));
        $first = (string) self::scalar("SELECT id FROM job_applications WHERE email = 'ada@example.com'");
        self::assertSame(['linkedin', 'linkedin', 'social', 'pif-2026'], array_values(self::$pdo->query("SELECT source, utm_source, utm_medium, utm_campaign FROM job_applications WHERE id = '{$first}'")->fetch(\PDO::FETCH_NUM)));
        self::assertSame([null, null], array_values(self::$pdo->query("SELECT source, utm_campaign FROM job_applications WHERE email = 'grace@example.com'")->fetch(\PDO::FETCH_NUM)), 'unknown values are dropped, not stored');

        $admin = $this->setupAdmin();
        self::assertSame(200, $this->call('PATCH', "/api/v1/admin/applications/{$first}", ['stage' => 'screening'], $admin)->status());
        $reviewedAt = self::scalar("SELECT first_reviewed_at FROM job_applications WHERE id = '{$first}'");
        self::assertNotNull($reviewedAt);
        $this->call('PATCH', "/api/v1/admin/applications/{$first}", ['stage' => 'shortlisted'], $admin);
        self::assertSame($reviewedAt, self::scalar("SELECT first_reviewed_at FROM job_applications WHERE id = '{$first}'"), 'only the first review counts');

        $detail = self::decode($this->call('GET', "/api/v1/admin/applications/{$first}", cookie: $admin))['data'];
        self::assertSame('LinkedIn', $detail['source']['label']);
        self::assertSame('pif-2026', $detail['campaign']['campaign']);

        $report = $this->call('GET', '/api/v1/admin/applications/report?days=30', cookie: $admin);
        self::assertSame(200, $report->status(), $report->body());
        $data = self::decode($report)['data'];
        self::assertSame(2, $data['total']);
        self::assertEqualsCanonicalizing(['LinkedIn', 'Not given'], array_column($data['by_source'], 'label'));
        self::assertEqualsCanonicalizing(['linkedin / social / pif-2026', 'No campaign link'], array_column($data['by_campaign'], 'label'));
        self::assertEqualsCanonicalizing(['Shortlisted', 'Applied'], array_column($data['by_stage'], 'label'));
        self::assertSame(['reviewed' => 1, 'on_time' => 1, 'waiting' => 1, 'overdue' => 0], array_intersect_key($data['review'], array_flip(['reviewed', 'on_time', 'waiting', 'overdue'])));
        self::assertStringNotContainsString('ada@example.com', $report->body(), 'counts only, no personal data');
    }

    public function testWorkingDaysSkipWeekends(): void
    {
        $friday = new \DateTimeImmutable('2026-10-02 15:00:00', new \DateTimeZone('UTC'));
        self::assertSame('2026-10-06', \Paxofi\CorporateWebsite\Application\Careers\RecruitmentService::addWorkingDays($friday, 2)->format('Y-m-d'), 'Friday + 2 working days = Tuesday');
    }

    public function testRolesAreDraftedPublishedAndClosedInTheStaffArea(): void
    {
        $admin = $this->setupAdmin();
        $role = ['title' => 'Data Analyst Fellow', 'code' => 'DA', 'family' => 'Data', 'summary' => 'Turn data into decisions for Paxofi products.', 'purpose' => 'Builds dashboards and analysis for product and growth teams.', 'responsibilities' => "Build dashboards\nAnalyse funnels", 'sort_order' => 80];
        $created = $this->call('POST', '/api/v1/admin/career-roles', $role, $admin);
        self::assertSame(201, $created->status(), $created->body());
        $data = self::decode($created)['data'];
        self::assertSame(['draft', 'data-analyst-fellow', ['Build dashboards', 'Analyse funnels']], [$data['state'], $data['slug'], $data['responsibilities']]);
        self::assertSame(404, $this->app()->handle(self::request('GET', '/api/v1/careers/roles/data-analyst-fellow'))->status(), 'drafts are hidden');

        self::assertSame(200, $this->call('POST', "/api/v1/admin/career-roles/{$data['id']}/state", ['state' => 'published'], $admin)->status());
        $codes = array_column(self::decode($this->app()->handle(self::request('GET', '/api/v1/careers/roles')))['data'], 'code');
        self::assertSame('DA', end($codes));

        $renamed = self::decode($this->call('PATCH', "/api/v1/admin/career-roles/{$data['id']}", ['title' => 'Data & Insights Fellow'] + $role, $admin))['data'];
        self::assertSame('data-analyst-fellow', $renamed['slug'], 'the address never changes');
        self::assertSame(200, $this->call('POST', "/api/v1/admin/career-roles/{$data['id']}/state", ['state' => 'closed'], $admin)->status());
        self::assertSame(404, $this->app()->handle(self::request('GET', '/api/v1/careers/roles/data-analyst-fellow'))->status());
        self::assertSame(3, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'career_role.%' AND action <> 'career_role.updated'"));
    }

    private function cvRequest(string $bytes, string $filename): \Paxofi\Core\Contracts\HttpRequest
    {
        return self::request('POST', '/api/v1/careers/cv?' . http_build_query(['filename' => $filename]), ['origin' => self::CAREERS, 'content-type' => 'application/octet-stream', 'content-length' => (string) strlen($bytes)], $bytes, ['filename' => $filename]);
    }

    /** @param array<string, mixed> $overrides */
    private function apply(string $slug, array $overrides, string $email = 'ada@example.com', string $ip = '203.0.113.10'): \Paxofi\Core\Contracts\HttpRequest
    {
        $payload = array_filter($overrides + [
            'full_name' => 'Ada Lovelace',
            'email' => $email,
            'location' => 'Lagos, Nigeria',
            'hours_per_week' => 20,
            'motivation' => str_repeat('I want to build real products with a delivery-focused team. ', 2),
            'experience' => str_repeat('I built a payments dashboard in React and a PHP API for it. ', 2),
            'linkedin_url' => 'https://www.linkedin.com/in/ada-lovelace',
            'age_confirmed' => true,
            'privacy_consent' => true,
        ], static fn (mixed $value): bool => $value !== null);

        return self::request('POST', "/api/v1/careers/roles/{$slug}/apply", ['origin' => self::CAREERS, 'content-type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR), [], $ip);
    }

    private function setupAdmin(): string
    {
        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN, 'email' => 'founder@paxofi.com', 'display_name' => 'Samuel Adeniji', 'password' => 'Correct horse battery 42']);
        self::assertSame(201, $response->status(), $response->body());

        return $this->token($response);
    }

    private function signIn(string $email): string
    {
        return $this->token($this->call('POST', '/api/v1/admin/session', ['email' => $email, 'password' => self::PASSWORD]));
    }

    private function token(HttpResponse $response): string
    {
        self::assertSame(1, preg_match('/^paxofi_admin=([^;]+);/', (string) $response->header('set-cookie'), $match), $response->body());

        return $match[1];
    }

    /** @param array<string, mixed>|null $payload */
    private function call(string $method, string $uri, ?array $payload = null, ?string $cookie = null): HttpResponse
    {
        $headers = ['origin' => self::ORIGIN, 'content-type' => 'application/json'];
        if ($cookie !== null) {
            $headers['cookie'] = 'paxofi_admin=' . $cookie;
        }
        $query = [];
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return $this->app()->handle(self::request($method, $uri, $headers, $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR), $query));
    }

    private function app(bool $storage = true): ApiApplication
    {
        $environment = Environment::from([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN . ',' . self::CAREERS,
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
            'RECRUITMENT_ALERT_TO' => 'recruitment@paxofi.com',
        ] + ($storage ? ['MEDIA_STORAGE_PATH' => self::$storage] : []));
        $cheap = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : ['cost' => 4];

        return new ApiApplication(
            Settings::fromEnvironment($environment),
            new NullLogger(),
            fn () => Connection::make(self::$environment),
            fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            new NativeStaffPasswordHasher($cheap),
            mailTransport: $this->mail,
        );
    }
}
