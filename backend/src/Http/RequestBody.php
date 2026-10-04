<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

/** Decodes JSON or form-encoded request bodies into an associative array. */
final class RequestBody
{
    /** @return array<string, mixed> */
    public static function parse(HttpRequest $request, int $maxBytes = RequestFactory::MAX_BODY_BYTES): array
    {
        $body = $request->body();
        if (strlen($body) > $maxBytes) {
            throw new ValidationFailed([], 'Request body is too large.');
        }

        $contentType = strtolower($request->header('content-type') ?? '');

        if (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($body, $fields);

            return self::stringKeyed($fields);
        }

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ValidationFailed([], 'Request body must be valid JSON.');
        }

        if (!is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new ValidationFailed([], 'Request body must be a JSON object.');
        }

        return self::stringKeyed($decoded);
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
