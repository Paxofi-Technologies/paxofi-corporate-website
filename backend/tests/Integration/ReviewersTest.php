<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

/** Reviewers and recusal (decision D-029), against a real MariaDB. */
final class ReviewersTest extends RecruitmentTestCase
{
    private const SCORES = ['capability' => 4, 'evidence' => 3, 'communication' => 4, 'reliability' => 3, 'learning' => 4, 'collaboration' => 3, 'readiness' => 3];

    public function testAdministratorsAssignReviewersAndOnlyTheyCanScore(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $second = $this->staff($admin, 'hr2@paxofi.com', 'Second Officer');
        $id = $this->application('screening');

        $without = $this->data($this->call('GET', "/api/v1/admin/applications/{$id}", cookie: $hr));
        self::assertSame([], $without['reviewers']);
        self::assertContains('HR Officer', array_column($without['reviewer_candidates'], 'name'));

        $assigned = $this->call('POST', "/api/v1/admin/applications/{$id}/reviewers", ['user_id' => $this->userId('hr@paxofi.com')], $admin);
        self::assertSame(201, $assigned->status(), $assigned->body());
        self::assertSame([['HR Officer', 'assigned']], array_map(static fn (array $r): array => [$r['name'], $r['status']], $this->data($assigned)['reviewers']));
        self::assertSame(409, $this->call('POST', "/api/v1/admin/applications/{$id}/reviewers", ['user_id' => $this->userId('hr@paxofi.com')], $admin)->status(), 'already a reviewer');
        self::assertSame(422, $this->call('POST', "/api/v1/admin/applications/{$id}/reviewers", ['user_id' => 'not-a-user'], $admin)->status());

        self::assertSame(200, $this->call('POST', "/api/v1/admin/applications/{$id}/scores", ['gate' => 'evidence', 'scores' => self::SCORES], $hr)->status(), 'an assigned reviewer scores');
        $denied = $this->call('POST', "/api/v1/admin/applications/{$id}/scores", ['gate' => 'evidence', 'scores' => self::SCORES], $second);
        self::assertSame(403, $denied->status(), 'someone who is not assigned cannot score once reviewers exist');
        self::assertSame(200, $this->call('GET', "/api/v1/admin/applications/{$id}", cookie: $second)->status(), 'but can still read it');

        $removed = $this->call('DELETE', "/api/v1/admin/applications/{$id}/reviewers/" . $this->userId('hr@paxofi.com'), cookie: $admin);
        self::assertSame(200, $removed->status(), $removed->body());
        self::assertSame([], $this->data($removed)['reviewers']);
        self::assertSame(200, $this->call('POST', "/api/v1/admin/applications/{$id}/scores", ['gate' => 'evidence', 'scores' => self::SCORES], $second)->status(), 'with no reviewers assigned anyone who manages recruitment scores, as before');
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'application.reviewer_assigned'"));
    }

