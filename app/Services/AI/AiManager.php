<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTOs\AiRequest;
use App\Services\AI\DTOs\AiResponse;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Providers\GrokAiProvider;
use App\Services\AI\Providers\NullAiProvider;
use Illuminate\Support\Manager;

class AiManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->configuration()->normalizeProvider(
            $this->config->get('ai.default', 'null')
        );
    }

    public function provider(?string $name = null): AiProviderInterface
    {
        $name = $name !== null
            ? $this->configuration()->normalizeProvider($name)
            : null;

        return $this->driver($name);
    }

    public function complete(AiRequest $request, ?string $provider = null): AiResponse
    {
        return $this->provider($provider)->complete($request);
    }

    public function configuration(): AiConfiguration
    {
        return $this->container->make(AiConfiguration::class);
    }

    protected function createNullDriver(): AiProviderInterface
    {
        return new NullAiProvider;
    }

    protected function createGrokDriver(): AiProviderInterface
    {
        $config = $this->config->get('ai.providers.grok', []);

        if (! is_array($config)) {
            throw AiProviderException::notConfigured('grok');
        }

        return new GrokAiProvider($config);
    }
}
