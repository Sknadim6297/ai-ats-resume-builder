<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTOs\AiRequest;
use App\Services\AI\DTOs\AiResponse;
use App\Services\AI\Exceptions\AiProviderException;

/**
 * Safe default provider. No network calls are made.
 */
class NullAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'null';
    }

    public function complete(AiRequest $request): AiResponse
    {
        throw AiProviderException::notConfigured('null');
    }
}
