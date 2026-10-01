<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Contact\ContactService;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestAttributes;
use Paxofi\CorporateWebsite\Http\RequestBody;

/** POST /api/v1/forms/{form_key}/submit */
final class FormSubmissionController implements Controller
{
    public function __construct(private readonly ContactService $contact)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        $parameters = $request->attributes()[RequestAttributes::ROUTE_PARAMETERS] ?? [];
        $formKey = is_array($parameters) && is_string($parameters['form_key'] ?? null) ? $parameters['form_key'] : '';
        $clientIp = $request->attributes()[RequestAttributes::CLIENT_IP] ?? null;

        $receipt = $this->contact->submit($formKey, RequestBody::parse($request), new RequestContext(
            requestId: ApiResponse::requestId($request) ?? '',
            clientIp: is_string($clientIp) ? $clientIp : null,
            userAgent: $request->header('user-agent'),
        ));

        return ApiResponse::success($request, ['accepted' => true, 'form_key' => $receipt->formKey], [], 202);
    }
}
