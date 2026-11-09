<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AiRequest;
use App\Services\AI\DTOs\AiResponse;

interface AiProviderInterface
{
    /**
     * Execute a structured AI request.
     *
     * Implementations must keep credentials server-side and must never
     * expose raw API keys to the frontend.
     *
     * @throws \App\Services\AI\Exceptions\AiProviderException
     */
    public function complete(AiRequest $request): AiResponse;

    public function name(): string;
}
