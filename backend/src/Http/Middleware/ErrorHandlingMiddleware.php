<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Middleware;

use Paxofi\Core\Contracts\HttpHandler;
use Paxofi\Core\Contracts\HttpMiddleware;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Contracts\Logger;
use Paxofi\Core\Http\HttpException;
use Paxofi\CorporateWebsite\Application\Exception\ApplicationException;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\RateLimited;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\Unauthenticated;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Throwable;

/**
 * Maps application exceptions to safe JSON errors, converts the PCF router's
 * plain-text 404/405 into the API envelope, and turns anything unexpected
 * into a generic 500 while logging diagnostics with the request id.
 */
final class ErrorHandlingMiddleware implements HttpMiddleware
{
    public function __construct(private readonly Logger $logger, private readonly bool $debug = false)
    {
    }

    public function process(HttpRequest $request, HttpHandler $handler): HttpResponse
    {
        try {
            $response = $handler->handle($request);
        } catch (ApplicationException $exception) {
            return $this->fromApplicationException($request, $exception);
        } catch (HttpException $exception) {
            return ApiResponse::error($request, $exception->status(), 'HTTP_' . $exception->status(), $this->debug ? $exception->getMessage() : 'Request could not be processed.');
        } catch (Throwable $exception) {
            $this->logger->error('http.unhandled_exception', $this->logContext($request, $exception));

            return ApiResponse::error($request, 500, 'INTERNAL_ERROR', $this->debug ? $exception->getMessage() : 'An unexpected error occurred.');
        }

        return $this->normalizeRouterResponse($request, $response);
    }

    private function fromApplicationException(HttpRequest $request, ApplicationException $exception): HttpResponse
    {
        [$status, $headers] = match (true) {
            $exception instanceof ValidationFailed => [422, []],
            $exception instanceof ResourceNotFound => [404, []],
            $exception instanceof Unauthenticated => [401, []],
            $exception instanceof Forbidden => [403, []],
            $exception instanceof Conflict => [409, []],
            $exception instanceof RateLimited => [429, ['retry-after' => (string) $exception->retryAfterSeconds()]],
            $exception instanceof DependencyUnavailable => [503, ['retry-after' => '30']],
            default => [400, []],
        };

        if ($exception instanceof DependencyUnavailable) {
            $this->logger->error('dependency.unavailable', $this->logContext($request, $exception));
        }

        return ApiResponse::error($request, $status, $exception->errorCode(), $exception->getMessage(), $exception->details(), $headers);
    }

    private function normalizeRouterResponse(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        if (str_starts_with($response->header('content-type') ?? '', 'application/json')) {
            return $response;
        }

        return match ($response->status()) {
            404 => ApiResponse::error($request, 404, 'NOT_FOUND', 'Resource not found.'),
            405 => ApiResponse::error($request, 405, 'METHOD_NOT_ALLOWED', 'Method not allowed.', [], ['allow' => $response->header('allow') ?? '']),
            default => $response,
        };
    }

    /** @return array<string, mixed> */
    private function logContext(HttpRequest $request, Throwable $exception): array
    {
        $root = $exception;
        while ($root->getPrevious() !== null) {
            $root = $root->getPrevious();
        }

        return [
            'request_id' => ApiResponse::requestId($request),
            'method' => $request->method(),
            'path' => parse_url($request->uri(), PHP_URL_PATH),
            'exception' => $exception::class,
            'cause' => $root::class . ': ' . $root->getMessage(),
            'at' => $root->getFile() . ':' . $root->getLine(),
        ];
    }
}
