<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Bootstrap;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpHandler;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Contracts\Logger;
use Paxofi\Core\Http\MiddlewarePipeline;
use Paxofi\Core\Http\Router;
use Paxofi\Core\Observability\HealthRegistry;
use Paxofi\CorporateWebsite\Application\Admin\AdminEnquiryService;
use Paxofi\CorporateWebsite\Application\Admin\AuthService;
use Paxofi\CorporateWebsite\Application\Admin\Catalog\CatalogEditor;
use Paxofi\CorporateWebsite\Application\Admin\Pages\PageCopyEditor;
use Paxofi\CorporateWebsite\Application\Admin\Pages\PageCopySchema;
use Paxofi\CorporateWebsite\Application\Admin\PasswordHashing;
use Paxofi\CorporateWebsite\Application\Admin\PasswordResetService;
use Paxofi\CorporateWebsite\Application\Mail\AlertThrottle;
use Paxofi\CorporateWebsite\Application\Mail\ErrorAlerts;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\MailTransport;
use Paxofi\CorporateWebsite\Application\Mail\OutboxSender;
use Paxofi\CorporateWebsite\Application\Admin\StaffAdminService;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\TwoFactorService;
use Paxofi\CorporateWebsite\Application\Analytics\Analytics;
use Paxofi\CorporateWebsite\Application\Careers\CareerRoleEditor;
use Paxofi\CorporateWebsite\Application\Careers\CareersService;
use Paxofi\CorporateWebsite\Application\Careers\RecruitmentService;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogService;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogType;
use Paxofi\CorporateWebsite\Application\Contact\ContactService;
use Paxofi\CorporateWebsite\Application\Contact\EnquiryValidator;
use Paxofi\CorporateWebsite\Application\Content\ContentService;
use Paxofi\CorporateWebsite\Application\Media\ImageProcessor;
use Paxofi\CorporateWebsite\Application\Media\MediaLibrary;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminAnalyticsController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminAuditController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminCareerRolesController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminCatalogController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminEnquiryController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminMediaController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminPagesController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminPasswordResetController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminRecruitmentController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminSessionController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminStaffController;
use Paxofi\CorporateWebsite\Http\Controllers\Admin\AdminTwoFactorController;
use Paxofi\CorporateWebsite\Http\Controllers\AnalyticsController;
use Paxofi\CorporateWebsite\Http\Controllers\CareersController;
use Paxofi\CorporateWebsite\Http\Controllers\CatalogController;
use Paxofi\CorporateWebsite\Http\Controllers\ContentController;
use Paxofi\CorporateWebsite\Http\Controllers\FormSubmissionController;
use Paxofi\CorporateWebsite\Http\Controllers\HealthController;
use Paxofi\CorporateWebsite\Http\Controllers\MediaController;
use Paxofi\CorporateWebsite\Http\Controllers\NavigationController;
use Paxofi\CorporateWebsite\Http\Controllers\PageCopyController;
use Paxofi\CorporateWebsite\Http\Controllers\ReadinessController;
use Paxofi\CorporateWebsite\Http\Middleware\CorsMiddleware;
use Paxofi\CorporateWebsite\Http\Middleware\ErrorHandlingMiddleware;
use Paxofi\CorporateWebsite\Http\Middleware\RequestIdMiddleware;
use Paxofi\CorporateWebsite\Http\Middleware\SecurityHeadersMiddleware;
use Paxofi\CorporateWebsite\Infrastructure\Health\DatabaseHealthCheck;
use Paxofi\CorporateWebsite\Infrastructure\Media\FilesystemMediaStorage;
use Paxofi\CorporateWebsite\Infrastructure\Media\GdImageProcessor;
use Paxofi\CorporateWebsite\Infrastructure\Mail\FileAlertThrottle;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoOutbox;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoPasswordResets;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\Database;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoAnalyticsStore;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\LazyTransactionManager;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoAdminEnquiryRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoAuditLog;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoAuditRecorder;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoCareerRoles;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoCatalogEditorRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoCatalogRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoContentRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoEnquiryRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoJobApplications;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoLoginAttempts;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoMediaRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoPageCopyRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoSessionStore;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoStaffRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoTwoFactorStore;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Infrastructure\Security\OpenSslSecretEncryption;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/**
 * Composition root for the public API.
 *
 * Request lifecycle (PCF MiddlewarePipeline → PCF Router → Controller):
 *   RequestId → SecurityHeaders → Cors → ErrorHandling → Router
 *
 * Controllers and their dependencies are built lazily per route so that
 * liveness/navigation requests never open a database connection.
 */
