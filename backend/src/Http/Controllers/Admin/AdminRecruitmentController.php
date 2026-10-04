<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Response;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Careers\RecruitmentService;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;
use Paxofi\CorporateWebsite\Http\RequestFactory;

/**
 * /api/v1/admin/applications[/{id}...] (D-019). Reading needs
 * recruitment.read; changing anything needs recruitment.manage.
 */
final class AdminRecruitmentController
{
    public function __construct(private readonly RecruitmentService $recruitment, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::RECRUITMENT_READ);
        $result = $this->recruitment->list($request->query());

        return ApiResponse::success($request, $result['items'], $result['meta']);
    }

    public function show(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::RECRUITMENT_READ);

        return ApiResponse::success($request, $this->recruitment->get(self::id($request)));
    }

    public function stage(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::RECRUITMENT_MANAGE);

        return ApiResponse::success($request, $this->recruitment->changeStage(self::id($request), RequestBody::parse($request)['stage'] ?? null, $staff, RequestContexts::from($request)));
    }

    public function score(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::RECRUITMENT_MANAGE);
        $body = RequestBody::parse($request);

        return ApiResponse::success($request, $this->recruitment->score(self::id($request), $body['gate'] ?? null, $body['scores'] ?? null, $staff, RequestContexts::from($request)));
    }

    public function note(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::RECRUITMENT_MANAGE);

        return ApiResponse::success($request, $this->recruitment->addNote(self::id($request), RequestBody::parse($request)['body'] ?? null, $staff, RequestContexts::from($request)));
    }

    public function email(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::RECRUITMENT_MANAGE);

        $input = RequestBody::parse($request, RequestFactory::CANDIDATE_EMAIL_MAX_BYTES);

        return ApiResponse::success($request, $this->recruitment->emailCandidate(self::id($request), $input, $staff, RequestContexts::from($request)));
    }

    /** The CV, always as a download and never cached (personal data). */
    public function cv(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::RECRUITMENT_READ);
        $file = $this->recruitment->cv(self::id($request), $staff, RequestContexts::from($request));

        return new Response(200, [
            'content-type' => $file['media_type'],
            'content-length' => (string) strlen($file['bytes']),
            'content-disposition' => 'attachment; filename="' . $file['filename'] . '"',
            'cache-control' => 'no-store',
        ], $file['bytes']);
    }

    public function delete(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::RECRUITMENT_MANAGE);
        $this->recruitment->delete(self::id($request), $staff, RequestContexts::from($request));

        return ApiResponse::success($request, ['deleted' => true]);
    }

    private static function id(HttpRequest $request): string
    {
        return RequestContexts::routeParameter($request, 'id');
    }
}
