<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Admin\AuthService;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\Unauthenticated;

/**
 * Admin request checks (decision D-009):
 *  - state-changing requests must come from an allowed website origin
 *    (with the SameSite=Strict cookie, this blocks cross-site request forgery);
 *  - a valid staff session is required, and optionally a permission.
 */
final class AdminGuard
{
    /** @var array<string, true> */
    private readonly array $allowedOrigins;

    /** @param list<string> $allowedOrigins */
    public function __construct(private readonly AuthService $auth, array $allowedOrigins, private readonly bool $secureCookies)
    {
        $normalized = [];
        foreach ($allowedOrigins as $origin) {
            $normalized[rtrim(strtolower(trim($origin)), '/')] = true;
        }
        $this->allowedOrigins = $normalized;
    }

    /** Rejects state-changing requests that do not come from the website. */
    public function requireTrustedOrigin(HttpRequest $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $origin = strtolower($request->header('origin') ?? '');
        if ($origin === '' || !isset($this->allowedOrigins[$origin])) {
            throw new Forbidden('This request must come from the Paxofi website.');
        }
    }

    public function require(HttpRequest $request, ?string $permission = null): AuthenticatedStaff
    {
        $staff = $this->auth->authenticate(SessionCookie::read($request, $this->secureCookies));
        if ($staff === null) {
            throw new Unauthenticated();
        }
        $this->requireTrustedOrigin($request);
        if ($permission !== null && !$staff->user->can($permission)) {
            throw new Forbidden();
        }

        return $staff;
    }

    public function token(HttpRequest $request): ?string
    {
        return SessionCookie::read($request, $this->secureCookies);
    }

    public function secureCookies(): bool
    {
        return $this->secureCookies;
    }
}
