<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\AiProviderException;

/**
 * Validates server-side AI env/config before any live provider call.
 * Never returns or logs secret values.
 */
class AiConfiguration
{
    /**
     * Ensure the active provider can make live requests (currently: grok).
     *
     * @throws AiProviderException
     */
    public function assertReadyForLiveRequests(?string $provider = null): void
    {
        $provider = $this->normalizeProvider($provider ?? config('ai.default'));

        if ($provider === 'null' || $provider === '') {
            throw AiProviderException::notConfigured('null');
        }

        if ($provider !== 'grok') {
            throw AiProviderException::requestFailed(
                $provider,
                'Unsupported AI provider. Set AI_PROVIDER=grok.'
            );
        }

        $apiKey = trim((string) config('ai.providers.grok.api_key', ''));
        $model = trim((string) config('ai.providers.grok.model', ''));

        $missing = [];
        if ($apiKey === '') {
            $missing[] = 'XAI_API_KEY';
        }
        if ($model === '') {
            $missing[] = 'XAI_MODEL';
        }

        if ($missing !== []) {
            throw AiProviderException::missingCredentials('grok', $missing);
        }
    }

    public function normalizeProvider(mixed $provider): string
    {
        if (! is_string($provider) && $provider !== null) {
            return 'null';
        }

        $value = trim((string) ($provider ?? ''));

        return $value !== '' ? $value : 'null';
    }

    /**
     * @return array{provider: string, api_key_configured: bool, model_configured: bool, ready: bool}
     */
    public function status(): array
    {
        $provider = $this->normalizeProvider(config('ai.default'));
        $apiKeyConfigured = trim((string) config('ai.providers.grok.api_key', '')) !== '';
        $modelConfigured = trim((string) config('ai.providers.grok.model', '')) !== '';

        return [
            'provider' => $provider,
            'api_key_configured' => $apiKeyConfigured,
            'model_configured' => $modelConfigured,
            'ready' => $provider === 'grok' && $apiKeyConfigured && $modelConfigured,
        ];
    }
}
