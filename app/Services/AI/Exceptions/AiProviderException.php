<?php

namespace App\Services\AI\Exceptions;

use Exception;

class AiProviderException extends Exception
{
    public static function notConfigured(string $provider): self
    {
        return new self("AI provider [{$provider}] is not configured.");
    }

    /**
     * @param  list<string>  $missingEnvKeys  Env var names only — never secret values.
     */
    public static function missingCredentials(string $provider, array $missingEnvKeys): self
    {
        $keys = implode(', ', array_values(array_unique($missingEnvKeys)));

        return new self("AI provider [{$provider}] is missing credentials: {$keys}.");
    }

    public static function requestFailed(string $provider, string $reason): self
    {
        return new self("AI provider [{$provider}] request failed: {$reason}");
    }

    public static function invalidJson(string $provider): self
    {
        return new self("AI provider [{$provider}] returned invalid JSON.");
    }

    public static function unsupportedModel(string $provider): self
    {
        return new self("AI provider [{$provider}] request failed: Unsupported or invalid model.");
    }

    public static function disabledInPhase(string $phase): self
    {
        return new self("Live AI requests are disabled in {$phase}. Configure a provider and enable it in a later phase.");
    }
}