    public function testRecusalHidesTheApplicationAndClearsTheirScores(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $other = $this->staff($admin, 'hr2@paxofi.com', 'Second Officer');
        $id = $this->application('screening');
        $other_id = $this->application('screening', 'grace@example.com', 'Grace Hopper');

        self::assertSame(200, $this->call('POST', "/api/v1/admin/applications/{$id}/scores", ['gate' => 'evidence', 'scores' => self::SCORES], $hr)->status());
        self::assertNotNull(self::scalar('SELECT evidence_scores FROM job_applications WHERE id = ?', [$id]));

        self::assertSame(422, $this->call('POST', "/api/v1/admin/applications/{$id}/recusal", ['reason' => 'short'], $hr)->status(), 'a real reason is needed');
        $recused = $this->call('POST', "/api/v1/admin/applications/{$id}/recusal", ['reason' => 'The applicant is a close relative of mine.'], $hr);
        self::assertSame(200, $recused->status(), $recused->body());
        self::assertSame(['recused' => true], $this->data($recused));

        self::assertNull(self::scalar('SELECT evidence_scores FROM job_applications WHERE id = ?', [$id]), 'the scorecard of someone with a conflict is cleared');
        foreach ([['GET', ''], ['GET', '/cv'], ['PATCH', ''], ['POST', '/notes'], ['POST', '/scores'], ['POST', '/recusal']] as [$method, $suffix]) {
            $response = $this->call($method, "/api/v1/admin/applications/{$id}{$suffix}", ['stage' => 'shortlisted', 'body' => 'hello there', 'gate' => 'evidence', 'scores' => self::SCORES, 'reason' => 'Another reason here'], $hr);
            self::assertSame(404, $response->status(), "{$method} {$suffix} is hidden from a recused reviewer");
        }
        $list = self::decode($this->call('GET', '/api/v1/admin/applications', cookie: $hr));
        self::assertSame([$other_id], array_column($list['data'], 'id'), 'the list leaves the application out');
        self::assertSame(['screening' => 1], $list['meta']['counts'], 'and so do the counts');

        $seenByOther = $this->data($this->call('GET', "/api/v1/admin/applications/{$id}", cookie: $other));
        self::assertSame([['HR Officer', 'recused']], array_map(static fn (array $r): array => [$r['name'], $r['status']], $seenByOther['reviewers']));
        $seenByAdmin = $this->data($this->call('GET', "/api/v1/admin/applications/{$id}", cookie: $admin));
        self::assertSame('The applicant is a close relative of mine.', $seenByAdmin['reviewers'][0]['reason'] ?? null, 'managers see why');
        self::assertSame('The applicant is a close relative of mine.', $seenByOther['reviewers'][0]['reason'] ?? null, 'HR staff manage recruitment, so they see it too');

        $again = $this->call('POST', "/api/v1/admin/applications/{$id}/reviewers", ['user_id' => $this->userId('hr@paxofi.com')], $admin);
        self::assertSame(409, $again->status(), 'a recused person cannot be assigned again');
        self::assertSame(404, $this->call('DELETE', "/api/v1/admin/applications/{$id}/reviewers/" . $this->userId('hr@paxofi.com'), cookie: $admin)->status(), 'a recusal cannot be removed');
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'application.recused'"));
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM application_notes WHERE application_id = ? AND kind = 'recusal'", [$id]));
    }

    public function testStaffCannotReviewTheirOwnApplication(): void
    {
        $admin = $this->setupAdmin();
        $hr = $this->staff($admin, 'hr@paxofi.com', 'HR Officer');
        $own = $this->application('applied', 'HR@paxofi.com', 'HR Officer');
        $someone = $this->application('applied', 'grace@example.com', 'Grace Hopper');

        self::assertSame(404, $this->call('GET', "/api/v1/admin/applications/{$own}", cookie: $hr)->status());
        self::assertSame([$someone], array_column(self::decode($this->call('GET', '/api/v1/admin/applications', cookie: $hr))['data'], 'id'));
        self::assertSame(200, $this->call('GET', "/api/v1/admin/applications/{$own}", cookie: $admin)->status(), 'other staff still see it');
        $assign = $this->call('POST', "/api/v1/admin/applications/{$own}/reviewers", ['user_id' => $this->userId('hr@paxofi.com')], $admin);
        self::assertSame(409, $assign->status(), 'and cannot assign the applicant as a reviewer');
    }

    public function testBusinessDevelopmentCannotUseTheRecruitmentRoutes(): void
    {
        $admin = $this->setupAdmin();
        $bd = $this->staff($admin, 'bd@paxofi.com', 'Business Dev', 'business_development');
        $id = $this->application();

        foreach ([['POST', "/api/v1/admin/applications/{$id}/recusal", ['reason' => 'A sufficiently long reason']], ['POST', "/api/v1/admin/applications/{$id}/reviewers", ['user_id' => $this->userId('bd@paxofi.com')]]] as [$method, $uri, $payload]) {
            self::assertSame(403, $this->call($method, $uri, $payload, $bd)->status(), $uri);
        }
        self::assertSame(401, $this->call('POST', "/api/v1/admin/applications/{$id}/recusal", ['reason' => 'A sufficiently long reason'])->status());
    }
}