final class ApiApplication implements HttpHandler
{
    /** Public routes; config/routes.php is the inventory (asserted equal in tests). */
    public const PUBLIC_ROUTES = [
        ['GET', '/api/v1/health'],
        ['GET', '/api/v1/readiness'],
        ['GET', '/api/v1/content'],
        ['GET', '/api/v1/navigation'],
        ['GET', '/api/v1/products'],
        ['GET', '/api/v1/services'],
        ['GET', '/api/v1/careers'],
        ['GET', '/api/v1/careers/roles'],
        ['GET', '/api/v1/careers/roles/{slug}'],
        ['POST', '/api/v1/careers/cv'],
        ['POST', '/api/v1/careers/roles/{slug}/apply'],
        ['POST', '/api/v1/forms/{form_key}/submit'],
        ['GET', '/api/v1/media/{id}/{filename}'],
        ['POST', '/api/v1/analytics/pageview'],
        ['GET', '/api/v1/pages/{page}'],
    ];

    /** Staff routes (decision D-009); each checks the session and permission itself. */
    public const ADMIN_ROUTES = [
        ['GET', '/api/v1/admin/setup'],
        ['POST', '/api/v1/admin/setup'],
        ['POST', '/api/v1/admin/session'],
        ['GET', '/api/v1/admin/session'],
        ['DELETE', '/api/v1/admin/session'],
        ['POST', '/api/v1/admin/session/password'],
        ['POST', '/api/v1/admin/session/mfa'],
        ['POST', '/api/v1/admin/password-reset'],
        ['POST', '/api/v1/admin/password-reset/complete'],
        ['GET', '/api/v1/admin/account/two-factor'],
        ['POST', '/api/v1/admin/account/two-factor/setup'],
        ['POST', '/api/v1/admin/account/two-factor/enable'],
        ['POST', '/api/v1/admin/account/two-factor/recovery-codes'],
        ['POST', '/api/v1/admin/account/two-factor/disable'],
        ['GET', '/api/v1/admin/enquiries'],
        ['GET', '/api/v1/admin/enquiries/{id}'],
        ['PATCH', '/api/v1/admin/enquiries/{id}'],
        ['GET', '/api/v1/admin/users'],
        ['POST', '/api/v1/admin/users'],
        ['PATCH', '/api/v1/admin/users/{id}'],
        ['POST', '/api/v1/admin/users/{id}/two-factor/reset'],
        ['GET', '/api/v1/admin/audit'],
        ['GET', '/api/v1/admin/catalog/{type}'],
        ['POST', '/api/v1/admin/catalog/{type}'],
        ['GET', '/api/v1/admin/catalog/{type}/{id}'],
        ['POST', '/api/v1/admin/catalog/{type}/{id}/draft'],
        ['DELETE', '/api/v1/admin/catalog/{type}/{id}/draft'],
        ['POST', '/api/v1/admin/catalog/{type}/{id}/publish'],
        ['POST', '/api/v1/admin/catalog/{type}/{id}/visibility'],
        ['POST', '/api/v1/admin/catalog/{type}/{id}/revisions/{revision}/restore'],
        ['GET', '/api/v1/admin/media'],
        ['POST', '/api/v1/admin/media'],
        ['PATCH', '/api/v1/admin/media/{id}'],
        ['DELETE', '/api/v1/admin/media/{id}'],
        ['GET', '/api/v1/admin/analytics'],
        ['GET', '/api/v1/admin/pages'],
        ['GET', '/api/v1/admin/pages/{page}'],
        ['POST', '/api/v1/admin/pages/{page}/draft'],
        ['DELETE', '/api/v1/admin/pages/{page}/draft'],
        ['POST', '/api/v1/admin/pages/{page}/publish'],
        ['POST', '/api/v1/admin/pages/{page}/revisions/{revision}/restore'],
        ['GET', '/api/v1/admin/applications'],
        ['GET', '/api/v1/admin/applications/report'],
        ['GET', '/api/v1/admin/applications/{id}'],
        ['GET', '/api/v1/admin/applications/{id}/cv'],
        ['PATCH', '/api/v1/admin/applications/{id}'],
        ['POST', '/api/v1/admin/applications/{id}/scores'],
        ['POST', '/api/v1/admin/applications/{id}/notes'],
        ['POST', '/api/v1/admin/applications/{id}/emails'],
        ['DELETE', '/api/v1/admin/applications/{id}'],
        ['GET', '/api/v1/admin/career-roles'],
        ['POST', '/api/v1/admin/career-roles'],
        ['GET', '/api/v1/admin/career-roles/{id}'],
        ['PATCH', '/api/v1/admin/career-roles/{id}'],
        ['POST', '/api/v1/admin/career-roles/{id}/state'],
    ];

