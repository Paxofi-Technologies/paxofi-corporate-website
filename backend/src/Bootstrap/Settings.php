<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Bootstrap;

use Paxofi\Core\Configuration\Environment;

/** Typed application settings derived from the PCF environment contract. */
final readonly class Settings
{
    /** @param list<string> $corsAllowedOrigins */
    public function __construct(
        public Environment $environment,
        public bool $debug,
        public array $corsAllowedOrigins,
        public int $contactRateLimitMax,
        public int $contactRateLimitWindowMinutes,
    ) {
    }

    public static function fromEnvironment(Environment $environment): self
    {
        $origins = array_values(array_filter(array_map(
            'trim',
            explode(',', $environment->get('CORS_ALLOWED_ORIGINS', '') ?? ''),
        )));

        return new self(
            environment: $environment,
            // Debug output is never permitted in production, whatever APP_DEBUG says.
            debug: !$environment->isProduction() && $environment->boolean('APP_DEBUG', false),
            corsAllowedOrigins: $origins,
            contactRateLimitMax: max(1, $environment->integer('CONTACT_RATE_LIMIT_MAX', 5) ?? 5),
            contactRateLimitWindowMinutes: max(1, $environment->integer('CONTACT_RATE_LIMIT_WINDOW_MINUTES', 10) ?? 10),
        );
    }
}
