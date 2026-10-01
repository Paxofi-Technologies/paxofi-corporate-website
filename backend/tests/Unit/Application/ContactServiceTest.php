<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application;

use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Application\Contact\ContactService;
use Paxofi\CorporateWebsite\Application\Contact\EnquiryValidator;
use Paxofi\CorporateWebsite\Application\Exception\RateLimited;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Paxofi\CorporateWebsite\Tests\Support\ImmediateTransactionManager;
use Paxofi\CorporateWebsite\Tests\Support\InMemoryEnquiryRepository;
use Paxofi\CorporateWebsite\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ContactServiceTest extends TestCase
{
    private const INPUT = ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello'];

    private InMemoryEnquiryRepository $enquiries;
    private RecordingAuditRecorder $audit;
    private ImmediateTransactionManager $transactions;
    private ContactService $service;

    protected function setUp(): void
    {
        $this->enquiries = new InMemoryEnquiryRepository();
        $this->audit = new RecordingAuditRecorder();
        $this->transactions = new ImmediateTransactionManager();
        $this->service = new ContactService(new EnquiryValidator(), $this->enquiries, $this->audit, $this->transactions, new NullLogger(), rateLimitMax: 2, rateLimitWindowMinutes: 10);
    }

    public function testPersistsEnquiryAndAuditEventInOneTransaction(): void
    {
        $receipt = $this->service->submit('Contact', self::INPUT, new RequestContext('req-1', '203.0.113.5', 'UA'));

        self::assertTrue($receipt->persisted);
        self::assertSame('contact', $receipt->formKey);
        self::assertCount(1, $this->enquiries->stored);
        self::assertSame(1, $this->transactions->transactions);
        self::assertCount(1, $this->audit->events);
        self::assertSame('enquiry.submitted', $this->audit->events[0]->action);
        self::assertSame('enquiry-1', $this->audit->events[0]->targetId);
        self::assertSame('req-1', $this->audit->events[0]->requestId);
    }

    public function testUnknownFormIsNotFound(): void
    {
        $this->expectException(ResourceNotFound::class);
        $this->service->submit('newsletter', self::INPUT, new RequestContext('req'));
    }

    public function testInvalidInputIsRejectedBeforeAnyPersistence(): void
    {
        try {
            $this->service->submit('contact', ['email' => 'x'] + self::INPUT, new RequestContext('req'));
            self::fail('Expected ValidationFailed');
        } catch (ValidationFailed) {
            self::assertSame([], $this->enquiries->stored);
            self::assertSame([], $this->audit->events);
        }
    }

    public function testRateLimitIsEnforcedWithoutDatabaseWrites(): void
    {
        $this->enquiries->recentCount = 2;

        try {
            $this->service->submit('contact', self::INPUT, new RequestContext('req-9'));
            self::fail('Expected RateLimited');
        } catch (RateLimited $exception) {
            self::assertSame(600, $exception->retryAfterSeconds());
        }

        self::assertSame([], $this->enquiries->stored);
        self::assertSame([], $this->audit->events, 'blocked requests must not write audit rows');
        self::assertSame(0, $this->transactions->transactions);
    }

    public function testHoneypotSubmissionIsAcknowledgedButDiscarded(): void
    {
        $receipt = $this->service->submit('contact', ['website' => 'x'] + self::INPUT, new RequestContext('req'));

        self::assertFalse($receipt->persisted);
        self::assertSame([], $this->enquiries->stored);
        self::assertSame(0, $this->transactions->transactions);
    }
}
