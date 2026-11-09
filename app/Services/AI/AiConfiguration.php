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
     * @var list<string>
     */
    private const LIVE_PROVIDERS = ['grok', 'gemini'];

    /**
     * Ensure the active provider can make live requests.
     *
     * @throws AiProviderException
     */
    public function assertReadyForLiveRequests(?string $provider = null): void
    {
        $provider = $this->normalizeProvider($provider ?? config('ai.default'));

        if ($provider === 'null' || $provider === '') {
            throw AiProviderException::notConfigured('null');
        }

        if (! in_array($provider, self::LIVE_PROVIDERS, true)) {
            throw AiProviderException::requestFailed(
                $provider,
                'Unsupported AI provider. Set AI_PROVIDER=grok or AI_PROVIDER=gemini.'
            );
        }

        [$apiKeyEnv, $modelEnv] = $this->credentialEnvKeys($provider);
        $apiKey = trim((string) config("ai.providers.{$provider}.api_key", ''));
        $model = trim((string) config("ai.providers.{$provider}.model", ''));

        $missing = [];
        if ($apiKey === '') {
            $missing[] = $apiKeyEnv;
        }
        if ($model === '') {
            $missing[] = $modelEnv;
        }

        if ($missing !== []) {
            throw AiProviderException::missingCredentials($provider, $missing);
        }
    }

    /**
     * Resolve which provider should handle a named AI task.
     */
    public function providerForTask(string $task): string
    {
        $configured = config("ai.tasks.{$task}.provider");

        return $this->normalizeProvider($configured ?: config('ai.default'));
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
    public function status(?string $provider = null): array
    {
        $provider = $this->normalizeProvider($provider ?? config('ai.default'));
        $apiKeyConfigured = trim((string) config("ai.providers.{$provider}.api_key", '')) !== '';
        $modelConfigured = trim((string) config("ai.providers.{$provider}.model", '')) !== '';

        return [
            'provider' => $provider,
            'api_key_configured' => $apiKeyConfigured,
            'model_configured' => $modelConfigured,
            'ready' => in_array($provider, self::LIVE_PROVIDERS, true)
                && $apiKeyConfigured
                && $modelConfigured,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentialEnvKeys(string $provider): array
    {
        return match ($provider) {
            'gemini' => ['GEMINI_API_KEY', 'GEMINI_MODEL'],
            default => ['XAI_API_KEY', 'XAI_MODEL'],
        };
    }
}