    private readonly HttpHandler $pipeline;
    private readonly Database $database;
    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;
    private readonly PasswordHashing $hasher;
    private readonly MailSettings $mail;
    private readonly ?MailTransport $mailTransport;
    private readonly ErrorAlerts $alerts;
    private readonly PdoOutbox $outbox;

    /**
     * @param (Closure(): PDO)|null $connect override for tests
     * @param (Closure(): DateTimeImmutable)|null $clock override for tests
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly Logger $logger,
        ?Closure $connect = null,
        ?Closure $clock = null,
        ?PasswordHashing $hasher = null,
        private readonly ?ImageProcessor $imageProcessor = null,
        ?MailTransport $mailTransport = null,
        ?AlertThrottle $alertThrottle = null,
    ) {
        $this->database = new Database($connect ?? fn (): PDO => Connection::make($settings->environment));
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->hasher = $hasher ?? new NativeStaffPasswordHasher();
        $this->mailTransport = $mailTransport ?? $settings->mailTransport;
        $mail = $settings->mail ?? MailSettings::disabled();
        // A transport passed in (tests) turns sending on with the configured recipients.
        $this->mail = $mailTransport !== null && !$mail->enabled ? new MailSettings(true, $mail->enquiryAlertTo, $mail->errorAlertTo, $mail->siteUrl) : $mail;
        $this->outbox = new PdoOutbox($this->database, $this->clock);
        $this->alerts = new ErrorAlerts($this->mail, $this->mailTransport, $alertThrottle ?? new FileAlertThrottle(), $this->clock, $logger);

        $this->pipeline = new MiddlewarePipeline([
            new RequestIdMiddleware(),
            new SecurityHeadersMiddleware(enforceHsts: $settings->environment->isProduction()),
            new CorsMiddleware($settings->corsAllowedOrigins),
            new ErrorHandlingMiddleware($logger, $settings->debug, $this->alerts),
        ], $this->router());
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        return $this->pipeline->handle($request);
    }

    /**
     * Work done after the response has gone to the browser (public/index.php):
     * sends error alerts and any emails this request queued (D-016). The
     * bin/send-mail.php cron job retries whatever could not be sent here.
     */
    public function afterResponse(float $budgetSeconds = 20.0): void
    {
        $this->alerts->flush();
        if ($this->mailTransport === null || !$this->mail->enabled || $this->outbox->addedCount() === 0) {
            return;
        }
        try {
            $this->outboxSender()->run(10, $budgetSeconds);
        } catch (\Throwable $exception) {
            $this->logger->warning('mail.after_response_failed', ['error' => $exception->getMessage()]);
        }
        $this->alerts->flush();
    }

    public function outboxSender(): OutboxSender
    {
        if ($this->mailTransport === null) {
            throw new \LogicException('Email sending is not configured (MAIL_TRANSPORT).');
        }

        return new OutboxSender($this->outbox, $this->mailTransport, $this->clock, $this->logger, $this->alerts->report(...));
    }

