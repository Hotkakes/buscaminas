<?php

declare(strict_types=1);

namespace Buscaminas\Http;

final readonly class JsonResponse
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public int $status = 200,
    ) {
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
