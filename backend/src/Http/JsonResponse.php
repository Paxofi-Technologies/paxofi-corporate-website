<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Http\Response;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function send(array $body, int $status = 200, array $headers = []): never
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = new Response(
            $status,
            array_merge([
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'no-store',
            ], $headers),
            $json,
        );

        http_response_code($response->status());

        foreach ($response->headers() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        echo $response->body();
        exit;
    }
}