    public function errorAlerts(): ErrorAlerts
    {
        return $this->alerts;
    }

    private function router(): Router
    {
        $router = new Router();

        $router->get('/api/v1/health', $this->lazy(fn (): Controller => new HealthController()));
        $router->get('/api/v1/readiness', $this->lazy(fn (): Controller => new ReadinessController($this->healthRegistry())));
        $router->get('/api/v1/navigation', $this->lazy(fn (): Controller => new NavigationController()));
        $router->get('/api/v1/content', $this->lazy(fn (): Controller => new ContentController(new ContentService(new PdoContentRepository($this->database)))));

        foreach (CatalogType::cases() as $type) {
            $router->get('/api/v1/' . $type->value, $this->lazy(fn (): Controller => new CatalogController(
                new CatalogService(new PdoCatalogRepository($this->database)),
                $type,
            )));
        }

        $router->post('/api/v1/forms/{form_key}/submit', $this->lazy(fn (): Controller => new FormSubmissionController($this->contactService())));
        $router->get('/api/v1/media/{id}/{filename}', $this->lazy(fn (): Controller => new MediaController($this->mediaLibrary())));
        $router->get('/api/v1/pages/{page}', $this->lazy(fn (): Controller => new PageCopyController($this->pageCopyEditor())));
        $careers = fn (): CareersController => new CareersController($this->careersService());
        $router->get('/api/v1/careers/roles', static fn (HttpRequest $r): HttpResponse => $careers()->roles($r));
        $router->get('/api/v1/careers/roles/{slug}', static fn (HttpRequest $r): HttpResponse => $careers()->role($r));
        $router->post('/api/v1/careers/cv', static fn (HttpRequest $r): HttpResponse => $careers()->uploadCv($r));
        $router->post('/api/v1/careers/roles/{slug}/apply', static fn (HttpRequest $r): HttpResponse => $careers()->apply($r));
        $router->post('/api/v1/analytics/pageview', $this->lazy(fn (): Controller => new AnalyticsController($this->analytics())));

        $session = fn (): AdminSessionController => new AdminSessionController($this->authService(), $this->adminGuard(), $this->clock);
        $router->get('/api/v1/admin/setup', static fn (HttpRequest $r): HttpResponse => $session()->setupStatus($r));
        $router->post('/api/v1/admin/setup', static fn (HttpRequest $r): HttpResponse => $session()->setup($r));
        $router->post('/api/v1/admin/session', static fn (HttpRequest $r): HttpResponse => $session()->signIn($r));
        $router->get('/api/v1/admin/session', static fn (HttpRequest $r): HttpResponse => $session()->current($r));
        $router->add('DELETE', '/api/v1/admin/session', static fn (HttpRequest $r): HttpResponse => $session()->signOut($r));
        $router->post('/api/v1/admin/session/password', static fn (HttpRequest $r): HttpResponse => $session()->changePassword($r));
        $router->post('/api/v1/admin/session/mfa', static fn (HttpRequest $r): HttpResponse => $session()->verifySecondFactor($r));

        $reset = fn (): AdminPasswordResetController => new AdminPasswordResetController($this->passwordResetService(), $this->adminGuard());
        $router->post('/api/v1/admin/password-reset', static fn (HttpRequest $r): HttpResponse => $reset()->request($r));
        $router->post('/api/v1/admin/password-reset/complete', static fn (HttpRequest $r): HttpResponse => $reset()->complete($r));

        $twoFactor = fn (): AdminTwoFactorController => new AdminTwoFactorController($this->twoFactorService(), $this->adminGuard());
        $router->get('/api/v1/admin/account/two-factor', static fn (HttpRequest $r): HttpResponse => $twoFactor()->status($r));
        $router->post('/api/v1/admin/account/two-factor/setup', static fn (HttpRequest $r): HttpResponse => $twoFactor()->setup($r));
        $router->post('/api/v1/admin/account/two-factor/enable', static fn (HttpRequest $r): HttpResponse => $twoFactor()->enable($r));
        $router->post('/api/v1/admin/account/two-factor/recovery-codes', static fn (HttpRequest $r): HttpResponse => $twoFactor()->recoveryCodes($r));
        $router->post('/api/v1/admin/account/two-factor/disable', static fn (HttpRequest $r): HttpResponse => $twoFactor()->disable($r));
        $router->post('/api/v1/admin/users/{id}/two-factor/reset', static fn (HttpRequest $r): HttpResponse => $twoFactor()->reset($r));

        $enquiries = fn (): AdminEnquiryController => new AdminEnquiryController(
            new AdminEnquiryService(new PdoAdminEnquiryRepository($this->database), new PdoAuditRecorder($this->database), new LazyTransactionManager($this->database)),
            $this->adminGuard(),
        );
        $router->get('/api/v1/admin/enquiries', static fn (HttpRequest $r): HttpResponse => $enquiries()->list($r));
        $router->get('/api/v1/admin/enquiries/{id}', static fn (HttpRequest $r): HttpResponse => $enquiries()->show($r));
        $router->add('PATCH', '/api/v1/admin/enquiries/{id}', static fn (HttpRequest $r): HttpResponse => $enquiries()->update($r));

        $staff = fn (): AdminStaffController => new AdminStaffController(
            new StaffAdminService(
                new PdoStaffRepository($this->database),
                new PdoSessionStore($this->database),
                $this->hasher,
                new PdoAuditRecorder($this->database),
                new LazyTransactionManager($this->database),
            ),
            $this->adminGuard(),
        );
        $router->get('/api/v1/admin/users', static fn (HttpRequest $r): HttpResponse => $staff()->list($r));
        $router->post('/api/v1/admin/users', static fn (HttpRequest $r): HttpResponse => $staff()->create($r));
        $router->add('PATCH', '/api/v1/admin/users/{id}', static fn (HttpRequest $r): HttpResponse => $staff()->update($r));

        $catalog = fn (): AdminCatalogController => new AdminCatalogController(
            new CatalogEditor(
                new PdoCatalogEditorRepository($this->database),
                new PdoAuditRecorder($this->database),
                new LazyTransactionManager($this->database),
                new PdoMediaRepository($this->database),
            ),
            $this->adminGuard(),
        );
        $router->get('/api/v1/admin/catalog/{type}', static fn (HttpRequest $r): HttpResponse => $catalog()->list($r));
        $router->post('/api/v1/admin/catalog/{type}', static fn (HttpRequest $r): HttpResponse => $catalog()->create($r));
        $router->get('/api/v1/admin/catalog/{type}/{id}', static fn (HttpRequest $r): HttpResponse => $catalog()->show($r));
        $router->post('/api/v1/admin/catalog/{type}/{id}/draft', static fn (HttpRequest $r): HttpResponse => $catalog()->saveDraft($r));
        $router->add('DELETE', '/api/v1/admin/catalog/{type}/{id}/draft', static fn (HttpRequest $r): HttpResponse => $catalog()->discardDraft($r));
        $router->post('/api/v1/admin/catalog/{type}/{id}/publish', static fn (HttpRequest $r): HttpResponse => $catalog()->publish($r));
        $router->post('/api/v1/admin/catalog/{type}/{id}/visibility', static fn (HttpRequest $r): HttpResponse => $catalog()->visibility($r));
        $router->post('/api/v1/admin/catalog/{type}/{id}/revisions/{revision}/restore', static fn (HttpRequest $r): HttpResponse => $catalog()->restore($r));

        $media = fn (): AdminMediaController => new AdminMediaController($this->mediaLibrary(), $this->adminGuard());
        $router->get('/api/v1/admin/media', static fn (HttpRequest $r): HttpResponse => $media()->list($r));
        $router->post('/api/v1/admin/media', static fn (HttpRequest $r): HttpResponse => $media()->upload($r));
        $router->add('PATCH', '/api/v1/admin/media/{id}', static fn (HttpRequest $r): HttpResponse => $media()->update($r));
        $router->add('DELETE', '/api/v1/admin/media/{id}', static fn (HttpRequest $r): HttpResponse => $media()->delete($r));

        $pages = fn (): AdminPagesController => new AdminPagesController($this->pageCopyEditor(), $this->adminGuard());
        $router->get('/api/v1/admin/pages', static fn (HttpRequest $r): HttpResponse => $pages()->list($r));
        $router->get('/api/v1/admin/pages/{page}', static fn (HttpRequest $r): HttpResponse => $pages()->show($r));
        $router->post('/api/v1/admin/pages/{page}/draft', static fn (HttpRequest $r): HttpResponse => $pages()->saveDraft($r));
        $router->add('DELETE', '/api/v1/admin/pages/{page}/draft', static fn (HttpRequest $r): HttpResponse => $pages()->discardDraft($r));
        $router->post('/api/v1/admin/pages/{page}/publish', static fn (HttpRequest $r): HttpResponse => $pages()->publish($r));
        $router->post('/api/v1/admin/pages/{page}/revisions/{revision}/restore', static fn (HttpRequest $r): HttpResponse => $pages()->restore($r));

        $recruitment = fn (): AdminRecruitmentController => new AdminRecruitmentController($this->recruitmentService(), $this->adminGuard());
        $router->get('/api/v1/admin/applications', static fn (HttpRequest $r): HttpResponse => $recruitment()->list($r));
        $router->get('/api/v1/admin/applications/report', static fn (HttpRequest $r): HttpResponse => $recruitment()->report($r));
        $router->get('/api/v1/admin/applications/{id}', static fn (HttpRequest $r): HttpResponse => $recruitment()->show($r));
        $router->get('/api/v1/admin/applications/{id}/cv', static fn (HttpRequest $r): HttpResponse => $recruitment()->cv($r));
        $router->add('PATCH', '/api/v1/admin/applications/{id}', static fn (HttpRequest $r): HttpResponse => $recruitment()->stage($r));
        $router->post('/api/v1/admin/applications/{id}/scores', static fn (HttpRequest $r): HttpResponse => $recruitment()->score($r));
        $router->post('/api/v1/admin/applications/{id}/notes', static fn (HttpRequest $r): HttpResponse => $recruitment()->note($r));
        $router->post('/api/v1/admin/applications/{id}/emails', static fn (HttpRequest $r): HttpResponse => $recruitment()->email($r));
        $router->add('DELETE', '/api/v1/admin/applications/{id}', static fn (HttpRequest $r): HttpResponse => $recruitment()->delete($r));

        $roles = fn (): AdminCareerRolesController => new AdminCareerRolesController(
            new CareerRoleEditor(new PdoCareerRoles($this->database), new PdoAuditRecorder($this->database), new LazyTransactionManager($this->database), Uuid::v4(...)),
            $this->adminGuard(),
        );
        $router->get('/api/v1/admin/career-roles', static fn (HttpRequest $r): HttpResponse => $roles()->list($r));
        $router->post('/api/v1/admin/career-roles', static fn (HttpRequest $r): HttpResponse => $roles()->create($r));
        $router->get('/api/v1/admin/career-roles/{id}', static fn (HttpRequest $r): HttpResponse => $roles()->show($r));
        $router->add('PATCH', '/api/v1/admin/career-roles/{id}', static fn (HttpRequest $r): HttpResponse => $roles()->update($r));
        $router->post('/api/v1/admin/career-roles/{id}/state', static fn (HttpRequest $r): HttpResponse => $roles()->state($r));

        $router->get('/api/v1/admin/analytics', fn (HttpRequest $r): HttpResponse => (new AdminAnalyticsController($this->analytics(), $this->adminGuard()))->report($r));

        $router->get('/api/v1/admin/audit', fn (HttpRequest $r): HttpResponse => (new AdminAuditController(new PdoAuditLog($this->database), $this->adminGuard()))->list($r));

        return $router;
    }

