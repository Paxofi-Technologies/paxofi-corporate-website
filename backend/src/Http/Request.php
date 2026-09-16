<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $body = [],
        public array $headers = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $body = file_get_contents('php://input');
        $decoded = $body !== false && $body !== '' ? json_decode($body, true) : [];

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            query: $_GET,
            body: is_array($decoded) ? $decoded : [],
            headers: is_array($headers) ? $headers : [],
        );
    }
}
