<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\AuthService;
use Paxofi\CorporateWebsite\Application\Admin\SignIn;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;
use Paxofi\CorporateWebsite\Http\SessionCookie;

/**
 * GET|POST /api/v1/admin/setup, POST|GET|DELETE /api/v1/admin/session,
 * POST /api/v1/admin/session/password
 */
final class AdminSessionController
{
    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(private readonly AuthService $auth, private readonly AdminGuard $guard, private readonly Closure $clock)
    {
    }

    public function setupStatus(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, ['available' => $this->auth->setupAvailable()]);
    }

    public function setup(HttpRequest $request): HttpResponse
    {
        $this->guard->requireTrustedOrigin($request);

        return $this->signedIn($request, $this->auth->setup(RequestBody::parse($request), RequestContexts::from($request)), 201);
    }

    public function signIn(HttpRequest $request): HttpResponse
    {
        $this->guard->requireTrustedOrigin($request);

        return $this->signedIn($request, $this->auth->signIn(RequestBody::parse($request), RequestContexts::from($request)), 200);
    }

    public function current(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->guard->require($request)->user->toArray());
    }

    public function signOut(HttpRequest $request): HttpResponse
    {
        $this->auth->signOut($this->guard->require($request), RequestContexts::from($request));

        return ApiResponse::success($request, ['signed_out' => true], [], 200, ['set-cookie' => SessionCookie::clear($this->guard->secureCookies())]);
    }

    public function changePassword(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request);
        $this->auth->changePassword($staff, RequestBody::parse($request), RequestContexts::from($request));

        return ApiResponse::success($request, ['changed' => true]);
    }

    private function signedIn(HttpRequest $request, SignIn $signIn, int $status): HttpResponse
    {
        $cookie = SessionCookie::issue($signIn->token, $signIn->expiresAt, ($this->clock)(), $this->guard->secureCookies());

        return ApiResponse::success($request, $signIn->user->toArray(), ['expires_at' => $signIn->expiresAt->format(DATE_ATOM)], $status, ['set-cookie' => $cookie]);
    }
}