    /**
     * @param Closure(): Controller $factory
     * @return Closure(HttpRequest): HttpResponse
     */
    private function lazy(Closure $factory): Closure
    {
        return static fn (HttpRequest $request): HttpResponse => $factory()($request);
    }

    private function authService(): AuthService
    {
        return new AuthService(
            new PdoStaffRepository($this->database),
            new PdoSessionStore($this->database),
            new PdoLoginAttempts($this->database),
            $this->hasher,
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
            $this->clock,
            $this->settings->adminSetupToken,
            twoFactor: $this->twoFactorService(),
        );
    }

    private function passwordResetService(): PasswordResetService
    {
        return new PasswordResetService(
            new PdoStaffRepository($this->database),
            new PdoPasswordResets($this->database),
            new PdoSessionStore($this->database),
            $this->hasher,
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
            $this->outbox,
            $this->mail,
            $this->clock,
        );
    }

    private function twoFactorService(): TwoFactorService
    {
        $key = $this->settings->mfaEncryptionKey;

        return new TwoFactorService(
            new PdoTwoFactorStore($this->database),
            $key === null ? null : new OpenSslSecretEncryption($key),
            new PdoStaffRepository($this->database),
            new PdoSessionStore($this->database),
            $this->hasher,
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
            $this->clock,
        );
    }

