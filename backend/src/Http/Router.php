<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

final class Router
{
    /** @var array<string, callable(Request): Response> */
    private array $routes = [];

    /**
     * Register a method/path handler. PCF routing will become the authoritative
     * runtime integration once the approved PCF bootstrap contract is consumed.
     */
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET ' . $path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method . ' ' . $request->path] ?? null;

        if ($handler === null) {
            return new Response(
                success: false,
                data: [],
                meta: ['error' => 'NOT_FOUND'],
                requestId: bin2hex(random_bytes(16)),
                status: 404,
            );
        }

        return $handler($request);
    }
}
