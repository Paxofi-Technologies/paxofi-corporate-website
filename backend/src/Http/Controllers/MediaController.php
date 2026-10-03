<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Response;
use Paxofi\CorporateWebsite\Application\Media\MediaLibrary;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * GET /api/v1/media/{id}/{filename}: a file from the media library (D-012).
 *
 * The id never changes content, so responses are cached for a year. Images
 * are shown in place; documents are always downloaded, never opened as a page
 * on the API's address. The content type is the one checked on upload, and
 * browsers may not guess another (nosniff, from SecurityHeadersMiddleware).
 * The files are public, so any site may embed them (Cross-Origin-Resource-
 * Policy: cross-origin): the website requires that for every picture it shows
 * (Cross-Origin-Embedder-Policy), wherever the API is hosted.
 */
final class MediaController implements Controller
{
    public function __construct(private readonly MediaLibrary $library)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        $file = $this->library->file(RequestContexts::routeParameter($request, 'id'));
        $row = $file['row'];
        $etag = '"' . ($row['sha256'] ?? hash('sha256', $file['bytes'])) . '"';
        $filename = (string) $row['filename'];
        $isImage = $row['kind'] === 'image';
        $type = (string) $row['media_type'];

        $headers = [
            'content-type' => str_starts_with($type, 'text/') ? $type . '; charset=utf-8' : $type,
            'cache-control' => 'public, max-age=31536000, immutable',
            'etag' => $etag,
            'cross-origin-resource-policy' => 'cross-origin',
            'content-disposition' => ($isImage ? 'inline' : 'attachment') . '; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
        ];

        if (in_array($etag, array_map('trim', explode(',', $request->header('if-none-match') ?? '')), true)) {
            return new Response(304, $headers);
        }

        return new Response(200, $headers + ['content-length' => (string) strlen($file['bytes'])], $file['bytes']);
    }
}
