<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

final readonly class Response
{
    public function __construct(
        public bool $success,
        public array $data = [],
        public array $meta = [],
        public string $requestId = '',
        public int $status = 200,
    ) {
    }

    public function toJson(): string
    {
        return json_encode([
            'success' => $this->success,
            'data' => $this->data,
            'meta' => $this->meta,
            'request_id' => $this->requestId,
        ], JSON_THROW_ON_ERROR);
    }
}
