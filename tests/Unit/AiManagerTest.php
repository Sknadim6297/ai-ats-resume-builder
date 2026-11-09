<?php

namespace Tests\Unit;

use App\Services\AI\AiManager;
use App\Services\AI\DTOs\AiRequest;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Providers\NullAiProvider;
use Tests\TestCase;

class AiManagerTest extends TestCase
{
    public function test_default_provider_is_null_when_unconfigured(): void
    {
        config(['ai.default' => 'null']);

        $manager = $this->app->make(AiManager::class);

        $this->assertInstanceOf(NullAiProvider::class, $manager->provider());
        $this->assertSame('null', $manager->provider()->name());
    }

    public function test_null_provider_does_not_make_network_calls(): void
    {
        config(['ai.default' => 'null']);

        $this->expectException(AiProviderException::class);

        $this->app->make(AiManager::class)->complete(
            new AiRequest(task: 'jd_extraction', prompt: 'Extract requirements')
        );
    }
}
