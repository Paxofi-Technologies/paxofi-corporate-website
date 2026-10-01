<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Bootstrap;

use Closure;
use PDO;
use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpHandler;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Contracts\Logger;
use Paxofi\Core\Http\MiddlewarePipeline;
use Paxofi\Core\Http\Router;
use Paxofi\Core\Observability\HealthRegistry;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogService;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogType;
use Paxofi\CorporateWebsite\Application\Contact\ContactService;
use Paxofi\CorporateWebsite\Application\Contact\EnquiryValidator;
use Paxofi\CorporateWebsite\Application\Content\ContentService;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Http\Controllers\CatalogController;
use Paxofi\CorporateWebsite\Http\Controllers\ContentController;
use Paxofi\CorporateWebsite\Http\Controllers\FormSubmissionController;
use Paxofi\CorporateWebsite\Http\Controllers\HealthController;
use Paxofi\CorporateWebsite\Http\Controllers\NavigationController;
use Paxofi\CorporateWebsite\Http\Controllers\ReadinessController;
use Paxofi\CorporateWebsite\Http\Middleware\CorsMiddleware;
use Paxofi\CorporateWebsite\Http\Middleware\ErrorHandlingMiddleware;
use Paxofi\CorporateWebsite\Http\Middleware\RequestIdMiddleware;
use Paxofi\CorporateWebsite\Http\Middleware\SecurityHeadersMiddleware;
use Paxofi\CorporateWebsite\Infrastructure\Health\DatabaseHealthCheck;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\Database;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\LazyTransactionManager;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoAuditRecorder;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoCatalogRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoContentRepository;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\PdoEnquiryRepository;

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
    /**
     * Public routes that are implemented. Admin routes from the endpoint
     * inventory (config/routes.php) are deliberately NOT registered until
     * authentication and authorization are implemented (CW-BE-010/011).
     */
    public const PUBLIC_ROUTES = [
        ['GET', '/api/v1/health'],
        ['GET', '/api/v1/readiness'],
        ['GET', '/api/v1/content'],
        ['GET', '/api/v1/navigation'],
        ['GET', '/api/v1/products'],
        ['GET', '/api/v1/services'],
        ['GET', '/api/v1/careers'],
        ['POST', '/api/v1/forms/{form_key}/submit'],
    ];

    private readonly HttpHandler $pipeline;
    private readonly Database $database;

    /** @param (Closure(): PDO)|null $connect override for tests */
    public function __construct(
        private readonly Settings $settings,
        private readonly Logger $logger,
        ?Closure $connect = null,
    ) {
        $this->database = new Database($connect ?? fn (): PDO => Connection::make($settings->environment));

        $this->pipeline = new MiddlewarePipeline([
            new RequestIdMiddleware(),
            new SecurityHeadersMiddleware(enforceHsts: $settings->environment->isProduction()),
            new CorsMiddleware($settings->corsAllowedOrigins),
            new ErrorHandlingMiddleware($logger, $settings->debug),
        ], $this->router());
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        return $this->pipeline->handle($request);
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

    private function healthRegistry(): HealthRegistry
    {
        $registry = new HealthRegistry();
        $registry->register(new DatabaseHealthCheck($this->database, $this->logger));

        return $registry;
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
        );
    }
}