    private function pageCopyEditor(): PageCopyEditor
    {
        return new PageCopyEditor(
            PageCopySchema::default(),
            new PdoPageCopyRepository($this->database),
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
        );
    }

    private function analytics(): Analytics
    {
        // The website's own hosts: a visit from them is not counted as coming from another site.
        $hosts = array_values(array_filter(array_map(
            static fn (string $origin): string => strtolower((string) parse_url($origin, PHP_URL_HOST)),
            $this->settings->corsAllowedOrigins,
        )));

        return new Analytics(new PdoAnalyticsStore($this->database), $hosts, $this->clock);
    }

    private function mediaLibrary(): MediaLibrary
    {
        return new MediaLibrary(
            new PdoMediaRepository($this->database),
            new FilesystemMediaStorage($this->settings->mediaStoragePath),
            $this->imageProcessor ?? GdImageProcessor::create(),
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
            Uuid::v4(...),
        );
    }

    private function adminGuard(): AdminGuard
    {
        return new AdminGuard($this->authService(), $this->settings->corsAllowedOrigins, $this->settings->environment->isProduction());
    }

    private function healthRegistry(): HealthRegistry
    {
        $registry = new HealthRegistry();
        $registry->register(new DatabaseHealthCheck($this->database, $this->logger));

        return $registry;
    }

