<?php

namespace App\Services\AI\DTOs;

final class AiResponse
{
    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $content,
        public readonly ?array $json = null,
        public readonly string $provider = 'null',
        public readonly ?string $model = null,
        public readonly array $meta = [],
    ) {}
}
