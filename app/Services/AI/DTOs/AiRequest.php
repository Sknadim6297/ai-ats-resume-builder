<?php

namespace App\Services\AI\DTOs;

final class AiRequest
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $task,
        public readonly string $prompt,
        public readonly array $context = [],
        public readonly bool $expectJson = true,
        public readonly array $options = [],
    ) {}
}