    private function careersService(): CareersService
    {
        return new CareersService(
            new PdoCareerRoles($this->database),
            new PdoJobApplications($this->database),
            $this->cvStorage(),
            $this->outbox,
            $this->mail,
            $this->settings->recruitment,
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
            $this->logger,
            $this->clock,
            Uuid::v4(...),
        );
    }

    private function recruitmentService(): RecruitmentService
    {
        return new RecruitmentService(
            new PdoJobApplications($this->database),
            new PdoCareerRoles($this->database),
            $this->cvStorage(),
            $this->outbox,
            $this->mail,
            $this->settings->recruitment,
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
            $this->clock,
        );
    }

    /** CVs live in their own folder inside the media folder, never served publicly (D-019). */
    private function cvStorage(): FilesystemMediaStorage
    {
        return new FilesystemMediaStorage(self::cvFolder($this->settings->mediaStoragePath));
    }

    /** MEDIA_STORAGE_PATH/applications, created on first use; null while media storage is not set up. */
    public static function cvFolder(?string $mediaStoragePath): ?string
    {
        if ($mediaStoragePath === null || !is_dir($mediaStoragePath)) {
            return null;
        }
        $folder = rtrim($mediaStoragePath, '/\\') . DIRECTORY_SEPARATOR . 'applications';
        if (!is_dir($folder)) {
            @mkdir($folder, 0750);
        }

        return $folder;
    }

    private function contactService(): ContactService
    {
        return new ContactService(
            new EnquiryValidator(),
            new PdoEnquiryRepository($this->database),
            new PdoAuditRecorder($this->database),
            new LazyTransactionManager($this->database),
            $this->logger,
            $this->settings->contactRateLimitMax,
            $this->settings->contactRateLimitWindowMinutes,
            $this->outbox,
            $this->mail,
        );
    }
}
