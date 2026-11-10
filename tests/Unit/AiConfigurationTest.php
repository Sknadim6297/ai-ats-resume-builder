<?php

namespace Tests\Unit;

use App\Services\AI\AiConfiguration;
use App\Services\AI\Exceptions\AiProviderException;
use Tests\TestCase;

class AiConfigurationTest extends TestCase
{
    public function test_null_provider_is_not_ready(): void
    {
        config([
            'ai.default' => 'null',
            'ai.providers.grok.api_key' => 'secret-should-not-leak',
            'ai.providers.grok.model' => 'grok-test',
        ]);

        $config = app(AiConfiguration::class);

        $this->assertFalse($config->status()['ready']);
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('not configured');
        $config->assertReadyForLiveRequests();
    }

    public function test_unquoted_null_env_coerces_to_null_driver(): void
    {
        config(['ai.default' => null]);

        $this->assertSame('null', app(AiConfiguration::class)->normalizeProvider(config('ai.default')));
    }

    public function test_missing_api_key_and_model_are_reported_by_name_only(): void
    {
        config([
            'ai.default' => 'grok',
            'ai.providers.grok.api_key' => '',
            'ai.providers.grok.model' => '',
        ]);

        try {
            app(AiConfiguration::class)->assertReadyForLiveRequests();
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertStringContainsString('XAI_API_KEY', $e->getMessage());
            $this->assertStringContainsString('XAI_MODEL', $e->getMessage());
            $this->assertStringNotContainsString('sk-', $e->getMessage());
        }
    }

    public function test_ready_when_grok_key_and_model_present(): void
    {
        config([
            'ai.default' => 'grok',
            'ai.providers.grok.api_key' => 'test-key',
            'ai.providers.grok.model' => 'test-model',
        ]);

        $status = app(AiConfiguration::class)->status();

        $this->assertTrue($status['ready']);
        $this->assertTrue($status['api_key_configured']);
        $this->assertTrue($status['model_configured']);

        app(AiConfiguration::class)->assertReadyForLiveRequests();
        $this->assertTrue(true);
    }

    public function test_ready_when_gemini_key_and_model_present(): void
    {
        config([
            'ai.default' => 'gemini',
            'ai.providers.gemini.api_key' => 'test-key',
            'ai.providers.gemini.model' => 'gemini-2.5-flash',
        ]);

        $status = app(AiConfiguration::class)->status('gemini');

        $this->assertTrue($status['ready']);
        app(AiConfiguration::class)->assertReadyForLiveRequests('gemini');
        $this->assertTrue(true);
    }

    public function test_resume_import_task_defaults_to_gemini_provider(): void
    {
        config([
            'ai.default' => 'null',
            'ai.tasks.resume_import.provider' => 'gemini',
        ]);

        $this->assertSame('gemini', app(AiConfiguration::class)->providerForTask('resume_import'));
    }
}
