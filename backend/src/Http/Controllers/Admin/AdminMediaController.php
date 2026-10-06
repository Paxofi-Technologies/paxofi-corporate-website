<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Media\MediaKind;
use Paxofi\CorporateWebsite\Application\Media\MediaLibrary;
use Paxofi\CorporateWebsite\Application\Media\MediaRules;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * /api/v1/admin/media[/{id}] (D-012). Listing, uploading and editing need
 * content.edit; deleting needs content.publish.
 *
 * An upload is the file itself as the request body (Content-Type:
 * application/octet-stream), with filename, alt_text and title in the query.
 */
final class AdminMediaController
{
    public function __construct(private readonly MediaLibrary $library, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_EDIT);
        $kind = MediaKind::tryFrom((string) ($request->query()['kind'] ?? ''));

        return ApiResponse::success($request, $this->library->list($kind), $this->library->capabilities(self::serverMaxBytes()));
    }

    public function upload(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);
        $body = $request->body();
        $declared = (int) ($request->header('content-length') ?? '0');
        if ($body === '' && $declared > 0) {
            // PHP drops bodies larger than post_max_size before the API sees them.
            $limit = self::serverMaxBytes();
            throw self::fileError('The server did not accept a file this large' . ($limit === null ? '' : ' (its limit is ' . self::megabytes($limit) . ')') . '. Ask an administrator to raise post_max_size (deployment guide Step 10).');
        }
        if (strlen($body) > MediaRules::UPLOAD_MAX_BYTES) {
            throw self::fileError('Files can be up to 10 MB (images up to 5 MB).');
        }
        $query = $request->query();
        $filename = is_string($query['filename'] ?? null) ? mb_substr($query['filename'], 0, 255) : '';

        return ApiResponse::success($request, $this->library->upload($body, $filename, $query, $staff, RequestContexts::from($request)), [], 201);
    }

    public function update(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->library->update(self::id($request), RequestBody::parse($request), $staff, RequestContexts::from($request)));
    }

    /** POST /api/v1/admin/media/{id}/resource: list a document on the Resources page or take it off (D-023). */
    public function resource(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->library->setResource(self::id($request), RequestBody::parse($request), $staff, RequestContexts::from($request)));
    }

    public function delete(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);
        $this->library->delete(self::id($request), $staff, RequestContexts::from($request));

        return ApiResponse::success($request, ['deleted' => true]);
    }

    private static function id(HttpRequest $request): string
    {
        return RequestContexts::routeParameter($request, 'id');
    }

    /** PHP's post_max_size in bytes, or null when unlimited or unknown. */
    public static function serverMaxBytes(): ?int
    {
        $value = trim((string) ini_get('post_max_size'));
        if ($value === '' || $value === '0') {
            return null;
        }
        $bytes = (int) $value * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return $bytes > 0 ? $bytes : null;
    }

    private static function megabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1), '0'), '.') . ' MB';
    }

    private static function fileError(string $message): ValidationFailed
    {
        return new ValidationFailed(['file' => $message], $message);
    }
}
