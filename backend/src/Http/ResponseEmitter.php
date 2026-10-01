<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Contracts\HttpResponse;

/** Writes a PCF response to the PHP SAPI. */
final class ResponseEmitter
{
    public static function emit(HttpResponse $response, bool $includeBody = true): void
    {
        if (!headers_sent()) {
            // Do not advertise the PHP version (expose_php may be on in shared hosting).
            header_remove('X-Powered-By');
            http_response_code($response->status());
            foreach ($response->headers() as $name => $values) {
                foreach ($values as $value) {
                    header(self::canonicalName($name) . ': ' . $value, false);
                }
            }
        }

        if ($includeBody) {
            echo $response->body();
        }
    }

    private static function canonicalName(string $name): string
    {
        return implode('-', array_map(ucfirst(...), explode('-', $name)));
    }
}
